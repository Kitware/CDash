<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';
require_once 'models/project.php';
use CDash\Database;

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

        // Submit testing data.
        $filesToSubmit = ['Test_1.xml', 'Test_2.xml', 'Test_3.xml', 'Test_4.xml', 'Test_5.xml'];
        $dir = dirname(__FILE__) . '/data/TimeStatus';
        foreach ($filesToSubmit as $file) {
            if (!$this->submission('TimeStatus', "$dir/$file")) {
                $this->fail("Failed to submit $file");
                return;
            }
        }

        // Verify results.
        $stmt = $this->PDO->prepare(
            'SELECT id, time, timemean, timestd, timestatus
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
