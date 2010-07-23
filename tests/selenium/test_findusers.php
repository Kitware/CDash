<?php

require_once 'PHPUnit/Extensions/SeleniumTestCase.php';

class Example extends PHPUnit_Extensions_SeleniumTestCase
{
  protected function setUp()
  {
    global $argv;
    $this->setBrowser("*" . $argv[2]);
    $path = dirname(__FILE__)."/..";
    set_include_path(get_include_path() . PATH_SEPARATOR . $path);
    require('config.test.php');
    $this->setBrowserUrl($configure['webserver']);
    $this->webPath = $configure['webpath'];
  }

  public function testFindUsers()
  {
    $this->open($this->webPath."/index.php");
    $this->click("link=Login");
    $this->waitForPageToLoad("30000");
    $this->type("login", "simpletest@localhost");
    $this->type("passwd", "simpletest");
    $this->click("sent");
    $this->waitForPageToLoad("30000");
    $this->click("link=[Manage users]");
    $this->waitForPageToLoad("30000");
    $this->type("search", "simple");
    $this->keyUp("search", "e");
    sleep(1);
    $this->click("makeadmin");
    $this->waitForPageToLoad("30000");
    $this->type("search", "simpl");
    $this->keyUp("search", "l");
    sleep(1);
    $this->click("makenormaluser");
    $this->waitForPageToLoad("30000");
  }
}
?>
