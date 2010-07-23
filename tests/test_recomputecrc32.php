<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class RecomputeCRC32TestCase extends KWWebTestCase
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

  function testRecomputeCRC32Test()
    {
    $this->login();
    $content = $this->get($this->url."/cdash/recomputecrc32.php");
    if(strpos($content, "Done") === false)
      {
      $this->fail("'Done' not found when expected");
      return 1;
      }
    $this->pass("Passed");
    return 0;
    }
}
?>
