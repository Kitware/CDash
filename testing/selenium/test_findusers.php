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
    $this->setBrowser("*chrome");
    $this->setBrowserUrl($configure['webserver']);
    $this->webPath = $configure['webpath'];
  }

  public function testFindUsers()
  {
    $this->open($this->webPath."/index.php");
    $this->click("link=Login");
    $this->waitForPageToLoad("30000");
    $this->type("login", "simpletest@localhost");
    $this->type("passwd", "simpletest");
    $this->click("sent");
    $this->waitForPageToLoad("30000");
    $this->click("//tr[5]/td[2]/a[7]/img");
    $this->waitForPageToLoad("30000");
    $this->click("//div[@id='wizard']/ul/li[2]/a/span");
    $this->type("search", "simple");
    $this->keyUp("search", "e");
    sleep(1);
    $this->select("//div[@id='newuser']/table/tbody/tr[1]/td[2]/font[1]/form/select", "label=Site maintainer");
    $this->select("//div[@id='newuser']/table/tbody/tr[1]/td[2]/font[1]/form/select", "label=Normal User");
    $this->type("//div[@id='newuser']/table/tbody/tr[1]/td[2]/font[1]/form/input[2]", "simple");
    $this->click("adduser");
    $this->waitForPageToLoad("30000");
    $this->click("//div[@id='fragment-1']/table/tbody/tr[2]/td[2]/table/tbody/tr[3]/td[6]/input[2]");
    $this->waitForPageToLoad("30000");
  }
}
?>
