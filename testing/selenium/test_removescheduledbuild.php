<?php

require_once 'PHPUnit/Extensions/SeleniumTestCase.php';

class Example extends PHPUnit_Extensions_SeleniumTestCase
{
  protected function setUp()
  {
    $this->setBrowser("*chrome");
    $path = dirname(__FILE__)."/..";
    set_include_path(get_include_path() . PATH_SEPARATOR . $path);
    require('config.test.php');
    $this->setBrowserUrl($configure['webserver']);
    $this->webPath = $configure['webpath'];
  }

  public function testRemoveScheduledBuild()
  {
    $this->open($this->webPath."/index.php");
    $this->click("link=Login");
    $this->waitForPageToLoad("30000");
    $this->type("login", "simpletest@localhost");
    $this->type("passwd", "simpletest");
    $this->click("sent");
    $this->waitForPageToLoad("30000");
    $this->click("//img[@alt='edit schedule']");
    $this->waitForPageToLoad("30000");
    $this->click("link=MY CDASH");
    $this->waitForPageToLoad("30000");
    $this->click("//img[@alt='remove schedule']");
    $this->assertTrue((bool)preg_match('/^Are you sure you want to delete this schedule[\s\S]$/',$this->getConfirmation()));
  }
}
?>
