<?php

// To be able to access files in this CDash installation:
//
$cdashpath = str_replace('\\', '/', dirname(dirname(__FILE__)));

require($cdashpath."/cdash/config.php");

$configure = array(
  // url of the cdash to test
  'urlwebsite'       => 'http://localhost/CDashTesting',
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

// Either:
//  (1) Set $web_report to false and run the tests through the php command
//      line tool:
//      $ php alltests.php
// Or:
//  (2) Set $web_report to true and run the tests in your web browser by
//      going to http://localhost/CDashTesting/testing/alltests.php
//
$web_report = false;

// DO NOT EDIT AFTER THIS LINE
$localConfig = dirname(__FILE__).'/config.test.local.php';
if ( file_exists($localConfig) )
  {
  include($localConfig);
  }
?>
