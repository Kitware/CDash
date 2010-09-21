<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class AutoRemoveBuildsOnSubmitTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
    }

  function testSetAutoRemoveTimeFrame()
    {
    $this->login();
    $query = $this->db->query("SELECT id FROM project WHERE name = 'EmailProjectExample'");
    $projectid = $query[0]['id'];
    $content = $this->connect($this->url.'/createProject.php?projectid='.$projectid);

    if($content == false)
      {
      return;
      }

    // set global autoremovetimeframe
    $this->setField("autoremoveTimeframe",'7');
    $this->clickSubmitByName('Update');
    }

  function testEnableAutoRemoveConfigSetting()
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
        fwrite($handle, '$CDASH_AUTOREMOVE_BUILDS = \'1\';');
        fwrite($handle, "\n?>");
        }
      else
        {
        fwrite($handle, "$line\n");
        }
      }
    fclose($handle);
    $this->pass("Passed");
    }

  function testBuildsRemovedOnSubmission()
    {
    $this->startCodeCoverage();
    $this->login();
    $this->deleteLog($this->logfilename);
    $query = $this->db->query("SELECT id FROM project WHERE name = 'EmailProjectExample'");
    $projectid = $query[0]['id'];
    $this->db->query("DELETE FROM dailyupdate WHERE projectid='$projectid'");

    $this->deleteLog($this->logfilename);
    $rep  = dirname(__FILE__)."/data/EmailProjectExample";
    $testxml1 = "$rep/1_test.xml";

    if(!$this->submission('EmailProjectExample',$testxml1))
      {
      return;
      }

    $testxml2 = "$rep/2_test.xml";
    if(!$this->submission('EmailProjectExample',$testxml2))
      {
      return;
      }

    if(!$this->compareLog($this->logfilename,"$rep/cdash_autoremove.log"))
      {
      return;
      }

    $this->pass("Passed");
    $this->stopCodeCoverage();
    }
}

?>
