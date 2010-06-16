<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class DisplayImageTestCase extends KWWebTestCase
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

  function testDisplayImage()
    {
    $content = $this->get($this->url."/displayImage.php");
    if(strpos($content, "Not a valid imgid!") === false)
      {
      $this->fail("'Not a valid imgid!' not found on displayImage.php");
      }
    if(!$this->get($this->url."/displayImage.php?imgid=1"))
      {
      $this->fail("display image failed");
      return 1;
      }
    $this->pass("Passed");
    }
}
?>
