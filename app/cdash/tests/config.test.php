<?php
//
// The only includer of this file should be cdash_test_case.php.
// Do not include this file directly; include cdash_test_case.php instead.
// That file adds the root of the CDash source tree to the include path.
//
require_once 'config/config.php';
// TODO: determine if this file is still needed and if so update it to use Cdash/Config
global $configure;
$configure = array(
    // url of the cdash to test
    'urlwebsite' => 'http://localhost/CDash',
    // the directory to store the xml report for cdash
    'outputdirectory' => '/tmp',
    // the kind of test: Experimental, Nightly, Continuous
    'type' => 'Nightly',
    // the site of the test
    'site' => 'yellowstone.kitware',
    // the build name
    'buildname' => 'CDash-SVN-MySQL',
    // the cdash host
    'cdash' => 'http://www.cdash.org/CDash',
    // the local svn repository
    'svnroot' => '/var/www/CDashTesting'
);

// The following heuristic is used to guess whether we are running inside the
// web browser or via a php command line invocation...
//
// If the $_SERVER variable has these keys and their values are non-empty then
// we think we are running in the browser. Otherwise, we must be running from
// a php command line invocation.
//
global $inBrowser;
$inBrowser = false;

if (array_key_exists('SERVER_ADDR', $_SERVER) &&
    array_key_exists('SERVER_NAME', $_SERVER) &&
    array_key_exists('SERVER_PORT', $_SERVER)
) {
    if (($_SERVER['SERVER_ADDR'] != '') &&
        ($_SERVER['SERVER_NAME'] != '') &&
        ($_SERVER['SERVER_PORT'] != '')
    ) {
        $inBrowser = true;
    }
}

// Either:
//  (1) Set $web_report to false and run the tests through the php command
//      line tool:
//      $ php alltests.php
// Or:
//  (2) Set $web_report to true and run the tests in your web browser by
//      going to http://localhost/CDashTesting/testing/alltests.php
//
// The default value is based on the above guess regarding whether we are
// running in the browser or not:
//
global $web_report;
$web_report = $inBrowser;

// xampp on Windows XP yields 'WINNT' in the predefined PHP_OS variable
// (presumably the same for earlier revs of Windows NT family, and also
// presumably still valid on Vista or Windows7...)
//
global $isWindows;
$isWindows = false;
global $isMacOSX;
$isMacOSX = false;

if (PHP_OS == 'WINNT') {
    $isWindows = true;
} elseif (PHP_OS == 'Darwin') {
    $isMacOSX = true;
}

// DO NOT EDIT AFTER THIS LINE
$localConfig = dirname(__FILE__) . '/config.test.local.php';
if (file_exists($localConfig)) {
    include $localConfig;
}
