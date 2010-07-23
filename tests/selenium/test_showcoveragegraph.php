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

  public function testShowCoverageGraph()
  {
    $this->open($this->webPath."/index.php?project=InsightExample");
    $this->click("//table[@id='coveragetable']/tbody/tr/td[3]/a/b");
    $this->waitForPageToLoad("30000");
    $this->click("link=[Show coverage over time]");
    $this->click("link=[Zoom out]");
    $this->click("link=[Show coverage over time]");
  }
}
?>
