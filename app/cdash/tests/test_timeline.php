<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';



use CDash\Database;
use CDash\Model\Build;

class TimelineTestCase extends KWWebTestCase
{
    protected $PDO;

    public function __construct()
    {
        parent::__construct();
        $this->PDO = Database::getInstance()->getPdo();
    }

    private function toggle_expected($client, $build, $expected)
    {
        // Mark this build as expected.
        $payload = [
            'buildid' => $build->Id,
            'groupid' => $build->GroupId,
            'expected' => $expected,
        ];
        try {
            $response = $client->request('POST',
                $this->url .  '/api/v1/build.php',
                ['json' => $payload]);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            $this->fail($e->getMessage());
        }

        // Make sure it's really expected now.
        $this->get($this->url . "/api/v1/is_build_expected.php?buildid=$build->Id");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        if ($jsonobj['expected'] != $expected) {
            $this->fail("is_build_expected did not return $expected");
        }
    }

    public function testTimeline()
    {
        // Get a known build.
        $this->get($this->url . '/api/v1/index.php?project=InsightExample&date=2009-02-23');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $build = null;
        foreach ($jsonobj['buildgroups'] as $buildgroup_response) {
            foreach ($buildgroup_response['builds'] as $build_response) {
                if ($build_response['buildname'] ===
                        'Linux-g++-4.1-LesionSizingSandbox_Debug') {
                    $build = new Build();
                    $build->Id = $build_response['id'];
                    $build->FillFromId($build->Id);
                    break;
                }
            }
            if (!is_null($build)) {
                break;
            }
        }
        if (is_null($build)) {
            $this->fail("build lookup failed");
        }

        // Login as admin.
        $client = $this->getGuzzleClient();

        // Mark this build as expected.
        $this->toggle_expected($client, $build, 1);

        // Now that we have an expected build, validate timeline data on relevant pages.
        $pages_to_check = ['testOverview.php', 'viewBuildGroup.php'];

        $answer_key = [
            'testOverview.php' => [
                'Failing Tests' => 1,
                'Not Run Tests' => 1,
                'Passing Tests' => 1,
            ],
            'viewBuildGroup.php' => [
                'Test Failures' => 1,
            ],
        ];
        foreach ($pages_to_check as $page) {
            $filterdata = json_encode(['pageId' => $page]);
            $extra_param = $page == 'viewBuildGroup.php' ? '&buildgroup=Experimental' : '';
            $this->get($this->url . "/api/v1/timeline.php?date=2009-02-23&filterdata=$filterdata&project=InsightExample$extra_param");
            $content = $this->getBrowser()->getContent();
            $jsonobj = json_decode($content, true);

            $this->validateExtent(1235350800000, 1235437200000, $jsonobj);
            $this->validatePage($answer_key, $page, 1235350800000, $jsonobj);
        }

        // Revert back to unexpected.
        $this->toggle_expected($client, $build, 0);
    }

    private function validateExtent($start, $end, $jsonobj)
    {
        if ($jsonobj['extentstart'] != $start) {
            $this->fail("Expected $start but found " . $jsonobj['extentstart'] . " for extentstart");
        }
        if ($jsonobj['extentend'] != $end) {
            $this->fail("Expected $end but found " . $jsonobj['extentend'] . " for extentend");
        }
    }

    private function validatePage($answer_key, $page, $timestamp_to_check, $jsonobj)
    {
        foreach ($answer_key[$page] as $measurement => $expected) {
            $validated = false;
            foreach ($jsonobj['data'] as $trend) {
                if ($trend['key'] === $measurement) {
                    foreach ($trend['values'] as $value) {
                        if ($value[0] == $timestamp_to_check) {
                            if ($value[1] = $expected) {
                                $validated = true;
                            } else {
                                $this->fail("Expected $expected but found " . $value[1] . " for $measurement on $page");
                            }
                            break;
                        }
                    }
                    break;
                }
            }
            if (!$validated) {
                $this->fail("Failed to validate $measurement on $page");
            }
        }
    }
}
