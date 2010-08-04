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

  public function testAddBuildGroup()
  {
    $this->open($this->webPath."/index.php");
    $this->click("link=Login");
    $this->waitForPageToLoad("30000");
    $this->type("login", "simpletest@localhost");
    $this->type("passwd", "simpletest");
    $this->click("sent");
    $this->waitForPageToLoad("30000");
    $this->click("link=InsightExample");
    $this->waitForPageToLoad("30000");
    $this->setSpeed("1000");
    $this->click("//table[@id='project_5_15']/tbody[1]/tr[1]/td[2]/a[4]/img");
    $this->click("link=[mark as expected]");
    $this->waitForPageToLoad("30000");
    $this->click("//table[@id='project_5_15']/tbody[1]/tr[1]/td[2]/a[4]/img");
    $this->click("link=[mark as non expected]");
    $this->waitForPageToLoad("30000");
    $this->click("link=Log Out");
    $this->waitForPageToLoad("30000");
  }
}
?>
