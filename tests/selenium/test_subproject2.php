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

  public function testSubProject2()
  {
    $this->open($this->webPath."/index.php");
    $this->click("link=Login");
    $this->waitForPageToLoad("30000");
    $this->type("login", "simpletest@localhost");
    $this->type("passwd", "simpletest");
    $this->click("sent");
    $this->waitForPageToLoad("30000");
    $this->click("link=SubProjectExample");
    $this->waitForPageToLoad("30000");
    $this->click("link=ThreadPool");
    $this->waitForPageToLoad("30000");
    $this->click("link=DASHBOARD");
    $this->waitForPageToLoad("30000");
    $this->click("link=NOX");
    $this->waitForPageToLoad("30000");
    $this->click("link=SubProjects");
    $this->waitForPageToLoad("30000");
    $this->click("//form[@id='formnewgroup']/table/tbody/tr[2]/td[2]/table/tbody/tr[2]/td/a");
    $this->waitForPageToLoad("30000");
  }
}
?>
