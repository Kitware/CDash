<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');
$path = dirname(__FILE__)."/..";
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once('cdash/common.php');
require_once('cdash/pdo.php');

class APITestCase extends KWWebTestCase
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

  function testAPI()
    {
    $db = pdo_connect($this->db->dbo->host, $this->db->dbo->user, $this->db->dbo->password);
    pdo_select_db("cdash4simpletest", $db);

    $projectList = $this->get($this->url."/api/?method=project&task=list");
    if(strpos($projectList, "InsightExample") === false)
      {
      $this->fail("'InsightExample' not found in list of projects");
      return 1;
      }
   
    $defects = $this->get($this->url."/api/?method=build&task=defects&project=EmailProjectExample");
    if(strpos($defects, "testfailed") === false)
      {
      $this->fail("Expected output not found when querying API for defects");
      return 1;
      }
    
    $checkinsdefects = $this->get($this->url."/api/?method=build&task=checkinsdefects&project=EmailProjectExample");
    if(strpos($checkinsdefects, '"testfailed":"3"') === false && strpos($checkinsdefects, '"testfailed":3') === false)
      {
      $this->fail("Expected output not found when querying API for checkinsdefects.");
      return 1;
      }

    $sitetestfailures = $this->get($this->url."/api/?method=build&task=sitetestfailures&project=EmailProjectExample&group=Nightly");
    if(strpos($sitetestfailures, "[]") === false)
      {
      $this->fail("Expected output not found when querying API for sitetestfailures");
      return 1;
      }

    $coveragedirectory = $this->get($this->url."/api/?method=coverage&task=directory&project=InsightExample");
    if(strpos($coveragedirectory, "[]") === false)
      {
      $this->fail("Expected output not found when querying API for coveragedirectory");
      return 1;
      }

    $userdefects = $this->get($this->url."/api/?method=user&task=defects&project=EmailProjectExample");
    if($userdefects != '{"user1kw":{"buildfixes":"6","buildfixesfiles":"1","testfixes":"2","testfixesfiles":"1"}}')
      {
      $this->fail("Expected output not found when querying API for userdefects");
      return 1;
      }

    $buildid = $this->get($this->url."/api/getbuildid.php?project=EmailProjectExample&siteid=3&name=Win32-MSVC2009&stamp=20090223-0100-Nightly");
    if($buildid !== '<?xml version="1.0" encoding="UTF-8"?><buildid>4</buildid>')
      {
      $this->fail("Expected output not found when querying API for buildid");
      return 1;
      }

    $userid = $this->get($this->url."/api/getuserid.php?author=user1kw&project=EmailProjectExample");
    if($userid !== '<?xml version="1.0" encoding="UTF-8"?><userid>2</userid>')
      {
      $this->fail("Expected output not found when querying API for userid");
      return 1;
      }

    include("cdash/version.php");
    $version = $this->get($this->url."/api/getversion.php");
    if($version !== $CDASH_VERSION)
      {
      $this->fail("Expected output not found when querying API for version");
      return 1;
      }

    $hasfile = $this->get($this->url."/api/hasfile.php");
    if($hasfile !== "md5sum not specified")
      {
      $this->fail("Expected output not found when querying API for hasfile");
      return 1;
      }
    $hasfile = $this->get($this->url."/api/hasfile.php?md5sums=1q2w3e4r5t");
    if(strpos($hasfile, "1q2w3e4r5t") === false)
      {
      $this->fail("Expected output not found when querying API for hasfile\n$hasfile\n");
      return 1;
      }
    $this->pass("Passed");
    }

}
?>
