<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class ManageClientTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
    }

  function testManageClientTest()
    {
    //1 submit a mock machine config xml
    $machineDescription = dirname(__FILE__)."/data/camelot.cdash.xml";
    $result = $this->uploadfile($this->url."/submit.php?sitename=camelot.kitware&systemname=Ubuntu-32-g++&submitinfo=1",$machineDescription);
    if($this->findString($result,'error')   ||
       $this->findString($result,'Warning') ||
       $this->findString($result,'Notice'))
      {
      $this->assertEqual($result,"\n");
      return false;
      }
    
    //2 set the repository for the project
    $this->login();
    $this->connect($this->url."/createProject.php?edit=1&projectid=1");
    $this->setField('cvsRepository[0]', 'git://fake/repo.git');
    $this->clickSubmitByName('Update');
    
    //3 schedule a job for the machine
    $this->connect($this->url."/manageClient.php?projectid=1");
    $scriptContents = 'message("hello world")';
    $this->setField('clientscript',$scriptContents);
    $this->clickSubmitByName('submit');
    
    //4 verify that we receive the correct script when we query for a job
    $content = $this->connect($this->url."/submit.php?getjob=1&siteid=1");
    if(!$this->findString($content, 'message("hello world")'))
      {
      $this->fail("Wrong script was sent: expected hello world script but got: $content");
      return false;
      }
    $this->pass("Passed");
    return 0;
    }
}
?>
