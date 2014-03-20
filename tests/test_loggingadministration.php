<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

require_once('cdash/common.php');
require_once('cdash/pdo.php');

class LoggingAdministrationTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
    $this->deleteLog($this->logfilename);
    }

  function testLoggingAdministration()
    {
    $handle = fopen($this->logfilename, "w");
    fwrite($handle, "test log file");
    fclose($handle);
    unset($handle);

    $this->login();

    $this->get($this->url."/loggingAdministration.php");
    if(strpos($this->getBrowser()->getContentAsText(), "test log file") === false)
      {
      $this->fail("'test log file' not found when expected.");
      return 1;
      }
    $this->pass("Passed");
    $this->deleteLog($this->logfilename);
    }
}
?>
