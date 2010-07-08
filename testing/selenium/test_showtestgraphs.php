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

  public function testShowTestGraphs()
  {
    $this->open($this->webPath."/index.php?project=EmailProjectExample&date=2009-02-23");
    $this->click("//table[@id='project_3_7']/tbody[1]/tr[1]/td[12]/div/a");
    $this->waitForPageToLoad("30000");
    $this->click("link=Failed");
    $this->waitForPageToLoad("30000");
    $this->click("link=[Show Test Time Graph]");
    $this->click("link=[Zoom out]");
    $this->click("link=[Show Test Time Graph]");
    $this->click("link=[Show Failing/Passing Graph]");
    $this->click("link=[Zoom out]");
    $this->click("link=[Show Failing/Passing Graph]");
    $this->click("link=DashboardSendTest");
    $this->waitForPageToLoad("30000");
    $this->click("link=[Show Test Failure Trend]");
    $this->click("link=[Zoom out]");
    $this->click("link=[Show Test Failure Trend]");
  }
}
?>
