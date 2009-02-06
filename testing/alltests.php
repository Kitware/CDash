<?php
require_once('simpletest/web_tester.php');
require_once('simpletest/xml.php');
require_once('config.test.php');
if(strcmp($CDASH_DB_NAME,'cdash4simpletest') != 0)
  {
  die("Cannot test cdash because the database is not named cdash4simpletest\n");       
  }
  
$cdashreporter = new CDashXmlReporter($configure);
$cdashreporter->setCDashServer();
$cdashreporter->updateSVN();
//$cdashreporter->configure();
$test = &new GroupTest('Web site tests');
$testsFile = array(realpath('test_uninstall.php') => 'test_uninstall.php',
                   realpath('test_install.php') => 'test_install.php');
foreach(glob('test_*.php') as $file)
  {
  if(strcmp($file,'test_install.php') != 0 || strcmp($file,'test_uninstall.php') != 0)
    {
    $testsFile[realpath($file)] = $file;
    }
  }
foreach($testsFile as $file)
  {
  $test->addTestFile($file);
  }
$cdashreporter->paintTestCaseList($testsFile);
exit ($test->run($cdashreporter) ? 0 : 1);
//exit ($test->run(new HtmlReporter()) ? 0 : 1);
?>
