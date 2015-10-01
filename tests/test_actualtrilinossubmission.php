<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class ActualTrilinosSubmissionTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }


    public function createProjectWithName($project)
    {
        $content = $this->connect($this->url);
        if (!$content) {
            $this->fail("no content after connect");
            return;
        }

        $this->login();
        if (!$this->analyse($this->clickLink('Create new project'))) {
            $this->fail("analyse failed after login then clickLink [Create new project]");
            return;
        }

        $this->setField('name', $project);
        $this->setField('description',
      $project.' project created by test code in file ['.__FILE__.']');
        $this->setField('public', '1');
        $this->setField('emailBrokenSubmission', '1');
        $this->setField('showIPAddresses', '1');
        $this->setField('displayLabels', '1');
        $this->clickSubmitByName('Submit');

        $this->checkErrors();
        $this->assertText('The project '.$project.' has been created successfully.');

        $this->logout();
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
        $this->createProjectWithName("TrilinosDriver");
        $this->createProjectWithName("Trilinos");
    }


    public function submitFiles()
    {
        $dir = str_replace("\\", '/',
      dirname(__FILE__).'/data/ActualTrilinosSubmission');

        $listfilename = $dir."/orderedFileList.txt";

        $filenames = explode("\n", file_get_contents($listfilename));

        foreach ($filenames as $filename) {
            if (!$filename) {
                continue;
            }

            $fullname = str_replace("\r", '', $dir.'/'.$filename);

            if (!file_exists($fullname)) {
                $this->fail("file '$fullname' does not exist");
                return false;
            }

            if (preg_match("/TrilinosDriver/", $filename)) {
                $project = "TrilinosDriver";
            } elseif (preg_match("/Trilinos/", $filename)) {
                $project = "Trilinos";
            } else {
                $this->fail("file [$fullname] does not match project name Trilinos or TrilinosDriver");
                return false;
            }

            if (!$this->submission($project, $fullname)) {
                $this->fail("Submission of file [$fullname] for project [$project] failed");
                return false;
            }
        }

        $this->assertTrue(true, "Submission of all files succeeded");
        return true;
    }


    public function testActualTrilinosSubmission()
    {
        $this->createProjects();
        $this->setEmailCommitters("Trilinos", 1);
        $this->submitFiles();
        $this->setEmailCommitters("Trilinos", 0);
        $this->deleteLog($this->logfilename);
    }
}
