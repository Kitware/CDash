<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

$path = dirname(__FILE__)."/..";
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once('cdash/common.php');
require_once('cdash/pdo.php');

class RecoverPasswordTestCase extends KWWebTestCase
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
    $this->logfilename = $cdashpath."/backup/cdash.log";
    $this->deleteLog($this->logfilename);
    }

  function testRecoverPassword()
    {
    $this->login();
    $this->get($this->url."/recoverPassword.php");
    if(strpos($this->getBrowser()->getContentAsText(), "your email address") === false)
      {
      $this->fail("'your email address' not found when expected.");
      return 1;
      }

    if(!$this->setFieldByName("email", "simpletest@localhost"))
      {
      $this->fail("Failed to set email");
      return 1;
      }
    if(!$this->clickSubmitByName("recover"))
      {
      $this->fail("clicking recover returned false");
      }

    //fix the password so others can still login...
    $db = pdo_connect($this->db->dbo->host, $this->db->dbo->user, $this->db->dbo->password);
    pdo_select_db("cdash4simpletest", $db);
    $md5pass = md5("simpletest");
    pdo_query("UPDATE ".qid("user")." SET password='$md5pass' WHERE email='simpletest@localhost'");
    add_last_sql_error("test_recoverpassword");
    $this->pass("Passed");
    }
}
?>
