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
    // set the repository for the project
    $this->login();
    $this->connect($this->url."/createProject.php?edit=1&projectid=3");
    $this->setField('cvsRepository[0]', 'git://fake/repo.git');
    $this->clickSubmitByName('Update');

    // submit a mock machine config xml
    $machineDescription = dirname(__FILE__)."/data/camelot.cdash.xml";
    $result = $this->uploadfile($this->url."/submit.php?sitename=camelot.kitware&systemname=Ubuntu32&submitinfo=1",$machineDescription);
    if($this->findString($result,'error')   ||
       $this->findString($result,'Warning') ||
       $this->findString($result,'Notice'))
      {
      $this->assertEqual($result,"\n");
      return false;
      }

    // schedule a job for the machine
    $this->connect($this->url."/manageClient.php?projectid=3");
    $scriptContents = "message(\"hello world\")";
    $this->setField('clientscript',$scriptContents);
    $this->clickSubmitByName('submit');

    // get the site id
    $siteid = $this->get($this->url."/submit.php?sitename=camelot.kitware&systemname=Ubuntu32&getsiteid=1");

    // wait a few seconds so that we know we are ahead of the schedule time
    sleep(5);

    // verify that we receive the correct script when we query for a job
    $content = $this->get($this->url."/submit.php?getjob=1&siteid=".$siteid);

    if(!$this->findString($content, "message(\"hello world\")"))
      {
      $this->fail("Wrong script was sent: expected hello world script but got: ".$content." for siteid=".$siteid);
      return false;
      }
    $this->pass("Passed");
    return 0;
    }
}
?>
