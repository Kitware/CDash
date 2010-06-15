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
$cdashreporter = new CDashXmlReporter($configure);
$manager       = new CDashTestManager();
$manager->setCDashServer($configure['cdash']);
$manager->setDatabase($db);
if($manager->configure($cdashreporter,$logfilename))
  {
  echo "Passed.\n";
  }
else
  {
  echo "Failed.\n";
  }
?>
