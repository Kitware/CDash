<?php

require_once 'PHPUnit/Extensions/SeleniumTestCase.php';

class Example extends PHPUnit_Extensions_SeleniumTestCase
{
  protected function setUp()
  {
    $this->setBrowser("*chrome");
    $this->setBrowserUrl("http://elysium/");
  }

  public function testMyTestCase()
  {
    $this->open("/CDash/index.php");
    $this->click("link=Login");
    $this->waitForPageToLoad("30000");
    $this->type("login", "simpletest@localhost");
    $this->type("passwd", "simpletest");
    $this->click("sent");
    $this->waitForPageToLoad("30000");
    $this->click("//tr[5]/td[2]/a[4]/img");
    $this->waitForPageToLoad("30000");
    $this->click("//div[@id='wizard']/ul/li[3]/a/span");
    $this->type("cvsRepository[0]", ":pserver:anoncvs@itk.org:/cvsroot/Insight");
    $this->type("cvsUsername[0]", "anoncvs");
    $this->click("Update");
    $this->waitForPageToLoad("30000");
    $this->click("//div[@id='wizard']/ul/li[7]/a/span");
    $this->type("ctestTemplateScript", "client testing works");
    $this->click("Update");
    $this->waitForPageToLoad("30000");
    $this->click("link=MY CDASH");
    $this->waitForPageToLoad("30000");
    $this->click("//tr[5]/td[2]/a[3]/img");
    $this->waitForPageToLoad("30000");
    $this->click("submit");
    $this->waitForPageToLoad("30000");
  }
}
?>