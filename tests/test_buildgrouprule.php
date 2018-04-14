<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/pdo.php';

use CDash\Model\Build;
use CDash\Model\BuildGroup;
use CDash\Model\BuildGroupRule;

class BuildGroupRuleTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testBuildGroupRule()
    {
        $buildgrouprule = new BuildGroupRule();

        $buildgrouprule->GroupId = 0;
        if ($buildgrouprule->Exists()) {
            $this->fail('Exists() should return false when GroupId is 0');
            return 1;
        }

        $buildgrouprule->GroupId = 1;

        if ($buildgrouprule->Add()) {
            $this->fail("Add returned true when it should be false.\n");
            return 1;
        }

        $buildgrouprule->BuildType = 1;
        $buildgrouprule->BuildName = 'TestBuild';
        $buildgrouprule->SiteId = 1;
        $buildgrouprule->Expected = 1;

        if (!$buildgrouprule->Add()) {
            $this->fail("Add() returned false when it should be true.\n");
            return 1;
        }

        if ($buildgrouprule->Add()) {
            $this->fail("Add returned true when it should be false.\n");
            return 1;
        }

        $this->pass('Passed');
        return 0;
    }

    public function testMatchLongestRule()
    {
        // Make some buildgroups.
        $group1 = new BuildGroup();
        $group1->SetName('group1');
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

        if (!$shortrule->Add()) {
            $this->fail("Failed to add short rule");
        }
        if (!$longrule->Add()) {
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
}
