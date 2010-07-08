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

  public function testEmailSubscription()
  {
    $this->open($this->webPath."/index.php");
    $this->click("link=Login");
    $this->waitForPageToLoad("30000");
    $this->type("login", "simpletest@localhost");
    $this->type("passwd", "simpletest");
    $this->click("sent");
    $this->waitForPageToLoad("30000");
    $this->click("//tr[5]/td[2]/a[4]/img");
    $this->waitForPageToLoad("30000");
    $this->click("//div[@id='wizard']/ul/li[5]/a/span");
    $this->click("emailBrokenSubmission");
    $this->click("emailRedundantFailures");
    $this->click("emailAdministrator");
    $this->click("emailLowCoverage");
    $this->click("emailTestTimingChanged");
    $this->click("Update");
    $this->click("link=MY CDASH");
    $this->waitForPageToLoad("30000");
    $this->setSpeed("300");
    $this->click("//tr[5]/td[2]/a[1]/img");
    for ($second = 0; ; $second++) {
        if ($second >= 60) $this->fail("timeout");
        try {
            if ($this->isElementPresent("//div[@id='wizard']/ul/li[2]/a/span")) break;
        } catch (Exception $e) {}
        sleep(1);
    }
    $this->click("//div[@id='wizard']/ul/li[2]/a/span");
    $this->click("//div[@id='wizard']/ul/li[3]/a/span");
    $this->click("//div[@id='wizard']/ul/li[4]/a/span");
    $this->click("//div[@id='wizard']/ul/li[5]/a/span");
    $this->click("updatesubscription");
    for ($second = 0; ; $second++) {
        if ($second >= 60) $this->fail("timeout");
        try {
            if ($this->isElementPresent("//tr[5]/td[2]/a[1]/img")) break;
        } catch (Exception $e) {}
        sleep(1);
    }
    $this->click("//tr[5]/td[2]/a[1]/img");
    $this->click("unsubscribe");
    $this->assertTrue((bool)preg_match('/^Are you sure you want to unsubscribe[\s\S]$/',$this->getConfirmation()));
  }
}
?>
