<?php
// To be able to access to the parameter to the database
$cdashpath = str_replace("/testing","", dirname(__FILE__));
require(dirname(dirname(__FILE__))."/cdash/config.php");
// Everything should go here
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
// to run the test into the web browser (true or false (default value))
$web_report = false;
?>
