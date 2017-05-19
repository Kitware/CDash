<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/pdo.php';
require_once 'models/buildgrouprule.php';

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
}
