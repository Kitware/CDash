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

  public function testBuildInfoGroup()
  {
    $this->open($this->webPath."/index.php?project=EmailProjectExample&date=2009-02-23");
    $this->click("//img[@alt='info']");
    sleep(1);
    $this->click("//img[@alt='info']");
  }
}
?>
