<?php
// To be able to access to the parameter to the database
$cdashpath = str_replace("/testing","", dirname(__FILE__));
require(realpath("$cdashpath/cdash/config.php"));
// Everything should go here
$configure = array(
  // url of the cdash to test
  'urlwebsite'       => 'http://localhost/CDash/CDashDev',
  // the directory to store the xml report for cdash
  'outputdirectory'  => '/tmp',
  // the kind of test: Experimental, Nightly, Continuous
  'type'             => 'Experimental',
  // the site of the test
  'site'             => 'tatouine.kitware.com',
  // the build name
  'buildname'        => 'CDash-SVN-MySQL',
  //'buildname'        => 'CDash-SVN-PgSQL',
  // the cdash host
  'cdash'            => 'http://localhost/CDash/CDash',
  // the local svn repository
  'svnroot'          => '/var/www/CDash/CDashDev'
  );
$db = array( 'host'   => $CDASH_DB_HOST,
             'login'  => $CDASH_DB_LOGIN,
             'pwd'    => $CDASH_DB_PASS,
             'name'   => $CDASH_DB_NAME,
             'type'   => $CDASH_DB_TYPE);
?>
