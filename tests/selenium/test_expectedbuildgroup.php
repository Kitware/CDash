<?php
//
// After including cdash_selenium_test_base.php, subsequent require_once calls
// are relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_selenium_test_case.php');

class Example extends CDashSeleniumTestCase
{
  protected function setUp()
  {
    $this->browserSetUp();
  }

  public function testExpectedBuildGroup()
  {
    $this->open($this->webPath."/index.php");
    $this->click("link=Login");
    $this->waitForPageToLoad("30000");
    $this->type("login", "simpletest@localhost");
    $this->type("passwd", "simpletest");
    $this->click("sent");
    $this->waitForPageToLoad("30000");

    // Mark the 1st build listed as expected: "tr[1]"
    // Then flip it and mark it as non expected.
    // This is just exercising the mark-as-expected / mark-as-non-expected
    // code in a smoke-test fashion.
    //
    $this->open($this->webPath."/index.php?project=InsightExample");
    $folder_button =
      "//table[@id='project_5_15']/tbody[1]/tr[1]/td[2]/div[3]/a[2]/img";
    $this->sleepWaitingForElement($folder_button);
    $this->click($folder_button);
    $this->sleepWaitingForElement("link=[mark as expected]");
    $this->click("link=[mark as expected]");
    $this->waitForPageToLoad("30000");
    $this->sleepWaitingForElement($folder_button);
    $this->click($folder_button);
    $this->sleepWaitingForElement("link=[mark as non expected]");
    $this->click("link=[mark as non expected]");
    $this->waitForPageToLoad("30000");

    // Remove the 2nd build listed: "tr[2]"...
    //
    $folder_button =
      "//table[@id='project_5_15']/tbody[1]/tr[2]/td[2]/div[3]/a[2]/img";
    $this->sleepWaitingForElement($folder_button);
    $this->click($folder_button);
    $this->sleepWaitingForElement("link=[remove this build]");
    $this->click("link=[remove this build]");
    $this->assertTrue((bool)preg_match('/^Are you sure you want to remove this build[\s\S]$/',$this->getConfirmation()));
  }
}
?>
