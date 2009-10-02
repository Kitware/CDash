<?php

// To be able to access files in this CDash installation:
//
$cdashpath = str_replace('\\', '/', dirname(dirname(__FILE__)));

require($cdashpath."/cdash/config.php");


$configure = array(
  // url of the cdash to test
  'urlwebsite'       => 'http://192.168.0.100/CDash',
  // the directory to store the xml report for cdash
  'outputdirectory'  => '/tmp',
  // the kind of test: Experimental, Nightly, Continuous
  'type'             => 'Nightly',
  // the site of the test
  'site'             => 'yellowstone.kitware',
  // the build name
  'buildname'        => 'CDash-SVN-MySQL',
  // the cdash host
  'cdash'            => 'http://www.cdash.org/CDash',
  // the local svn repository
  'svnroot'          => '/var/www/CDashTesting'
  );


$db = array( 'host'   => $CDASH_DB_HOST,
             'login'  => $CDASH_DB_LOGIN,
             'pwd'    => $CDASH_DB_PASS,
             'name'   => $CDASH_DB_NAME,
             'type'   => $CDASH_DB_TYPE);


// The following heuristic is used to guess whether we are running inside the
// web browser or via a php command line invocation...
//
// If the $_SERVER variable has these keys and their values are non-empty then
// we think we are running in the browser. Otherwise, we must be running from
// a php command line invocation.
//
$inBrowser = true;

if (array_key_exists('SERVER_ADDR', $_SERVER) &&
    array_key_exists('SERVER_NAME', $_SERVER) &&
    array_key_exists('SERVER_PORT', $_SERVER)
   )
  {
  if (($_SERVER['SERVER_ADDR'] != '') &&
      ($_SERVER['SERVER_NAME'] != '') &&
      ($_SERVER['SERVER_PORT'] != '')
     )
    {
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
$web_report = $inBrowser;


// xampp on Windows XP yields 'WINNT' in the predefined PHP_OS variable
// (presumably the same for earlier revs of Windows NT family, and also
// presumably still valid on Vista or Windows7...)
//
$isWindows = false;
$isMacOSX = false;

if (PHP_OS == 'WINNT')
  {
  $isWindows = true;
  }
else if (PHP_OS == 'Darwin')
  {
  $isMacOSX = true;
  }
?>
