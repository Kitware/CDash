<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Database;
use CDash\Test\UseCase\TestUseCase;
use CDash\Model\Project;

class TimeStatusTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->PDO = Database::getInstance()->getPdo();
    }

    public function testTimeStatus()
    {
        // Create project.
        $project = new Project();
        $project->Id = get_project_id('TimeStatus');
        if ($project->Id >= 0) {
            remove_project_builds($project->Id);
            $project->Delete();
        }

        $settings = [
            'Name' => 'TimeStatus',
            'Description' => 'Project for test time status',
            'ShowTestTime' => 1
        ];
        $projectid = $this->createProject($settings);
        if ($projectid < 1) {
            $this->fail('Failed to create project');
            return;
        }

        $use_case = new TestUseCase();
        $use_case
            ->createSite([
                'BuildName' => 'test_timing',
                'BuildStamp' => '20180127-1723-Experimental',
                'Name' => 'elysium'
            ])
            ->setProjectId($projectid)
            ->setStartTime(1516900999)
            ->setEndTime(1516901001)
            ->createTestPassed('nap')
            ->setTestProperties('nap', ['Execution Time' => 2.00447]);

        $use_case->build();

        $use_case
            ->setSiteAttribute('BuildStamp', '20180127-1724-Experimental')
            ->setStartTime(1516901059)
            ->setEndTime(1516901061)
            ->setTestProperties('nap', ['Execution Time' => 2.00447]);

        $use_case->build();

        $use_case
            ->setSiteAttribute('BuildStamp', '20180127-1725-Experimental')
            ->setStartTime(1516901119)
            ->setEndTime(1516901188)
            ->setTestProperties('nap', ['Execution Time' => 9.00447]);

        $use_case->build();

        $use_case
            ->setSiteAttribute('BuildStamp', '20180127-1726-Experimental')
            ->setStartTime(1516901179)
            ->setEndTime(1516901248)
            ->setTestProperties('nap', ['Execution Time' => 9.00447]);

        $use_case->build();

        $use_case
            ->setSiteAttribute('BuildStamp', '20180127-1727-Experimental')
            ->setStartTime(1516901239)
            ->setEndTime(1516901308)
            ->setTestProperties('nap', ['Execution Time' => 9.00447]);

        $use_case->build();

        // Verify results.
        $stmt = $this->PDO->prepare(
            'SELECT b.id, time, timemean, timestd, timestatus
            FROM build b
            JOIN build2test b2t ON (b.id = b2t.buildid)
            WHERE projectid = ?
            ORDER BY starttime');
        if (!pdo_execute($stmt, [$projectid])) {
            $this->fail("SELECT query failed");
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $num_rows = count($rows);
        if ($num_rows !== 5) {
            $this->fail("Expected 5 rows, found $num_rows");
        }

        $this->verify_row($rows[0], 2.00, 0.00, 0.00, 0);
        $this->verify_row($rows[1], 2.00, 0.60, 1.13, 0);
        $this->verify_row($rows[2], 9.00, 0.60, 1.13, 1);
        $this->verify_row($rows[3], 9.00, 0.60, 1.13, 2);
        $this->verify_row($rows[4], 9.00, 0.60, 1.13, 3);
    }

    private function verify_field($expected, $found, $field, $id)
    {
        if ($expected != $found) {
            $this->fail("Expected $expected but found $found for $field on build #$id");
        }
    }

    private function verify_row($row, $time, $timemean, $timestd, $timestatus)
    {
        $this->verify_field($row['time'], $time, 'time', $row['id']);
        $this->verify_field($row['timemean'], $timemean, 'timemean', $row['id']);
        $this->verify_field($row['timestd'], $timestd, 'timestd', $row['id']);
        $this->verify_field($row['timestatus'], $timestatus, 'timestatus', $row['id']);
    }
}
