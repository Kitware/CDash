<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class ManageBackupTestCase extends KWWebTestCase
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

  function testManageBackup()
    {
    $this->login();
    $content = $this->get($this->url."/manageBackup.php");
    if(strpos($content, "Import") === false)
      {
      $this->fail("'Import' not found on manageBackup.php");
      }
    $this->pass("Passed");
    }
}
?>
