<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

require_once('cdash/common.php');

class AutoRemoveBuildsOnSubmitTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
    }


  function enableAutoRemoveConfigSetting()
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
        fwrite($handle, '// test config settings injected by file [' . __FILE__ . "]\n");
        fwrite($handle, '$CDASH_AUTOREMOVE_BUILDS = \'1\';'."\n");
        fwrite($handle, '$CDASH_ASYNCHRONOUS_SUBMISSION = false;'."\n");
        }
      if($line != '')
        {
        fwrite($handle, "$line\n");
        }
      }
    fclose($handle);
    }


  function setAutoRemoveTimeFrame()
    {
    // set project autoremovetimeframe
    $result = $this->db->query("UPDATE project ".
      "SET autoremovetimeframe='7' WHERE name='EmailProjectExample'");
    }


  function testBuildsRemovedOnSubmission()
    {
    $this->enableAutoRemoveConfigSetting();

    $this->setAutoRemoveTimeFrame();

    $this->startCodeCoverage();

    $result = $this->db->query("SELECT id FROM project WHERE name = 'EmailProjectExample'");
    $projectid = $result[0]['id'];
    $this->db->query("DELETE FROM dailyupdate WHERE projectid='$projectid'");

    $rep  = dirname(__FILE__)."/data/EmailProjectExample";
    $testxml1 = "$rep/1_test.xml";

    if(!$this->submission('EmailProjectExample',$testxml1))
      {
      $this->fail("submission 1 failed");
      $this->stopCodeCoverage();
      return;
      }

    $testxml2 = "$rep/2_test.xml";
    if(!$this->submission('EmailProjectExample',$testxml2))
      {
      $this->fail("submission 2 failed");
      $this->stopCodeCoverage();
      return;
      }

    if(!$this->cdashpro && !$this->compareLog($this->logfilename, "$rep/cdash_autoremove.log"))
      {
      $this->fail("compareLog failed");
      $this->stopCodeCoverage();
      return;
      }

    $this->pass("Passed");
    $this->stopCodeCoverage();
    }
}

?>
