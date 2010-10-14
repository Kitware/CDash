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

  /*function testEnableManageClients()
    {
    $filename = dirname(__FILE__)."/../cdash/config.local.php";
    $handle = fopen($filename, "r");
    $contents = fread($handle, filesize($filename));
    fclose($handle);
    $handle = fopen($filename, "w");
    $lines = explode("\n", $contents);
    foreach($lines as $line)
      {
      if(strpos($line, "?>") !== false)
        {
        fwrite($handle, '$CDASH_MANAGE_CLIENTS = \'1\';');
        fwrite($handle, "\n?>");
        }
      else
        {
        fwrite($handle, "$line\n");
        }
      }
    fclose($handle);
    $this->pass("Passed");
    }*/

  function testManageClientTest()
    {
    //1 set the repository for the project
    $this->login();
    $this->connect($this->url."/createProject.php?edit=1&projectid=3");
    $this->setField('cvsRepository[0]', 'git://fake/repo.git');
    $this->clickSubmitByName('Update');
    
    //2 submit a mock machine config xml
    $machineDescription = dirname(__FILE__)."/data/camelot.cdash.xml";
    $result = $this->uploadfile($this->url."/submit.php?sitename=camelot.kitware&systemname=Ubuntu32&submitinfo=1",$machineDescription);
    if($this->findString($result,'error')   ||
       $this->findString($result,'Warning') ||
       $this->findString($result,'Notice'))
      {
      $this->assertEqual($result,"\n");
      return false;
      }
    
    //3 schedule a job for the machine
    $this->connect($this->url."/manageClient.php?projectid=3");
    $scriptContents = 'message("hello world")';
    $this->setField('clientscript',$scriptContents);
    $this->clickSubmitByName('submit');
    
    //4 get the site id
    $siteid = $this->get($this->url."/submit.php?sitename=camelot.kitware&systemname=Ubuntu32&getsiteid=1");

    sleep(3);
    //5 verify that we receive the correct script when we query for a job
    $content = $this->get($this->url."/submit.php?getjob=1&siteid=$siteid");

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
