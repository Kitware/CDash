<?php
// simpletest library
require_once('simpletest/kw_web_tester.php');
require_once('simpletest/db.php');

class uninstallTestCase extends KWWebTestCase
{
  var $database  = null;
  
  function __construct()
    {
    parent::__construct();
    require('config.test.php');
    $this->url = $configure['urlwebsite'];
    if(!strcmp($CDASH_DB_NAME,'cdash4simpletest'))
      {
      $this->uninstall($CDASH_DB_HOST,
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
    
  function uninstall($host,$user,$password,$dbname,$dbtype)
    {
     $this->database = new database($dbtype);
    $this->database->setHost($host);
    $this->database->setUser($user);
    $this->database->setPassword($password);
    return $this->database->drop($dbname);
    }
    
  function testUninstall()
    {
    $this->assertFalse($this->database->connectedToDb());
    }
    
}    
?>
