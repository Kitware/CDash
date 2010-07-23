<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

$path = dirname(__FILE__)."/..";
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once('cdash/common.php');
require_once('cdash/pdo.php');

class SVNInfoTestCase extends KWWebTestCase
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

  function testSVNInfo()
    {
    $this->login();
    $this->get($this->url."/svninfo.php");
    if(strpos($this->getBrowser()->getContentAsText(), "phpinfo") === false)
      {
      $this->fail("'phpinfo' not found when expected.");
      return 1;
      }
    $this->pass("Passed");
    }
}
?>
