<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

$path = dirname(__FILE__)."/..";
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once('models/banner.php');
require_once('cdash/pdo.php');
require_once('cdash/common.php');

class BannerTestCase extends KWWebTestCase
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
   
  function testBanner()
    {
    $db = pdo_connect($this->db->dbo->host, $this->db->dbo->user, $this->db->dbo->password);
    pdo_select_db("cdash4simpletest", $db);

    $banner = new Banner();

    ob_start();
    $result = $banner->SetText("banner");
    $output = ob_get_contents();
    ob_end_clean();
    if($result)
      {
      $this->fail("SetText() should return false when ProjectId is -1");
      return 1;
      }
    if(strpos($output, "Banner::SetText(): no ProjectId specified") === false)
      {
      $this->fail("'no ProjectId specified' not found from SetText()");
      return 1;
      }

    //set a reasonable project id
    $banner->SetProjectId(1);

    //test insert
    $banner->SetText("banner");

    //test update
    $banner->SetText("banner");

    if($banner->GetText() != "banner")
      {
      $this->fail("GetText() should have returned 'banner'.");
      return 1;
      }

    $this->pass("Passed");
    return 0;
    }
}
?>
