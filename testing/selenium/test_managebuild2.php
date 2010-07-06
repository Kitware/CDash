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

  public function testManageBuild2()
  {
    $this->open($this->webPath."/index.php");
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
    $this->addSelection("system_select", "index=0");
    $this->click("//option[@value='1']");
    $this->addSelection("compiler_select", "index=0");
    $this->click("//select[@id='compiler_select']/option");
    $this->addSelection("cmake_select", "index=0");
    $this->addSelection("library_select", "index=0");
    $this->click("//select[@id='library_select']/option");
    $this->addSelection("site_select", "index=0");
    $this->click("//select[@id='site_select']/option");
    $this->click("submit");
    $this->waitForPageToLoad("30000");
  }
}
?>
