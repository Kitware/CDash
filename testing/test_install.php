<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class InstallTestCase extends KWWebTestCase
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
    $this->databaseName = $db['name'];
    $this->db->setDb($this->databaseName);
    $this->db->setHost($db['host']);
    $this->db->setUser($db['login']);
    $this->db->setPassword($db['pwd']);
    $this->projectid = -1;
    }

  function testInstall()
    {
    //double check that it's the testing database before doing anything hasty...
    if($this->databaseName !== "cdash4simpletest")
      {
      $this->fail("can only test on a database named 'cdash4simpletest'");
      return 1;
      }
    //drop any old testing database before testing install
    $this->db->drop($this->databaseName);

    $this->get($this->url."/install.php");
    if(!$this->setFieldByName("admin_email", "simpletest@localhost"))
      {
      $this->fail("Set admin email returned false");
      return 1;
      }
    if(!$this->setFieldByName("admin_password", "simpletest"))
      {
      $this->fail("Set admin password returned false");
      return 1;
      }
    $this->clickSubmitByName("Submit");
    if(strpos($this->getBrowser()->getContentAsText(), "sucessfully created") === false)
      {
      $this->fail("'sucessfully created' not found when expected");
      return 1;
      }
    $this->pass("Passed");
    }
}
?>
