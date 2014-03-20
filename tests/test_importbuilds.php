<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

require_once('cdash/pdo.php');
require_once('models/image.php');
require_once('models/testimage.php');
require_once('tests/kwtest/kw_unlink.php');

class ImportBuildsTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
    }

  function testImportBuilds()
    {
    $this->startCodeCoverage();

    global $configure;
    $dir = $configure['svnroot'];
    chdir($dir);
    $argv[0] = "importBuilds.php";
    $xmlDirectory = dirname(__FILE__)."/data/SubProjectExample";
    $argv[1] = $xmlDirectory;

    $checkFile = dirname(__FILE__)."/data/SubProjectExample/lastcheck";
    if(file_exists($checkFile))
      {
      cdash_testsuite_unlink($checkFile);
      }

    $argc = 1;
    ob_start();
    include('importBuilds.php');
    $output = ob_get_contents();
    ob_end_clean();
    if(strpos($output, "Usage: php") === false)
      {
      $this->fail("Expected output not found from importBuilds.php.\n$output\n");
      return 1;
      }

    $argc = 2;
    ob_start();
    include('importBuilds.php');
    $output = ob_get_contents();
    ob_end_clean();
    if(strpos($output, "Import backup complete. 3 files processed.") === false)
      {
      $this->fail("Expected output not found from importBuilds.php.\n$output\n");
      return 1;
      }

    ob_start();
    include('importBuilds.php');
    $output = ob_get_contents();
    ob_end_clean();
    if(strpos($output, "Import backup complete. 0 files processed.") === false)
      {
      $this->fail("Expected output not found from importBuilds.php.\n$output\n");
      return 1;
      }

    $this->pass("Passed");
    cdash_testsuite_unlink($checkFile);
    $this->deleteLog($this->logfilename);

    $this->stopCodeCoverage();

    return 0;
    }
}

?>
