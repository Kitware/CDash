<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class ManageUsersTestCase extends KWWebTestCase
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

  function testManageUsersTest()
    {
    echo "Here's my url: $this->url\n\n";
    //make sure we can't visit the manageUsers page while logged out
    $this->logout();
    $content = $this->get($this->url."/manageUsers.php");
    if(strpos($content, "<title>Login</title>") === false)
      {
      $this->fail("'<title>Login</title>' not found when expected.");
      return 1;
      }

    //make sure we can visit the page while logged in
    $this->login();
    $content = $this->get($this->url."/manageUsers.php");
    if(strpos($content, "Add new user") === false)
      {
      $this->fail("'Add new user' not found when expected");
      return 1;
      }

    //add a new user
    if(!$this->SetFieldByName("fname", "Simple"))
      {
      $this->fail("SetFieldByName on first name returned false");
      return 1;
      }
    if(!$this->SetFieldByName("lname", "User2"))
      {
      $this->fail("SetFieldByName on last name returned false");
      return 1;
      }
    if(!$this->SetFieldByName("email", "simpleuser2@localhost"))
      {
      $this->fail("SetFieldByName on email returned false");
      return 1;
      }
    if(!$this->SetFieldByName("passwd", "simpleuser2"))
      {
      $this->fail("SetFieldByName on password returned false");
      return 1;
      }
    if(!$this->SetFieldByName("passwd2", "simpleuser2"))
      {
      $this->fail("SetFieldByName on password returned false");
      return 1;
      }
    if(!$this->SetFieldByName("institution", "testers"))
      {
      $this->fail("SetFieldByName on institution returned false");
      return 1;
      }
    $content = $this->clickSubmitByName("adduser");

    if(strpos($content, "added successfully") === false)
      {
      $this->fail("'added successfully' not found in output.");
      return 1;
      }

    $this->pass("Passed");
    return 0;
    }
}
?>
