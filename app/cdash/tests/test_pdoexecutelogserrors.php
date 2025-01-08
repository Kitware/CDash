<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Database;

class PdoExecuteLogsErrorsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testPdoExecuteLogsErrors()
    {
        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare('SELECT notarealcolumn FROM build');
        pdo_execute($stmt);

        $log_contents = file_get_contents($this->logfilename);
        if (!str_contains($log_contents, 'notarealcolumn') || !str_contains($log_contents, 'ERROR')) {
            $this->fail('Invalid query failed to produce log output!');
            return 1;
        }
    }
}
