<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class CompressedTestCase extends KWWebTestCase
{
  var $url = null;
  var $db  = null;
  
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
    }
    
  function testSubmissionCompressedTest()
    {
    $this->login();
    // first project necessary for testing
    $name = 'TestCompressionExample';
    $description = 'Project compression example';
    $this->createProject($name,$description);
    $content = $this->connect($this->url.'/index.php?project=TestCompressionExample');
    if(!$content)
      {
      return;
      }
      
    $file = dirname(__FILE__)."/data/CompressedTest.xml";
    if(!$this->submission('TestCompressionExample',$file))
      {
      return;
      }   
    }

  function testCheckCompressedTest()
    { 
    $content = $this->connect($this->url.'?project=TestCompressionExample&date=2009-12-18');
    if(!$content)
      {
      return;
      }
    
    $content = $this->analyse($this->clickLink('20'));
    $expected = 'kwsys.testHashSTL';
    if(!$content)
      {
      return;
      }
    elseif(!$this->findString($content,$expected))
      {
      $this->assertTrue(false,'The webpage does not match right the content exepected');
      return;
      }

    $content = $this->analyse($this->clickLink('kwsys.testHashSTL'));
    $content = $this->analyse($this->clickLink('Passed'));
    $expected = 'Found entry [world,2]';
    if(!$content)
      {
      return;
      }
    elseif(!$this->findString($content,$expected))
      {
      $this->assertTrue(false,'The webpage does not match right the content exepected');
      return;
      }  
    }
     
  function testCheckUnCompressedTest()
    { 
    $content = $this->connect($this->url.'?project=TestCompressionExample&date=2009-12-18');
    if(!$content)
      {
      return;
      }
     
    $content = $this->analyse($this->clickLink('20'));
    $expected = 'kwsys.testIOS';
    if(!$content)
      {
      return;
      }
    elseif(!$this->findString($content,$expected))
      {
      $this->assertTrue(false,'The webpage does not match right the content exepected');
      return;
      }
  
     $content = $this->analyse($this->clickLink('kwsys.testIOS'));
     $content = $this->analyse($this->clickLink('Passed'));
     $expected = 'IOS tests passed';
     if(!$content)
       {
       return;
       }
     elseif(!$this->findString($content,$expected))
       {
       $this->assertTrue(false,'The webpage does not match right the content exepected');
       return;
       }   
     } 
 }
?>
