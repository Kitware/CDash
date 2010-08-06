<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class SubProjectTestCase extends KWWebTestCase
{
  var $url           = null;
  var $db            = null;
  var $projecttestid = null;
  var $logfilename   = null;
  
  function __construct()
    {
    parent::__construct();
    require('config.test.php');
    $this->url = $configure['urlwebsite'];
    $this->db  =& new database($db['type']);
    $this->db->setDb($db['name']);
    $this->db->setHost($db['host']);
    $this->db->setUser($db['login']);
    $this->db->setPassword($db['pwd']);
    $this->logfilename = $cdashpath."/backup/cdash.log";
    $this->deleteLog($this->logfilename);
    }
   
  function testAccessToWebPageProjectTest()
    {
    $this->login();
    // first project necessary for testing
    $name = 'SubProjectExample';
    $description = 'Project SubProjectExample test for cdash testing';
     
    // Create the project
    $this->clickLink('[Create new project]');
    $this->setField('name',$name);
    $this->setField('description',$description);
    $this->setField('public','1');
    $this->setField('emailBrokenSubmission','1');
    $this->setField('emailRedundantFailures','1');  
    $this->clickSubmitByName('Submit');
    
    $content = $this->connect($this->url.'/index.php?project=SubProjectExample');
    if(!$content)
      {
      return;
      }
    if(!$this->checkLog($this->logfilename))
      {
      return;
      }
    $this->pass('Test passed'); 
    }
    
  function testSubmissionProjectDependencies()
    {
    $rep = dirname(__FILE__)."/data/SubProjectExample";
    $file = "$rep/Project_1.xml";
    if(!$this->submission('SubProjectExample',$file))
      {
      return;
      }
    if(!$this->checkLog($this->logfilename))
      {
      return;
      }
    $this->pass('Test passed');
    }
    
  function testSubmissionSubProjectBuild()
    {
    $this->deleteLog($this->logfilename);
    $rep  = dirname(__FILE__)."/data/SubProjectExample";
    $file = "$rep/Build_1.xml";
    if(!$this->submission('SubProjectExample',$file))
      {
      return;
      }
    if(!$this->compareLog($this->logfilename,$rep."/cdash_1.log"))
      {
      return;
      }
    $this->pass('Test passed');  
    }
  
  function testSubmissionSubProjectTest()
    {
    $this->deleteLog($this->logfilename);
    $rep  = dirname(__FILE__)."/data/SubProjectExample";
    $file = "$rep/Test_1.xml";
    if(!$this->submission('SubProjectExample',$file))
      {
      return;
      }
    if(!$this->compareLog($this->logfilename,$rep."/cdash_2.log"))
      {
      return;
      }
    $this->pass('Test passed');  
    }
}

?>
