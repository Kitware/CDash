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

  public function testEditSite()
  {
    $this->open($this->webPath."/index.php");
    $this->click("link=Login");
    $this->waitForPageToLoad("30000");
    $this->type("login", "simpletest@localhost");
    $this->type("passwd", "simpletest");
    $this->click("sent");
    $this->waitForPageToLoad("30000");
    $this->click("link=InsightExample");
    $this->waitForPageToLoad("30000");
    $this->click("link=CDashTestingSite");
    $this->waitForPageToLoad("30000");
    $this->click("link=exact:Are you maintaining this site? [claim this site]");
    $this->waitForPageToLoad("30000");
    $this->click("claimsite");
    $this->waitForPageToLoad("30000");
    $this->type("site_ip", "66.194.253.20");
    $this->click("geolocation");
    $this->waitForPageToLoad("30000");
    $this->type("site_description", "test description");
    $this->click("newdescription_revision");
    $this->click("updatesite");
    $this->waitForPageToLoad("30000");
    $this->click("unclaimsite");
    $this->waitForPageToLoad("30000");
  }
}
?>
