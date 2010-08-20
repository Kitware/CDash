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

  public function testQueryTestsFilters()
  {
    $this->open($this->webPath."/index.php");
    $this->click("link=InsightExample");
    $this->waitForPageToLoad("30000");
    $this->click("link=Tests Query");
    $this->waitForPageToLoad("30000");
    $this->click("label_showfilters");
    $this->type("id_value1", "CDash");
    $this->click("apply");
    $this->waitForPageToLoad("30000");
    $this->type("id_value1", "simple");
    $this->click("apply");
    $this->waitForPageToLoad("30000");
    $this->select("id_field1", "label=Build Name");
    $this->type("id_value1", "CDash");
    $this->click("apply");
    $this->waitForPageToLoad("30000");
    $this->select("id_field1", "label=Build Time");
    $this->select("id_compare1", "label=is");
    $this->type("id_value1", "0");
    $this->click("apply");
    $this->waitForPageToLoad("30000");
    $this->click("clear");
    $this->waitForPageToLoad("30000");
    $this->select("id_field1", "label=Details");
    $this->select("id_compare1", "label=is not");
    $this->type("id_value1", "blah");
    $this->click("apply");
    $this->waitForPageToLoad("30000");
    $this->select("id_field1", "label=Site");
    $this->select("id_compare1", "label=contains");
    $this->type("id_value1", "CDash");
    $this->click("apply");
    $this->waitForPageToLoad("30000");
    $this->select("id_field1", "label=Status");
    $this->select("id_compare1", "label=is");
    $this->type("id_value1", "Passed");
    $this->click("apply");
    $this->waitForPageToLoad("30000");
    $this->click("add1");
    $this->type("id_value1", "Failed");
    $this->click("apply");
    $this->waitForPageToLoad("30000");
    $this->click("remove1");
    $this->select("id_field2", "label=Test Name");
    $this->select("id_compare2", "label=is not");
    $this->type("id_value2", "blah");
    $this->click("apply");
    $this->waitForPageToLoad("30000");
    $this->select("id_field1", "label=Time");
    $this->select("id_compare1", "label=is not");
    $this->type("id_value1", "6");
    $this->click("apply");
    $this->waitForPageToLoad("30000");
    $this->click("clear");
    $this->waitForPageToLoad("30000");
    $this->click("label_showfilters");
  }
}
?>
