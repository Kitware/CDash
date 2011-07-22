<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class ActualTrilinosSubmissionTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
    }


  function createProject($project)
    {
    $content = $this->connect($this->url);
    if (!$content)
      {
      $this->fail("no content after connect");
      return;
      }

    $this->login();
    if (!$this->analyse($this->clickLink('[Create new project]')))
      {
      $this->fail("analyse failed after login then clickLink [Create new project]");
      return;
      }

    $this->setField('name', $project);
    $this->setField('description',
      $project.' project created by test code in file ['.__FILE__.']');
    $this->setField('public', '1');
    $this->setField('showIPAddresses', '1');
    $this->setField('displayLabels', '1');
    $this->clickSubmitByName('Submit');

    $this->checkErrors();
    $this->assertText('The project '.$project.' has been created successfully.');

    $this->logout();
    }


  function createProjects()
    {
    $this->createProject("Trilinos");
    $this->createProject("TrilinosDriver");
    }


  function submitFiles()
    {
    $dir = str_replace("\\", '/',
      dirname(__FILE__).'/data/ActualTrilinosSubmission');

    $listfilename = $dir."/orderedFileList.txt";

    $filenames = explode("\n", file_get_contents($listfilename));

    foreach($filenames as $filename)
      {
      if (!$filename)
        {
        continue;
        }

      $fullname = str_replace("\r", '', $dir.'/'.$filename);

      if (!file_exists($fullname))
        {
        $this->fail("file '$fullname' does not exist");
        return false;
        }

      if (preg_match("/TrilinosDriver/", $filename))
        {
        $project = "TrilinosDriver";
        }
      elseif (preg_match("/Trilinos/", $filename))
        {
        $project = "Trilinos";
        }
      else
        {
        $this->fail("file [$fullname] does not match project name Trilinos or TrilinosDriver");
        return false;
        }

      if (!$this->submission($project, $fullname))
        {
        $this->fail("Submission of file [$fullname] for project [$project] failed");
        return false;
        }
      }

    $this->assertTrue(true, "Submission of all files succeeded");
    return true;
    }


  function testActualTrilinosSubmission()
    {
    $this->createProjects();
    $this->submitFiles();
    $this->deleteLog($this->logfilename);
    }
}
?>
