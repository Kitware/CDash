<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'tests/kwtest/kw_test_manager.php';

if (strcmp($CDASH_DB_NAME, 'cdash4simpletest') != 0) {
    die("We cannot test cdash because test database is not cdash4simpletest\n");
}

$logfilename = $CDASH_LOG_FILE;

$manager = new HtmlTestManager();
$manager->removeLogAndBackupFiles($logfilename);
//$manager->setTestDirectory(getcwd());
$manager->setDatabase($db);
$reporter = new TextReporter();
$manager->runFileTest($reporter, $argv[1]);
