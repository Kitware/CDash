<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class ManageProjectRolesTestCase extends KWWebTestCase
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
    $this->projectid = -1;
    }

  function testRegisterUser()
    {
    if(!$this->connectAndGetProjectId())
      {
      return 1;
      }
    $this->get($this->url."/manageProjectRoles.php?projectid=$this->projectid#fragment-3");
    if(!$this->setFieldByName("registeruseremail", "simpleuser@localhost"))
      {
      $this->fail("Set user email returned false");
      return 1;
      }
    if(!$this->setFieldByName("registeruserfirstname", "Simple"))
      {
      $this->fail("Set user first name returned false");
      return 1;
      }
    if(!$this->setFieldByName("registeruserlastname", "User"))
      {
      $this->fail("Set user last name returned false");
      return 1;
      }
    if(!$this->setFieldByName("registerusercvslogin", "simpleuser"))
      {
      $this->fail("Set user CVS login returned false");
      return 1;
      }
    $this->clickSubmitByName("registerUser");
    if(strpos($this->getBrowser()->getContentAsText(), "simpleuser@localhost") === false)
      {
      $this->fail("'simpleuser@localhost' not found when expected");
      return 1;
      }
    $this->pass("Passed");
    }

  function connectAndGetProjectId()
    {
    $this->login();

    //get projectid for PublicDashboards
    $content = $this->connect($this->url.'/manageProjectRoles.php');
    $lines = explode("\n", $content);
    foreach($lines as $line)
      {
      if(strpos($line, "PublicDashboard") !== false)
        {
        preg_match('#<option value="([0-9]+)"#', $line, $matches);
        $this->projectid = $matches[1];
        break;
        }
      }
    if($this->projectid === -1)
      {
      $this->fail("Unable to find projectid for PublicDashboard");
      return false;
      }
    return true;
    }
}
?>
