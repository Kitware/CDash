<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');
require_once('include/common.php');
require_once('include/pdo.php');
require_once('models/build.php');

class BuildGetDateTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testBuildGetDate()
    {
        $build = new Build();
        $build->Id = 1;
        $build->ProjectId = 1;
        $build->Filled = true;

        $build->StartTime = date("Y-m-d H:i:s", strtotime('2009-02-23 19:59:59'));

        $expected_date = '2009-02-23';
        $date = $build->GetDate();
        if ($build->GetDate() !== $expected_date) {
            $this->fail("Expected $expected_date, found $date");
            return 1;
        }

        $build->StartTime = date("Y-m-d H:i:s", strtotime('2009-02-23 20:00:00'));

        $expected_date = '2009-02-24';
        $date = $build->GetDate();
        if ($build->GetDate() !== $expected_date) {
            $this->fail("Expected $expected_date, found $date");
            return 1;
        }

        $this->pass('Tests passed');
        return 0;
    }
}
