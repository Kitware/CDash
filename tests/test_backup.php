<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class BackupTestCase extends KWWebTestCase
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

  function testBackupTest()
    {
    //make sure we can't visit the backup page while logged out
    $this->logout();
    $content = $this->get($this->url."/backup.php");
    if(strpos($content, "<title>Login</title>") === false)
      {
      $this->fail("'<title>Login</title>' not found when expected");
      return 1;
      }

    //make sure we can visit the page while logged in
    $this->login();
    $content = $this->get($this->url."/backup.php");
    if(strpos($content, "backup directory") === false)
      {
      $this->fail("'backup directory' not found when expected");
      return 1;
      }

    //do the backup
    $content = $this->clickSubmitByName("Submit");

    //check for expected output
    if(strpos($content, "Backup complete") === false)
      {
      $this->fail("'Backup complete' not found on backup.php\n$content\n");
      return 1;
      }
    $this->pass("Passed");
    return 0;
    }
}
?>
