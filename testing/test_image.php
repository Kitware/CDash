<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

$path = dirname(__FILE__)."/..";
echo "PATH: " . $path . "\n";
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once('models/image.php');
#require_once('models/buildconfigureerror.php');
#require_once('models/buildconfigureerrordiff.php');
#require_once('models/label.php');
#require_once('cdash/pdo.php');
#require_once('cdash/common.php');

class BuildImageCase extends KWWebTestCase
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
   
  function testImage()
    {

    $db = pdo_connect($this->db->dbo->host, $this->db->dbo->user, $this->db->dbo->password);
    pdo_select_db("cdash4simpletest", $db);

    $image = new Image();
    
    //no id, no matching checksum
    $image->Exists();
    $image->Id = 1;

    //id, no matching checksum
    $image->Exists();

    //cover the various SetValue options
    $pathToImage = dirname(__FILE__)."/data/smile.gif";
    $image->SetValue("FILENAME", $pathToImage);
    $image->SetValue("EXTENSION", "gif");
    $image->SetValue("CHECKSUM", "12345");

    //call save twice to cover different execution paths
    $image->Save();
    $image->Save();

    $this->pass("Passed");
    return 0;
    }
}

?>
