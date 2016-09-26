<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'tests/trilinos_submission_test.php';

class ActualTrilinosSubmissionTestCase extends TrilinosSubmissionTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function createProjectWithName($project)
    {
        $settings = array(
                'Name' => $project,
                'Description' => $project . ' project created by test code in file [' . __FILE__ . ']',
                'EmailBrokenSubmission' => '1',
                'ShowIPAddresses' => '1',
                'DisplayLabels' => '1',
                'ShareLabelFilters' => '1');
        $this->createProject($settings);
    }

    public function setEmailCommitters($projectname, $val)
    {
        // The "Email committers" checkbox is on the manageBuildGroup.php
        // page, but we set it here directly through database manipulation:
        // it's easier to write the test code this way... And we're testing
        // the functionality of emailing the committers, not the web UI for
        // it.
        //
        $query = $this->db->query("SELECT id FROM project WHERE name='$projectname'");
        $projectid = $query[0]['id'];
        $query = $this->db->query("UPDATE buildgroup SET emailcommitters='$val' WHERE projectid='$projectid'");
    }

    public function createProjects()
    {
        $this->createProjectWithName('TrilinosDriver');
        $this->createProjectWithName('Trilinos');
    }

    public function testActualTrilinosSubmission()
    {
        $this->createProjects();
        $this->setEmailCommitters('Trilinos', 1);
        $this->submitFiles('ActualTrilinosSubmission');
        $this->submitFiles('ActualTrilinosSubmissionTestData');
        $this->verifyResults();
        $this->setEmailCommitters('Trilinos', 0);
        $this->deleteLog($this->logfilename);
    }
}
