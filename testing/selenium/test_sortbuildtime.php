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

  public function testSortBuildTime()
  {
    $this->open($this->webPath."/index.php?project=InsightExample&date=2010-07-07");
    $this->click("sort13sort_14");
    try {
        $this->assertEquals("2010-07-07T08:22:56 EDT", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[1]/td[15]"));
    } catch (PHPUnit_Framework_AssertionFailedError $e) {
        array_push($this->verificationErrors, $e->toString());
    }
    try {
        $this->assertEquals("2010-07-07T08:26:31 EDT", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[2]/td[15]"));
    } catch (PHPUnit_Framework_AssertionFailedError $e) {
        array_push($this->verificationErrors, $e->toString());
    }
    try {
        $this->assertEquals("2010-07-07T08:26:31 EDT", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[3]/td[15]"));
    } catch (PHPUnit_Framework_AssertionFailedError $e) {
        array_push($this->verificationErrors, $e->toString());
    }
    $this->click("sort13sort_14");
    try {
        $this->assertEquals("2010-07-07T08:26:31 EDT", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[1]/td[15]"));
    } catch (PHPUnit_Framework_AssertionFailedError $e) {
        array_push($this->verificationErrors, $e->toString());
    }
    try {
        $this->assertEquals("2010-07-07T08:26:31 EDT", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[2]/td[15]"));
    } catch (PHPUnit_Framework_AssertionFailedError $e) {
        array_push($this->verificationErrors, $e->toString());
    }
    try {
        $this->assertEquals("2010-07-07T08:22:56 EDT", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[3]/td[15]"));
    } catch (PHPUnit_Framework_AssertionFailedError $e) {
        array_push($this->verificationErrors, $e->toString());
    }
  }
}
?>
