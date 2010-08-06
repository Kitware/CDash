<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

require_once('models/project.php');
require_once('cdash/pdo.php');
require_once('cdash/common.php');

class ProjectModelTestCase extends KWWebTestCase
{
  var $url = null;
  var $db  = null;

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

  function testProjectModel()
    {
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
    $db = pdo_connect($this->db->dbo->host, $this->db->dbo->user, $this->db->dbo->password);
    pdo_select_db("cdash4simpletest", $db);

    $project = new Project();

    $this->assertTrue($project->GetNumberOfErrorConfigures(0,0) === false, "GetNumberOfErrorConfigures!=false");
    $this->assertTrue($project->GetNumberOfWarningConfigures(0,0) === false, "GetNumberOfWarningConfigures!=false");
    $this->assertTrue($project->GetNumberOfPassingConfigures(0,0) === false, "GetNumberOfPassingConfigures!=false");
    $this->assertTrue($project->GetNumberOfPassingTests(0,0) === false, "GetNumberOfPassingTests!=false");
    $this->assertTrue($project->GetNumberOfFailingTests(0,0) === false, "GetNumberOfFailingTests!=false");
    $this->assertTrue($project->GetNumberOfNotRunTests(0,0) === false, "GetNumberOfNotRunTests!=false");
    $this->assertTrue($project->SendEmailToAdmin(0,0) === false, "SendEmailToAdmin!=false");

    if(!($project->Delete() === false))
      {
      $this->fail("Project::Delete didn't return false for no id");
      return 1;
      }

    $project->Id = "27123";
    if(!($project->Exists() === false))
      {
      $this->fail("Project::Exists didn't return false for bogus id");
      return 1;
      }

    //Cover empty contents case
    $project->AddLogo('','');
    $project->Id = "2";
    $contents1 = file_get_contents('data/smile.gif', true);
    $contents2 = file_get_contents('data/smile2.gif', true);

    //Cover all execution paths
    $project->AddLogo($contents1, 'gif');
    $project->AddLogo($contents2, 'gif');
    $project->AddLogo($contents1, 'gif');

    @$project->SendEmailToAdmin('foo', 'hello world');
    if ( extension_loaded('xdebug'))
      {
      include('cdash/config.local.php');
      $data = xdebug_get_code_coverage();
      xdebug_stop_code_coverage();
      $file = $CDASH_COVERAGE_DIR . DIRECTORY_SEPARATOR .
        md5($_SERVER['SCRIPT_FILENAME']);
      file_put_contents(
        $file . '.' . md5(uniqid(rand(), TRUE)) . '.' . "test_projectmodel",
        serialize($data)
      );
      }
    return 0;
    }
}

?>
