<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

require_once('include/common.php');
require_once('include/pdo.php');

class LoggingAdministrationTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->deleteLog($this->logfilename);
    }

    public function testLoggingAdministration()
    {
        $handle = fopen($this->logfilename, "w");
        fwrite($handle, "test log file");
        fclose($handle);
        unset($handle);

        $this->login();

        $this->get($this->url."/loggingAdministration.php");
        global $CDASH_LOG_FILE;
        if ($CDASH_LOG_FILE !== false && strpos($this->getBrowser()->getContentAsText(), "test log file") === false) {
            $this->fail("'test log file' not found when expected.");
            return 1;
        }
        $this->pass("Passed");
        $this->deleteLog($this->logfilename);
    }
}
