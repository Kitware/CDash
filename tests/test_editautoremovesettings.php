<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class EditAutoRemoveSettingsTestCase extends KWWebTestCase
{
  function __construct()
   {
   parent::__construct();
   }

  function testEditProject()
    {
    $this->startCodeCoverage();

    $content = $this->connect($this->url);
    if(!$content)
      {
      return;
      }
    $this->login();
    $query = $this->db->query("SELECT id FROM project WHERE name = 'InsightExample'");
    $projectid = $query[0]['id'];
    $content = $this->connect($this->url.'/manageBuildGroup.php?projectid='.$projectid);
    if(!$content)
      {
      return;
      }
    
    if(!$this->cdashpro)  
      {
      $buildgroupid = $this->db->query("SELECT id FROM buildgroup WHERE projectid='$projectid' AND name='Experimental'");
      $id = $buildgroupid[0]['id'];
      $this->setField("autoremovetimeframe_$id",'7');
  
      $this->clickSubmitByName('submitAutoRemoveSettings');
      
      $timeframe = $this->db->query("SELECT autoremovetimeframe FROM buildgroup WHERE id='$id'");
      
      if($timeframe[0]['autoremovetimeframe'] == 7)
        {
        $this->pass("Passed");
        }
      else
        {
        $this->fail("Autoremovetimeframe was not set correctly");
        }
      }
    $this->stopCodeCoverage();
    }
}

?>
