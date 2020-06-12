<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';

use CDash\Model\Build;
use CDash\Model\Project;

class BuildGetDateTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testBuildGetDate()
    {
        // For easy comparison, use Eastern Time inputs to generate some UTC values.
        date_default_timezone_set('America/New_York');
        $evening_before = gmdate('Y-m-d H:i:s', strtotime('2009-02-23 19:59:59'));
        $evening_after = gmdate('Y-m-d H:i:s', strtotime('2009-02-23 20:00:00'));
        $morning_before = gmdate('Y-m-d H:i:s', strtotime('2009-02-23 08:59:59'));
        $morning_after = gmdate('Y-m-d H:i:s', strtotime('2009-02-23 09:00:00'));

        $build = new Build();
        $build->Id = 1;
        $build->ProjectId = 1;
        $build->Filled = true;
        $build->GetProject()->Fill();

        $original_nightlytime = $build->GetProject()->NightlyTime;

        // Test the case where the project's start time is in the evening.
        $build->GetProject()->SetNightlyTime('20:00:00 America/New_York');
        $build->StartTime = $evening_before;

        $expected_date = '2009-02-23';
        $date = $build->GetDate();
        if ($date !== $expected_date) {
            $this->fail("Evening case: expected $expected_date, found $date");
        }

        $build->StartTime = $evening_after;

        $expected_date = '2009-02-24';
        $build->NightlyStartTime = false;
        $date = $build->GetDate();
        if ($date !== $expected_date) {
            $this->fail("Evening case: expected $expected_date, found $date");
        }

        // Test the case where the project's start time is in the morning.
        $build->GetProject()->SetNightlyTime('09:00:00 America/New_York');
        $build->StartTime = $morning_before;
        $expected_date = '2009-02-22';
        $build->NightlyStartTime = false;
        $date = $build->GetDate();
        if ($date !== $expected_date) {
            $this->fail("Morning case: expected $expected_date, found $date");
        }

        $build->StartTime = $morning_after;
        $expected_date = '2009-02-23';
        $build->NightlyStartTime= false;
        $date = $build->GetDate();
        if ($date !== $expected_date) {
            $this->fail("Morning case: expected $expected_date, found $date");
        }

        // Restore project to original settings.
        $build->GetProject()->SetNightlyTime($original_nightlytime);
        $build->GetProject()->Save();
    }
}
