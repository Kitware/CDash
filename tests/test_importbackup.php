<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class ImportBackupTestCase extends KWWebTestCase
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

  function testImportBackupTest()
    {
    //make sure we can't visit the importBackup page while logged out
    $this->logout();
    $content = $this->get($this->url."/importBackup.php");
    if(strpos($content, "<title>Login</title>") === false)
      {
      $this->fail("'<title>Login</title>' not found when expected");
      return 1;
      }

    //make sure we can visit the page while logged in
    $this->login();
    $content = $this->get($this->url."/importBackup.php");
    if(strpos($content, "import xml") === false)
      {
      $this->fail("'import xml' not found when expected");
      return 1;
      }
    $content = $this->clickSubmitByName("Submit");

    //check for expected output
    if(strpos($content, "Import backup complete") === false)
      {
      $this->fail("'Import backup complete' not found on importBackup.php\n$content\n");
      return 1;
      }

    $this->pass("Passed");
    return 0;
    }
}
?>
