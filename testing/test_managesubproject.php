<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class ManageSubprojectTestCase extends KWWebTestCase
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

  function testManageSubproject()
    {
    $this->login();
    
    //get projectid for PublicDashboards
    $content = $this->connect($this->url.'/manageSubproject.php');
    $lines = explode("\n", $content);
    foreach($lines as $line)
      {
      if(strpos($line, "SubProjectExample") !== false)
        {
        preg_match('#<option value="([0-9]+)"#', $line, $matches);
        $this->projectid = $matches[1];
        break;
        }
      }

    $this->get($this->url."/manageSubproject.php?projectid=$this->projectid");
    if(strpos($this->getBrowser()->getContentAsText(), "Teuchos") === false)
      {
      $this->fail("'Teuchos' not found when expected");
      return 1;
      }
    
    $this->get($this->url."/manageSubproject.php?projectid=$this->projectid&delete=1");
    if(strpos($this->getBrowser()->getContentAsText(), "Teuchos") !== false)
      {
      $this->fail("'Teuchos' not found when expected");
      return 1;
      }
      
    if(!$this->setFieldByName("dependency_selection_2", "3"))
      {
      $this->fail("Set dependency_selection_2 returned false");
      return 1;
      }
    $this->clickSubmitByName("addDependency");
    if(strpos($this->getBrowser()->getContent(), "- RTOp") === false)
      {
      $this->fail("'- RTOp' not found when expected");
      return 1;
      }

    $this->pass("Passed");
    }
}
?>
