<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class ProjectInDbTestCase extends KWWebTestCase
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

  function testProjectTest4DbInDatabase()
    {
    $this->createProjectTest4Db();
    $query = "SELECT name,description,public FROM project WHERE name = 'ProjectTest4Db'";
    $result = $this->db->query($query);
    $nameexpected = "ProjectTest4Db";
    $descriptionexpected = "This is a project test for cdash";
    $publicexpected = 0;
    $expected = array('name'        =>  $nameexpected,
                      'description' =>  $descriptionexpected,
                      'public'      =>  $publicexpected);
    $this->assertEqual($result[0],$expected);
    }
  
  function testProjectInBuildGroup()
    {
    $query  = "SELECT id FROM project WHERE name = 'ProjectTest4Db'";
    $result = $this->db->query($query);
    $this->projecttestid = $result[0]['id'];
    $query  = "SELECT name,starttime,endtime,description FROM buildgroup WHERE projectid = '".$this->projecttestid."' order by name desc";
    $result = $this->db->query($query);
    $expected = array('0' => array('name'        => 'Nightly',
                                   'starttime'   => '1980-01-01 00:00:00',
                                   'endtime'     => '1980-01-01 00:00:00',
                                   'description' => 'Nightly builds'),
                      '1' => array('name'        => 'Experimental',
                                   'starttime'   => '1980-01-01 00:00:00',
                                   'endtime'     => '1980-01-01 00:00:00',
                                   'description' => 'Experimental builds'),
                      '2' => array('name'        => 'Continuous',
                                   'starttime'   => '1980-01-01 00:00:00',
                                   'endtime'     => '1980-01-01 00:00:00',
                                   'description' => 'Continuous builds'));
   $this->assertEqual($result,$expected);
   }
  
  function testProjectInBuildGroupPosition()
    {
    $query  = "SELECT COUNT(*) FROM buildgroupposition WHERE buildgroupid IN (SELECT id FROM buildgroup WHERE projectid=";
    $query .= $this->projecttestid.")";
    $result = $this->db->query($query);
    if(!strcmp($this->db->getType(),'pgsql'))
      {
      $this->assertEqual($result[0]['count'],3);
      }
    elseif(!strcmp($this->db->getType(),'mysql'))
      {
      $this->assertEqual($result[0]['COUNT(*)'],3);  
      }
    }
  
  function testUser2Project()
    {
    $query  = "SELECT userid, role, emailtype, emailcategory FROM user2project WHERE projectid=".$this->projecttestid;
    $result = $this->db->query($query);
    $expected = array('userid'        => 1,
                      'role'          => 2,
                      'emailtype'     => 3,
                      'emailcategory' => 62);
    $this->assertEqual($result[0],$expected);
    }
 
  
  function createProjectTest4Db()
    {
    $this->get($this->url);
    $this->clickLink('Login');
    $this->setField('login','simpletest@localhost');
    $this->setField('passwd','simpletest');
    $this->clickSubmitByName('sent');
    $this->clickLink('[Create new project]');
    $this->setField('name','ProjectTest4Db');
    $this->setField('description','This is a project test for cdash');
    $this->setField('public','0');
    return $this->clickSubmitByName('Submit');
    }
  
}
?>
