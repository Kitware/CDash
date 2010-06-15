<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

$path = dirname(__FILE__)."/..";
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once('models/buildconfigure.php');
require_once('models/buildconfigureerror.php');
require_once('models/buildconfigureerrordiff.php');
require_once('models/label.php');
require_once('cdash/pdo.php');
require_once('cdash/common.php');

class BuildConfigureTestCase extends KWWebTestCase
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
   
  function testBuildConfigure()
    {
    $db = pdo_connect($this->db->dbo->host, $this->db->dbo->user, $this->db->dbo->password);
    pdo_select_db("cdash4simpletest", $db);

    $configure = new BuildConfigure();
    $configure->BuildId = "foo";
    ob_start();
    $configure->Exists();
    $output = ob_get_contents();
    ob_end_clean();
    if($output !== "BuildConfigure::Exists(): Buildid is not numeric")
      {
      $this->fail("'BuildId is not numeric' not found from Exists()");
      return 1;
      }

    $configure->BuildId = 1;
    $error = new BuildConfigureError();
    $error->BuildId = 1;
    $error->Type = 1;
    $configure->AddError($error);

    $diff = new BuildConfigureErrorDiff();
    $diff->BuildId = 1;
    $configure->AddErrorDifference($diff);

    $label = new Label();
    $configure->AddLabel($label);

    $configure->SetValue("STARTTIME", "Dec 31 23:58 EST");
    $configure->SetValue("ENDTIME", "Dec 31 23:59 EST");
    $configure->SetValue("COMMAND", "cmake .");
    $configure->SetValue("LOG", "Configuring done");
    $configure->SetValue("STATUS", 0);

    $configure->BuildId = false;
    ob_start();
    $configure->Exists();
    $output = ob_get_contents();
    ob_end_clean();
    if($output !== "BuildConfigure::Exists(): BuildId not set")
      {
      $this->fail("'BuildId not set' not found from Exists()");
      return 1;
      }
    $configure->BuildId = 1;
    $configure->Delete();

    $this->pass("Passed");
    return 0;
    }
}

?>
