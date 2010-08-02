<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

$path = dirname(__FILE__)."/..";
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once('models/user.php');
require_once('cdash/pdo.php');
require_once('cdash/common.php');

class UserTestCase extends KWWebTestCase
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
   
  function testUser()
    {
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
    $db = pdo_connect($this->db->dbo->host, $this->db->dbo->user, $this->db->dbo->password);
    pdo_select_db("cdash4simpletest", $db);

    $user = new User();
    $user->Id = "non_numeric";

    if(!($user->IsAdmin() === false))
      {
      $this->fail("User::IsAdmin didn't return false for non-numeric user id");
      return 1;
      }

    $user->Id = "";
    $user->Email = "";
    
    if(!($user->GetName() === false))
      {
      $this->fail("User::GetName didn't return false when given no user id");
      return 1;
      }
    
    if(!($user->IsAdmin() === false))
      {
      $this->fail("User::Exists didn't return false for no user id and no email");
      return 1;
      }
    
    $user->Email = "simpletest@localhost";
    
    if($user->Exists() === false)
      {
      $this->fail("User::Exists returned false even though user exists");
      return 1;
      }
    
    $id = $user->GetIdFromEmail("simpletest@localhost");
    
    if($id === false)
      {
      $this->fail("User::GetIdFromEmail returned false for a valid user");
      return 1;
      }
      
    $user->Id = $id;
    $user->FirstName = "Foo";
    
    if($user->Exists() != true)
      {
      $this->fail("User::Exists failed given a valid user id");
      return 1;
      }
    
    $user->password = "simpletest";
    
    //Update save.
    $user->Save();
    
    return 0;
    }
}

?>
