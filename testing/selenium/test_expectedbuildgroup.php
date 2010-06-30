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

  public function testExpectedBuildGroup()
  {
    $this->open($this->webPath."/index.php");
    $this->click("link=Login");
    $this->waitForPageToLoad("30000");
    $this->type("login", "simpletest@localhost");
    $this->type("passwd", "simpletest");
    $this->click("sent");
    $this->waitForPageToLoad("30000");
    $this->click("//tr[5]/td[2]/a[6]/img");
    $this->waitForPageToLoad("30000");
    $this->click("//div[@id='wizard']/ul/li[3]/a/span");
    $this->addSelection("movebuilds", "label=CDashTestingSite CDash-CTest-sameImage [Experimental] Experimental");
    $this->click("expectedMove");
    $this->select("groupSelection", "label=Experimental");
    $this->click("globalMove");
    $this->waitForPageToLoad("30000");
    $this->click("link=DASHBOARD");
    $this->waitForPageToLoad("30000");
    $this->click("//table[@id='project_5_15']/tbody[1]/tr[2]/td[2]/a[4]/img");
    $this->click("link=[remove this build]");
    $this->assertTrue((bool)preg_match('/^Are you sure you want to remove this build[\s\S]$/',$this->getConfirmation()));
    sleep(2);
    $this->click("//img[@alt='info']");
    sleep(2);
    $this->click("//img[@alt='info']");
    $this->click("//table[@id='project_5_15']/tbody[1]/tr[1]/td[2]/a[2]/img");
    $this->click("link=[mark as non expected]");
    $this->waitForPageToLoad("30000");
  }
}
?>
