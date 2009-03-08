<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class ProjectInDbTestCase extends KWWebTestCase
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
  
  function testCreateProject()
    {
    $content = $this->connect($this->url);
    if(!$content)
      {
      return;
      }
    $this->login();
    if(!$this->analyse($this->clickLink('[Create new project]')))
      {
      return;
      }
    
    $this->setField('name','ProjectTest');
    $this->setField('description','This is a project test for cdash');
    $this->setField('public','1');
    $this->clickSubmitByName('Submit');


    $query = "SELECT COUNT(*) FROM project";
    $result = $this->db->query($query);
    if( strcmp($this->db->getType(),"pgsql") == 0 && 
        $result[0]['count'] < 1)
      {
      $result = $result[0]['count'];  
      $errormsg = "The result of the query '$query' which is $result"; 
      $errormsg .= "is not the one expected: 1";
      $this->assertEqual($result,'1',$errormsg);
      return;
      }
    elseif(strcmp($this->db->getType(),"mysql") == 0 && 
           $result[0]['COUNT(*)'] < 1)
      {
      $result = $result[0]['COUNT(*)']; 
      $errormsg = "The result of the query '$query' which is $result"; 
      $errormsg .= "is not the one expected: 1";
      $this->assertEqual($result,'1',$errormsg);
      return;
      }
    $this->assertText('The project ProjectTest has been created successfully.');
    }
  
    function testProjectTestInDatabase()
    {
    $query = "SELECT name,description,public FROM project WHERE name = 'ProjectTest'";
    $result = $this->db->query($query);
    $nameexpected = "ProjectTest";
    $descriptionexpected = "This is a project test for cdash";
    $publicexpected = 1;
    $expected = array('name'        =>  $nameexpected,
                      'description' =>  $descriptionexpected,
                      'public'      =>  $publicexpected);
    $this->assertEqual($result[0],$expected);
    }
  
  function testIndexProjectTest()
    {
    $content = $this->get($this->url.'/index.php?project=ProjectTest');
    $this->assertTitle('CDash - ProjectTest');
    }
  
  function testEditProject()
    {
    $content = $this->connect($this->url);
    if(!$content)
      {
      return;
      }
    $this->login();
    $projectid = $this->db->query("SELECT id FROM project WHERE name = 'ProjectTest'");
    $content = $this->connect($this->url.'/createProject.php?projectid='.$projectid[0]['id']);
    if(!$content)
      {
      return;
      }
//  $this->analyse($this->clickLink('[Edit project]'));
//  echo $this->analyse($this->setField('projectSelection','ProjectTest'));
    $description = $this->_browser->getField('description');
    $public      = $this->_browser->getField('public');
    $descriptionExpected = 'This is a project test for cdash';
    if(strcmp($description,$descriptionExpected) != 0)
      {
      $this->assertEqual($description,$descriptionExpected);
      return;
      }
    if(strcmp($public,'1') != 0)
      {
      $this->assertEqual($public,'1');
      return;
      }
    $content  = $this->analyse($this->clickLink('CTestConfig.php'));
    $expected = '## This file should be placed in the root directory of your project.';
    if(!$this->findString($content,$expected))
      {
      $this->assertText($content,$expected);
      return;
      }
    $this->back();
    $this->post($this->getUrl(),array('Delete'=>true));
    $headerExpected = "window.location='user.php'";
    $content = $this->_browser->getContent();
    if($this->findString($content,$headerExpected))
      {
      $msg  = "We have well been redirecting to user.php\n";
      $msg .= "after to have deleted ProjectTest\n";
      $this->assertTrue(true,$msg);
      }
    else
      {
      $msg  = "We have not been redirecting to user.php\n";
      $msg .= "The deletion of ProjectTest failed\n";
      $this->assertTrue(false,$msg);
      }
    }

  function login()
    {
    $this->clickLink('Login');
    $this->setField('login','simpletest@localhost');
    $this->setField('passwd','simpletest');
    return $this->clickSubmitByName('sent');
    }
}

?>
