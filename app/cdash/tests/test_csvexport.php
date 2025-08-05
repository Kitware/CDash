<?php

use CDash\Database;

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class ExportToCSVTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testExportToCSV(): void
    {
        // Get the ID of a build that has tests.
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT b.id FROM build b
            JOIN build2test b2t ON b2t.buildid=b.id
            WHERE b.name='Win32-MSVC2009' AND b2t.testname='curl'");
        $stmt->execute();
        $row = $stmt->fetch();
        $buildid = $row['id'];

        // Export this build's tests to CSV.
        $content = $this->connect($this->url . "/api/v1/viewTest.php?buildid=$buildid&export=csv");

        // Verify expected contents.
        $expected = 'DashboardSendTest,0.05,"Completed (OTHER_FAULT)",Failed';
        if (!str_contains($content, $expected)) {
            $this->fail('Expected content not found in CSV output');
        }
    }
}
