<?php
//
// After including this file, all subsequent require_once calls are
// relative to the top of the CDash source tree...
//
// All tests in this directory should include this file first as so:
//
//   require_once(dirname(__FILE__).'/cdash_selenium_test_case.php');
//

// To be able to access files in this CDash installation regardless
// of getcwd() value:
//
global $cdashpath;
$cdashpath = str_replace('\\', '/', dirname(dirname(dirname(__FILE__))));
set_include_path($cdashpath . PATH_SEPARATOR . get_include_path());

//echo "cdashpath='".$cdashpath."'\n";
//echo "get_include_path()='".get_include_path()."'\n";

require_once 'PHPUnit/Extensions/SeleniumTestCase.php';

class CDashSeleniumTestCase extends PHPUnit_Extensions_SeleniumTestCase
{
    protected $webPath;

    protected function browserSetUp()
    {
        global $argv;
        $this->setBrowser('*' . $argv[2]);
        global $configure;
        $this->setBrowserUrl($configure['webserver']);
        $this->webPath = $configure['webpath'];
    }

    public function sleepWaitingForElement($element)
    {
        for ($attempts = 0;; $attempts++) {
            if ($attempts >= 300) {
                $this->fail("timeout waiting for '$element'");
            }

            try {
                if ($this->isElementPresent($element)) {
                    break;
                }
            } catch (Exception $e) {
            }

            sleep(0.033);
        }
    }
}
