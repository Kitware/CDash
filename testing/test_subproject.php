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
    }
   
  function testAccessToWebPageProjectTest()
    {
    $this->login();
    // first project necessary for testing
    $name = 'SubProjectExample';
    $description = 'Project SubProjectExample test for cdash testing';
    $this->createProject($name,$description);
    $content = $this->connect($this->url.'/index.php?project=SubProjectExample');
    if(!$content)
      {
      return;
      }
    $this->assertText('SubProjectExample Dashboard');
    $this->checkLog($this->logfilename);
    }
    
  function testSubmissionProjectDependencies()
    {
    $rep = dirname(__FILE__)."/data/SubProjectExample";
    $file = "$rep/Project_1.xml";
    if(!$this->submission('SubProjectExample',$file))
      {
      return;
      }
    $this->assertTrue(true,"Submission of $file has succeeded");
    $this->checkLog($this->logfilename);
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
    $this->assertTrue(true,"Submission of $file has succeeded");
    $this->compareLog($this->logfilename,$rep."/cdash_1.log");
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
    $this->assertTrue(true,"Submission of $file has succeeded");
    $this->compareLog($this->logfilename,$rep."/cdash_2.log");
    }
    
  // In case of the project does not exist yet
  function createProject($name,$description)
    {
    $this->clickLink('[Create new project]');
    $this->setField('name',$name);
    $this->setField('description',$description);
    $this->setField('public','1');
    $this->setField('emailBrokenSubmission','1');
    $this->setField('emailRedundantFailures','1');  
    $this->clickSubmitByName('Submit');
    return $this->clickLink('BACK');
    }
    
  function login()
    {
    $this->get($this->url);
    $this->clickLink('Login');
    $this->setField('login','simpletest@localhost');
    $this->setField('passwd','simpletest');
    return $this->clickSubmitByName('sent');
    }
    
  function submission($projectname,$file)
    {
    $url = $this->url."/submit.php?project=$projectname";
    $result = $this->uploadfile($url,$file);
    if($this->findString($result,'error')   ||
       $this->findString($result,'Warning') ||
       $this->findString($result,'Notice'))
      {
      $this->assertEqual($result,"\n");
      return false;
      }
    return true;
    }
    
  function uploadfile($url,$filename)
    {    
    $fp = fopen($filename, 'r');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_UPLOAD, 1);
    curl_setopt($ch, CURLOPT_INFILE, $fp);
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($filename));
    $page = curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    return $page;
    } 
}

?>
