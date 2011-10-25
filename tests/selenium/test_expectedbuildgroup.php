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
    $this->click("//tr[5]/td[2]/a[6]/img");
    $this->waitForPageToLoad("30000");
    $this->click("//div[@id='wizard']/ul/li[3]/a/span");
    $this->addSelection("movebuilds", "label=CDashTestingSite CDash-CTest-sameImage [Experimental] Experimental");
    $this->click("expectedMove");
    $this->select("groupSelection", "label=Experimental");
    $this->click("globalMove");
    $this->waitForPageToLoad("30000");
    $this->click("link=Dashboard");
    $this->waitForPageToLoad("30000");

    // Remove the 3rd build listed: "tr[3]"...
    //
    $folder_button =
      "//table[@id='project_5_15']/tbody[1]/tr[3]/td[2]/a[3]/img";
    $this->sleepWaitingForElement($folder_button);
    $this->click($folder_button);
    $this->sleepWaitingForElement("link=[remove this build]");
    $this->click("link=[remove this build]");
    $this->assertTrue((bool)preg_match('/^Are you sure you want to remove this build[\s\S]$/',$this->getConfirmation()));

    // After removal, it moves as an empty row "Expected build" to the
    // bottom of the list. Now it's "tr[6]" -- and, because it's not a real
    // build anymore, the folder link is now "a[2]" instead of a[3]...
    // Mark it as non expected, and it disappears...
    //
    $folder_button =
      "//table[@id='project_5_15']/tbody[1]/tr[6]/td[2]/a[2]/img";
    $this->sleepWaitingForElement($folder_button);
    $this->click($folder_button);
    $this->sleepWaitingForElement("link=[mark as non expected]");
    $this->click("link=[mark as non expected]");
  }
}
?>
