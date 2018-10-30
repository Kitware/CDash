<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';

use CDash\Model\Build;

class SubmissionAssignBuildIdTestCase extends KWWebTestCase
{
    public function testSubmissionAssignBuildId()
    {
        $begin_test_time = time();
        $file_to_submit = dirname(__FILE__) .  '/data/AssignBuildId/Configure.xml';
        $buildid = $this->submission_assign_buildid(
                $file_to_submit, 'InsightExample', 'assign_buildid',
                'localhost', '20180918-0100-Nightly');
        if (!$buildid || !is_numeric($buildid)) {
            $this->fail('Did not receive a numeric buildid for submission');
        }

        $build = new Build();
        $build->Id = $buildid;
        if (!$build->Exists()) {
            $this->fail("Build #$buildid does not exist");
        }

        $build->FillFromId($buildid);
        $this->assertEqual('assign_buildid', $build->Name);
        $this->assertTrue(strtotime($build->SubmitTime) >= $begin_test_time);
        $this->assertEqual('2018-09-18 14:27:07', $build->StartTime);
        $this->assertEqual('2018-09-18 14:27:08', $build->EndTime);

        remove_build($buildid);
    }
}
