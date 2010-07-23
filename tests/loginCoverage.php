<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class LoginTestCase extends KWWebTestCase
{
  var $url           = null;
  var $db            = null;
  var $projecttestid = null;
  var $logfilename   = null;
  
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
    $this->logfilename = $cdashpath."/backup/cdash.log";
    }
   
  function testLogin()
    {
    $content = $this->login('baduser@badhost.com');
    $this->assertText("This user doesn't exist.");
    
    $content = $this->login('simpletest@localhost', 'badpasswd');
    $this->assertText("Wrong email or password.");

    $content = $this->logout();
    $this->assertText("Login");
    
    $this->pass('Test passed'); 
    }
}

?>
