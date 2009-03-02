<?php
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_cdash_xml.php');
require_once('kwtest/kw_test_manager.php');
require_once('kwtest/kw_html_reporter.php');

require_once('config.test.php');
if(strcmp($CDASH_DB_NAME,'cdash4simpletest') != 0)
  {
  die("We cannot test cdash because test database is not cdash4simpletest\n");       
  }
if(!$web_report)
  {
  /*--- for testing inside the console and send to cdash ---*/
  $cdashreporter = new CDashXmlReporter($configure);
  $manager       = new CDashTestManager();
  $manager->setCDashServer($configure['cdash']);
  $manager->updateSVN($cdashreporter,$configure['svnroot']);
  $manager->setDatabase($db);
  $manager->configure($cdashreporter);
  $manager->setTestDirectory(dirname(__FILE__));
  $manager->runAllTests($cdashreporter);
  $manager->sendToCdash($cdashreporter,$configure['outputdirectory']);
  }
else
  {
  /*--- for testing inside our browser  ---*/
  $manager = new HtmlTestManager();
  $manager->setTestDirectory(getcwd());
  $manager->setDatabase($db);
  $manager->runAllTests(new KWHtmlReporter());
  }
?>
