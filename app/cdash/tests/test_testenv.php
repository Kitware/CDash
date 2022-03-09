<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class TestEnvTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testTestEnv()
    {
        $s = "CTEST_FULL_OUTPUT\n";
        $s = $s . "=================\n";
        $s = $s . "\n";

        $s = $s . "CDash/tests config values\n";
        $s = $s . "=========================\n";

        global $cdashpath;
        $s = $s . 'cdashpath=[' . print_r($cdashpath, true) . "]\n";

        global $configure;
        $s = $s . 'configure=[' . print_r($configure, true) . "]\n";

        global $inBrowser;
        $s = $s . 'inBrowser=[' . print_r($inBrowser, true) . "]\n";

        global $web_report;
        $s = $s . 'web_report=[' . print_r($web_report, true) . "]\n";

        global $isWindows;
        $s = $s . 'isWindows=[' . print_r($isWindows, true) . "]\n";

        global $isMacOSX;
        $s = $s . 'isMacOSX=[' . print_r($isMacOSX, true) . "]\n";

        $s = $s . "\n";

        $s = $s . "php superglobals ( see http://www.php.net/manual/en/language.variables.superglobals.php )\n";
        $s = $s . "================\n";

        $s = $s . '_SERVER=[' . print_r($_SERVER, true) . "]\n";
        $s = $s . "\n";

        $s = $s . '_GET=[' . print_r($_GET, true) . "]\n";
        $s = $s . "\n";

        $s = $s . '_POST=[' . print_r($_POST, true) . "]\n";
        $s = $s . "\n";

        $s = $s . '_FILES=[' . print_r($_FILES, true) . "]\n";
        $s = $s . "\n";

        $s = $s . '_COOKIE=[' . print_r($_COOKIE, true) . "]\n";
        $s = $s . "\n";

        if (isset($_SESSION)) {
            $s = $s . '_SESSION=[' . print_r($_SESSION, true) . "]\n";
            $s = $s . "\n";
        }

        $s = $s . '_REQUEST=[' . print_r($_REQUEST, true) . "]\n";
        $s = $s . "\n";

        $s = $s . '_ENV=[' . print_r($_ENV, true) . "]\n";
        $s = $s . "\n";

        if (isset($argc)) {
            $s = $s . 'argc=[' . print_r($argc, true) . "]\n";
            $s = $s . "\n";
        }

        if (isset($argv)) {
            $s = $s . 'argv=[' . print_r($argv, true) . "]\n";
            $s = $s . "\n";
        }

        if (isset($HTTP_RAW_POST_DATA)) {
            $s = $s . 'HTTP_RAW_POST_DATA=[' . print_r($HTTP_RAW_POST_DATA, true) . "]\n";
            $s = $s . "\n";
        }

        if (isset($http_response_header)) {
            $s = $s . 'http_response_header=[' . print_r($http_response_header, true) . "]\n";
            $s = $s . "\n";
        }

        if (isset($php_errormsg)) {
            $s = $s . 'php_errormsg=[' . print_r($php_errormsg, true) . "]\n";
            $s = $s . "\n";
        }

        $s = $s . 'GLOBALS=[' . print_r($GLOBALS, true) . "]\n";
        $s = $s . "\n";

        $s = $s . "\n";

        echo $s;

        $this->pass('TestEnv Passed');
    }
}
