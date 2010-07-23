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

  public function testBuildSummary()
  {
    $this->open($this->webPath."/index.php");
    $this->click("link=Login");
    $this->waitForPageToLoad("30000");
    $this->type("login", "simpletest@localhost");
    $this->type("passwd", "simpletest");
    $this->click("sent");
    $this->waitForPageToLoad("30000");
    $this->open($this->webPath."/index.php?project=EmailProjectExample&date=2009-02-23");
    $this->waitForPageToLoad("30000");
    $this->click("link=Win32-MSVC2009");
    $this->waitForPageToLoad("30000");
    $this->click("link=[Show Build History]");
    $this->click("link=[Show Build History]");
    $this->click("link=[Show Build Graphs]");
    $this->click("link=[Zoom out]");
    $this->click("link=[Show Build Graphs]");
    $this->click("link=[Add a Note to this Build]");
    for ($second = 0; ; $second++) {
        if ($second >= 60) $this->fail("timeout");
        try {
            if ($this->isElementPresent("TextNote")) break;
        } catch (Exception $e) {}
        sleep(1);
    }
    $this->type("TextNote", "just a simple note");
    $this->click("AddNote");
    $this->waitForPageToLoad("30000");
  }
}
?>
