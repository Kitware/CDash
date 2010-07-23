<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class CreatePublicDashboardTestCase extends KWWebTestCase
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

  function testCreatePublicDashboard()
    {
    $content = $this->connect($this->url);
    if(!$content)
      {
      return;
      }

    $this->login();
    if(!$this->analyse($this->clickLink('[Create new project]')))
      {
      return;
      }

    $this->setField('name', 'PublicDashboard');
    $this->setField('description', 'This project is for CMake dashboards run on this machine to submit to from their test suites... CMake dashboards on this machine should set CMAKE_TESTS_CDASH_SERVER to "'.$this->url.'"');
    $this->setField('public', '1');
    $this->clickSubmitByName('Submit');

    $this->checkErrors();
    $this->assertText('The project PublicDashboard has been created successfully.');
    }
}

?>
