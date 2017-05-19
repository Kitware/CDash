<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/pdo.php';
require_once 'models/buildgroupposition.php';

class BuildGroupPositionTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testBuildGroupPosition()
    {
        $buildgroupposition = new BuildGroupPosition();

        $buildgroupposition->GroupId = 0;
        if ($buildgroupposition->Exists()) {
            $this->fail('Exists() should return false when GroupId is 0');
            return 1;
        }

        $buildgroupposition->GroupId = 1;
        $buildgroupposition->Position = 1;
        $buildgroupposition->StartTime = date('Y-m-d H:i:s', time() - 1);
        $buildgroupposition->EndTime = date('Y-m-d H:i:s');

        //call save twice to cover different execution paths
        if (!$buildgroupposition->Add()) {
            $this->fail("Add() returned false when it should be true.\n");
            return 1;
        }
        if ($buildgroupposition->Add()) {
            $this->fail("Add returned true when it should be false.\n");
            return 1;
        }
        $this->pass('Passed');

        return 0;
    }
}
