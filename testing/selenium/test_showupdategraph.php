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

  public function testShowUpdateGraph()
  {
    $this->open($this->webPath."/viewUpdate.php?buildid=1");
    $this->click("link=[Show Activity Graph]");
    $this->click("link=[Zoom out]");
    $this->click("link=[Show Activity Graph]");
    $this->click("link=DASHBOARD");
    $this->waitForPageToLoad("30000");
    $this->click("link=CURRENT");
    $this->waitForPageToLoad("30000");
    $this->click("//a[contains(text(),'1 file\n          changed')]");
    $this->waitForPageToLoad("30000");
    $this->click("link=[Show Activity Graph]");
    $this->click("link=[Zoom out]");
    $this->click("link=[Show Activity Graph]");
  }
}
?>
