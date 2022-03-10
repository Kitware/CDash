<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use CDash\Config;

require_once dirname(__FILE__) . '/../../../vendor/autoload.php';
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'tests/kwtest/kw_test_manager.php';

$env_contents = file_get_contents(dirname(__FILE__) . '/../../../.env');
if (strpos($env_contents, 'DB_DATABASE=cdash4simpletest') === false) {
    echo "We cannot test cdash because test database is not cdash4simpletest\n";
    die(1);
}

$manager = new HtmlTestManager();
$manager->removeLogAndBackupFiles(\CDash\Config::getInstance()->get('CDASH_LOG_FILE'));
$reporter = new TextReporter();
$manager->runFileTest($reporter, $argv[1]);
