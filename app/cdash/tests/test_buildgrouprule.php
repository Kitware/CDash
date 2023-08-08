<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';




use CDash\Database;
use CDash\Model\Build;
use CDash\Model\BuildGroup;
use CDash\Model\BuildGroupRule;

class BuildGroupRuleTestCase extends KWWebTestCase
{
    protected $PDO;

    public function __construct()
    {
        parent::__construct();
        $this->PDO = Database::getInstance()->getPdo();
    }

    public function testBuildGroupRule()
    {
        $buildgrouprule = new BuildGroupRule();
        if ($buildgrouprule->Exists()) {
            $this->fail('Exists() should return false when GroupId is 0');
            return 1;
        }

        $buildgrouprule->GroupId = 1;
        $buildgrouprule->BuildType = 1;
        $buildgrouprule->BuildName = 'TestBuild';
        $buildgrouprule->SiteId = 1;
        $buildgrouprule->Expected = 1;

        if (!$buildgrouprule->Save()) {
            $this->fail("Save() returned false when it should be true.\n");
            return 1;
        }

        if ($buildgrouprule->Save()) {
            $this->fail("Save() returned true when it should be false.\n");
            return 1;
        }

        $buildgrouprule->Delete(false);
        $this->pass('Passed');
        return 0;
    }

    public function testMatchLongestRule()
    {
        // Make some buildgroups.
        $group1 = new BuildGroup();
        $group1->SetName('group1');
        $group1->SetProjectId(1);
        $group2 = clone $group1;
        $group2->SetName('group2');
        $group1->Save();
        $group2->Save();

        // Make some wildcard rules for these groups.
        $shortrule = new BuildGroupRule();
        $shortrule->GroupId = $group1->GetId();
        $shortrule->BuildType = 'Nightly';
        $shortrule->BuildName = '%nightly-foo%';
        $shortrule->SiteId = -1;
        $shortrule->Expected = 1;

        $longrule = clone $shortrule;
        $longrule->GroupId = $group2->GetId();
        $longrule->BuildName = '%nightly-foo-coverage%';

        if (!$shortrule->Save()) {
            $this->fail("Failed to add short rule");
        }
        if (!$longrule->Save()) {
            $this->fail("Failed to add long rule");
        }

        // Make a build whose name matches both of these rules.
        $build = new Build();
        $build->Name = 'gcc-nightly-foo-coverage-full';
        $build->Type = 'Nightly';
        $build->ProjectId = $group1->GetProjectId();
        $build->StartTime = gmdate(FMT_DATETIME, time() - 100);

        // Make sure the longer rule wins.
        $groupid = $group1->GetGroupIdFromRule($build);
        $expectedid = $group2->GetId();
        if ($groupid != $expectedid) {
            $this->fail("Expected $expectedid, found $groupid");
        }

        // This also deletes the rules.
        $group1->Delete();
        $group2->Delete();
    }

    public function testOnlyExpireRulesFromSameProject()
    {
        // Clean up any previous runs of this test case.
        $stmt = $this->PDO->prepare(
            "SELECT id FROM build WHERE name = 'no-project-leakage'");
        pdo_execute($stmt);
        while ($row = $stmt->fetch()) {
            remove_build($row['id']);
        }
        $this->PDO->exec(
            "DELETE FROM build2grouprule WHERE buildname = 'no-project-leakage'");

        // Create two similar builds that belong to different projects.
        $build1 = new Build();
        $build1->Name = 'no-project-leakage';
        $build1->SiteId = 1;
        $build1->StartTime = gmdate(FMT_DATETIME, time() - 100);
        $build1->Type = 'Experimental';
        $build2 = clone $build1;

        $build1->ProjectId = 1;
        $build1->Id = add_build($build1);

        $build2->ProjectId = 2;
        $build2->Id = add_build($build2);

        // Login as admin.
        $client = $this->getGuzzleClient();

        // Mark both builds as expected.  This will define buildgroup rules
        // for each.
        foreach ([$build1, $build2] as $build) {
            // Mark this build as expected.
            $payload = [
                'buildid'  => $build->Id,
                'groupid'  => $build->GroupId,
                'expected' => 1
            ];
            try {
                $response = $client->request('POST',
                    $this->url .  '/api/v1/build.php',
                    ['json' => $payload]);
            } catch (GuzzleHttp\Exception\ClientException $e) {
                $this->fail($e->getMessage());
            }
        }

        // Change the group of one of the builds.
        $group = new BuildGroup();
        $group->SetProjectId($build1->ProjectId);
        $group->SetName('Continuous');
        $payload = [
            'buildid'    => $build1->Id,
            'expected'   => 1,
            'newgroupid' => $group->GetId()
        ];
        try {
            $response = $client->request('POST',
                $this->url .  '/api/v1/build.php',
                ['json' => $payload]);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            $this->fail($e->getMessage());
        }

        // Check the database.
        // We expect to find one finished rule and two active ones.
        $num_active = 0;
        $num_finished = 0;
        $stmt = $this->PDO->prepare(
            "SELECT * FROM build2grouprule
                WHERE buildname = 'no-project-leakage' AND
                      siteid = 1 AND
                      buildtype = 'Experimental'");
        pdo_execute($stmt);
        while ($row = $stmt->fetch()) {
            if ($row['endtime'] == '1980-01-01 00:00:00') {
                $num_active++;
            } else {
                $num_finished++;
            }
        }
        if ($num_active !== 2) {
            $this->fail("Expected two active rules, found $num_active");
        }
        if ($num_finished !== 1) {
            $this->fail("Expected one finished rule, found $num_finished");
        }
    }
}
