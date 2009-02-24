<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');


class ProjectWebPageTestCase extends KWWebTestCase
{
  var $url           = null;
  var $db            = null;
  var $projecttestid = null;
  
  
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
   
  function testAccessToWebPageProjectTest()
    {
    $this->login();
    $this->createProjectTest4Db();
//    $this->clickLink('ProjectTest4Db');
    $content = $this->connect($this->url.'/index.php?project=ProjectTest4Db');
    if(!$content)
      {
      return;
      }
    $this->assertText('ProjectTest4Db Dashboard');
    }
    
  
  // In case of the project does not exist yet
  function createProjectTest4Db()
    {
    $this->clickLink('[Create new project]');
    $this->setField('name','ProjectTest4Db');
    $this->setField('description','This is a project test for cdash');
    $this->setField('public','0');
    $this->clickSubmit('Create Project');
    return $this->clickLink('BACK');
    }
    
  function login()
    {
    $this->get($this->url);
    $this->clickLink('Login');
    $this->setField('login','simpletest@localhost');
    $this->setField('passwd','simpletest');
    return $this->clickSubmit('Login >>');
    }
}
?>