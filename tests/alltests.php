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
  
if(!$web_report)
  {
  /*--- for testing inside the console and send to cdash ---*/
  $cdashreporter = new CDashXmlReporter($configure);
  $manager       = new CDashTestManager();
  $manager->setCDashServer($configure['cdash']);
  if(!$manager->updateSVN($cdashreporter,$configure['svnroot'],$configure['type']))
    {
    return;
    }
  $manager->setDatabase($db);
  $manager->configure($cdashreporter,$logfilename);
  $manager->setTestDirectory(dirname(__FILE__));
  $manager->runAllTests($cdashreporter);
  $manager->getErrorFromServer($logfilename,$cdashreporter);
  $manager->sendToCdash($cdashreporter,$configure['outputdirectory']);
  }
else
  {
  /*--- for testing inside our browser  ---*/
  $manager = new HtmlTestManager();
  $manager->removeLogAndBackupFiles($logfilename);
  $manager->setTestDirectory(getcwd());
  $manager->setDatabase($db);
  $manager->runAllTests(new KWHtmlReporter());
  }
?>
