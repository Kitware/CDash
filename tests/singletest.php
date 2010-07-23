<?php
require_once('config.test.php');

require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_cdash_xml.php');
require_once('kwtest/kw_test_manager.php');
require_once('kwtest/kw_html_reporter.php');

if(strcmp($CDASH_DB_NAME,'cdash4simpletest') != 0)
  {
  die("We cannot test cdash because test database is not cdash4simpletest\n");       
  }

$logfilename = $cdashpath."/backup/cdash.log";
  
$manager = new HtmlTestManager();
$manager->removeLogAndBackupFiles($logfilename);
//$manager->setTestDirectory(getcwd());
$manager->setDatabase($db);
$manager->runFileTest(new TextReporter(), $argv[1] );
?>
