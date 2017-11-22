<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';
require_once 'models/build.php';
require_once 'models/buildgrouprule.php';

use CDash\Config;
use CDash\Database;

class TimelineTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->PDO = Database::getInstance()->getPdo();
        $this->Config = Config::getInstance();
        $this->base_url = $this->Config->get('CDASH_BASE_URL');
    }

    private function toggle_expected($client, $build, $expected)
    {
        // Mark this build as expected.
        $payload = [
            'buildid' => $build->Id,
            'groupid' => $build->GroupId,
            'expected' => $expected
        ];
        try {
            $response = $client->request('POST',
                    $this->base_url .  '/api/v1/build.php',
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
        $client = new GuzzleHttp\Client(['cookies' => true]);
        try {
            $response = $client->request('POST',
                    $this->base_url . '/user.php',
                    ['form_params' => [
                        'login' => 'simpletest@localhost',
                        'passwd' => 'simpletest',
                        'sent' => 'Login >>']]);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            $this->fail($e->getMessage());
            return false;
        }

        // Mark this build as expected.
        $this->toggle_expected($client, $build, 1);

        // Now that we have an expected build,
        // validate timeline data for index.php and testOverview.php.


        $timestamp_to_check = 1235350800000;
        $pages_to_check = ['index.php', 'testOverview.php'];

        $answer_key = [
            'index.php' => [
                'Warnings' => 1,
                'Test Failures' => 1
            ],
            'testOverview.php' => [
                'Failing Tests' => 1,
                'Not Run Tests' => 1,
                'Passing Tests' => 1
            ]
        ];
        foreach ($pages_to_check as $page) {
            $this->get($this->url . "/api/v1/timeline.php?date=2009-02-23&page=$page&project=InsightExample");
            $content = $this->getBrowser()->getContent();
            $jsonobj = json_decode($content, true);

            if ($jsonobj['extentstart'] != 1235350800000) {
                $this->fail("Expected 1235350800000 but found " . $jsonobj['extentstart'] . " for extentstart");
            }
            if ($jsonobj['extentend'] != 1235437200000) {
                $this->fail("Expected 1235437200000 but found " . $jsonobj['extentend'] . " for extentend");
            }

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

        // Revert back to unexpected.
        $this->toggle_expected($client, $build, 0);
    }
}
