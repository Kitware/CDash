<?php
// simpletest library
require_once('simpletest/kw_web_tester.php');
require_once('simpletest/db.php');

class installTestCase extends KWWebTestCase
{
  var $database  = null;
  
  function __construct()
    {
    parent::__construct();
    require('config.test.php');
    $this->url = $configure['urlwebsite'];
    if(!strcmp($CDASH_DB_NAME,'cdash4simpletest'))
      {
      $this->install($CDASH_DB_HOST,
                     $CDASH_DB_LOGIN,
                     $CDASH_DB_PASS,
                     $CDASH_DB_NAME,
                     $CDASH_DB_TYPE
                     );        
      }
    else
      {
      exit('We cannot test cdash because test database is not cdash4simpletest');
      }
    }
    
  function install($host,$user,$password,$dbname,$dbtype)
    {
    $this->database = new database($dbtype);
    $this->database->setHost($host);
    $this->database->setUser($user);
    $this->database->setPassword($password);
    $dbcreated = true;
    if(!$this->database->create($dbname))
      {
      $dbcreated = false;
      $msg = 'error mysql_query(CREATE DATABASE)';
      die("Error" . " File: " . __FILE__ . " on line: " . __LINE__.": $msg");
      return false;
      }
    if($dbcreated)
      {
      $sqlfile = "../sql/".$dbtype."/cdash.sql";
      $this->database->fillDb($sqlfile);
      }
    return true;
    }
    
  function testConnection()
    {
    $this->assertNotEqual($this->database->connectedToDb(),false);
    }
}
?>
