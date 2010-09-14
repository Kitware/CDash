<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

require_once('cdash/pdo.php');
require_once('models/image.php');
require_once('models/testimage.php');

class AutoRemoveBuildsTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
    }

  function testAutoRemoveBuilds()
    {
    $this->startCodeCoverage();

    global $configure;
    $dir = $configure['svnroot'];
    chdir($dir);
    $argv[0] = "autoRemoveBuilds.php";
    $argv[1] = "InsightExample";

    $argc = 1;
    ob_start();
    include('autoRemoveBuilds.php');
    $output = ob_get_contents();
    ob_end_clean();
    if(strpos($output, "Usage: php") === false)
      {
      $this->fail("Expected output not found from autoRemoveBuilds.php.\n$output\n");
      }

    $argc = 2;
    ob_start();
    include('autoRemoveBuilds.php');
    $output = ob_get_contents();
    ob_end_clean();

    if(strpos($output, "removing builds for InsightExample") === false)
      {
      $this->fail("Expected output not found from autoRemoveBuilds.php.\n$output\n");
      }
    else if(strpos($output, "removing old buildid:") === false)
      {
      $this->fail("Autoremovebuilds failed to remove old build by buildgroup setting.\n$output\n");
      }
    else
      {
      $this->pass("Passed");
      }
    $this->deleteLog($this->logfilename);

    $this->stopCodeCoverage();

    return 0;
    }
}

?>
