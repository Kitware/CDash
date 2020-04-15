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
                'NightlyTime' => '21:00:00 America/New_York',
                'ShareLabelFilters' => '1');
        return $this->createProject($settings);
    }

    public function setEmailCommitters($projectname, $email)
    {
        // The "Email committers" checkbox is on the manageBuildGroup.php
        // page, but we set it here directly through database manipulation:
        // it's easier to write the test code this way... And we're testing
        // the functionality of emailing the committers, not the web UI for
        // it.
        //
        $db = \CDash\Database::getInstance();
        $query = $db->query("SELECT id FROM project WHERE name='$projectname'");
        $project = $query->fetchColumn();
        $sql ="UPDATE buildgroup SET emailcommitters=:email WHERE projectid=:project";
        $stmt = $db->prepare($sql);
        $db->execute($stmt, [':email' => $email, ':project' => $project]);
    }

    public function createProjects()
    {
        return $this->createProjectWithName('TrilinosDriver') &&
            $this->createProjectWithName('Trilinos');
    }

    public function testActualTrilinosSubmission()
    {
        if ($this->createProjects()) {
            $this->setEmailCommitters('Trilinos', 1);
            $this->submitFiles('ActualTrilinosSubmission');
            $this->submitFiles('ActualTrilinosSubmissionTestData');
            $this->verifyResults();
            $this->setEmailCommitters('Trilinos', 0);
            $this->deleteLog($this->logfilename);
        }
    }

    public function testSubProjectBuildErrors()
    {
        // Get the parent build that we just created.
        $query = $this->db->query(
            "SELECT id FROM build
            WHERE name = 'Windows_NT-MSVC10-SERIAL_DEBUG_DEV'
            AND parentid = -1");

        if (!isset($query[0])) {
            $this->fail("Unable to find build, 'Windows_NT-MSVC10-SERIAL_DEBUG_DEV'");
            return;
        }

        $buildid = $query[0]['id'];

        // Verify 8 build errors.
        $this->get($this->url . "/api/v1/viewBuildError.php?buildid=$buildid");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $num_errors = 0;

        if (isset($jsonobj['errors'])) {
            $num_errors = count($jsonobj['errors']);
        }

        if ($num_errors !== 8) {
            $this->fail("Expected 8 build errors, found $num_errors");
        }

        // Verify 296 build warnings.
        $this->get($this->url . "/api/v1/viewBuildError.php?buildid=$buildid&type=1");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $num_warnings = 0;

        if (isset($jsonobj['errors'])) {
            $num_warnings = count($jsonobj['errors']);
        }

        if ($num_warnings !== 296) {
            $this->fail("Expected 296 build warnings, found $num_warnings");
        }
    }
}
