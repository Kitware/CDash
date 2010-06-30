<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class EnableAsynchronousTestCase extends KWWebTestCase
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

  function testEnableAsynchronous()
    {
    $filename = dirname(__FILE__)."/../cdash/config.local.php";
    $handle = fopen($filename, "r");
    $contents = fread($handle, filesize($filename));
    fclose($handle);
    $handle = fopen($filename, "w");
    $lines = explode("\n", $contents);
    foreach($lines as $line)
      {
      if(strpos($line, "?>") !== false)
        {
        fwrite($handle, '$CDASH_ASYNCHRONOUS_SUBMISSION = true;');
        fwrite($handle, "\n?>");
        }
      else
        {
        fwrite($handle, "$line\n");
        }
      }
    fclose($handle);
    $this->pass("Passed");
    }
}
?>
