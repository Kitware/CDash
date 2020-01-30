<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Database;
use CDash\Model\Build;
use CDash\Model\BuildRelationship;

class BuildRelationshipTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->PDO = Database::getInstance()->getPdo();
    }

    public function testBuildRelationships()
    {
        // Clean up any previous runs of this test.
        $stmt = $this->PDO->prepare(
            "SELECT id FROM build WHERE name = 'test-build-relationships'");
        pdo_execute($stmt);
        while ($row = $stmt->fetch()) {
            remove_build($row['id']);
        }

        // Create three builds to relate to each other.
        $start_time = 1533838200;
        $projectid = get_project_id('InsightExample');
        $build1 = new Build();
        $build1->Name = 'test-build-relationships';
        $build1->SetStamp('20180809-1810-Experimental');
        $build1->StartTime = gmdate(FMT_DATETIME, $start_time);
        $build1->SiteId = 1;
        $build1->Type = 'Experimental';
        $build1->ProjectId = $projectid;
        $build2 = clone $build1;
        $build3 = clone $build1;

        $build1->Id = add_build($build1);

        $build2->SetStamp('20180809-1811-Experimental');
        $build2->StartTime = gmdate(FMT_DATETIME, $start_time + 60);
        $build2->Id = add_build($build2);

        $build3->SetStamp('20180809-1812-Experimental');
        $build3->StartTime = gmdate(FMT_DATETIME, $start_time + 120);
        $build3->Id = add_build($build3);

        // Login as admin.
        $client = $this->getGuzzleClient();

        // Use the API to create relationships between these builds.
        $payloads = [
            [
                'project'    => 'InsightExample',
                'buildid'      => $build2->Id,
                'relatedid'    => $build1->Id,
                'relationship' => 'depends on'
            ],
            [
                'project'    => 'InsightExample',
                'buildid'      => $build3->Id,
                'relatedid'    => $build2->Id,
                'relationship' => 'uses results from'
            ]
        ];
        foreach ($payloads as $payload) {
            try {
                $response = $client->request('POST',
                        $this->url .  '/api/v1/relateBuilds.php',
                        ['json' => $payload]);
            } catch (GuzzleHttp\Exception\ClientException $e) {
                $this->fail($e->getMessage());
            }
        }

        // Verify that these results are displayed on the build summary page.
        $content = $this->connect($this->url . '/api/v1/buildSummary.php?buildid=' . $build2->Id);
        $json_content = json_decode($content, true);

        $to = $json_content['relationships_to'][0];
        $this->assertEqual($build3->Id, $to['buildid']);
        $this->assertEqual('uses results from', $to['relationship']);

        $from = $json_content['relationships_from'][0];
        $this->assertEqual($build1->Id, $from['relatedid']);
        $this->assertEqual('depends on', $from['relationship']);
    }
}
