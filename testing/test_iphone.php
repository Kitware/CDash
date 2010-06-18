<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class iPhoneTestCase extends KWWebTestCase
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

  function testiPhone()
    {
    $this->get($this->url."/iphone/index.php");
    if(strpos($this->getBrowser()->getContentAsText(), "BatchmakeExample") === false)
      {
      $this->fail("'BatchmakeExample' not found when expected");
      return 1;
      }
    $this->get($this->url."/iphone/project.php?project=BatchmakeExample");
    if(strpos($this->getBrowser()->getContentAsText(), "Continuous") === false)
      {
      $this->fail("'Continuous' not found when expected");
      return 1;
      }
    $this->get($this->url."/iphone/user.php");
    if(strpos($this->getBrowser()->getContentAsText(), "Wrong login or password") === false)
      {
      $this->fail("'Wrong login or password' not found when expected");
      return 1;
      }
    $this->get($this->url."/iphone/buildsummary.php?buildid=1");
    if(strpos($this->getBrowser()->getContentAsText(), "Number of Updates") === false)
      {
      $this->fail("'Number of Updates' not found when expected");
      return 1;
      }
    $this->pass("Passed");
    }
}
?>
