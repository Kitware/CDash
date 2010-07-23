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

  public function testSetupRepositories()
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
    $this->click("//div[@id='wizard']/ul/li[3]/a/span");
    $this->type("cvsRepository[0]", ":pserver:anoncvs@itk.org:/cvsroot/Insight");
    $this->type("cvsUsername[0]", "anoncvs");
    $this->click("Update");
    $this->waitForPageToLoad("30000");
    $this->click("//div[@id='wizard']/ul/li[7]/a/span");
    $this->type("ctestTemplateScript", "client testing works");
    $this->click("Update");
    $this->waitForPageToLoad("30000");
    $this->select("projectSelection", "label=PublicDashboard");
    $this->waitForPageToLoad("30000");
    $this->click("//div[@id='wizard']/ul/li[3]/a/span");
    $this->type("cvsRepository[0]", "git://cmake.org/cmake.git");
    $this->select("cvsviewertype", "label=GitWeb");
    $this->type("cvsURL", "cmake.org/gitweb?p=cmake.git");
    $this->click("Update");
    $this->setSpeed("500");
    $this->select("projectSelection", "label=EmailProjectExample");
    $this->click("//div[@id='wizard']/ul/li[3]/a/span");
    $this->type("cvsRepository[0]", "https://www.kitware.com/svn/CDash/trunk");
    $this->type("cvsURL", "https://www.kitware.com/svn/CDash/trunk");
    $this->select("cvsviewertype", "label=WebSVN");
    $this->click("Update");
  }
}
?>
