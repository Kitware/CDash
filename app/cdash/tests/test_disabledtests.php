<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once __DIR__ . '/cdash_test_case.php';

use App\Utils\DatabaseCleanupUtils;
use CDash\Database;

class DisabledTestsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testDisabledTests(): void
    {
        $this->deleteLog($this->logfilename);

        // Submit testing data.
        $rep = __DIR__ . '/data/DisabledTests';
        $file = "$rep/Test.xml";
        if (!$this->submission('EmailProjectExample', $file)) {
            return;
        }

        // Find the buildid we just created.
        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->query("SELECT id FROM build WHERE name = 'test_disabled'");
        $row = $stmt->fetch();
        $buildid = $row['id'];
        if ($buildid < 1) {
            $this->fail('No buildid found when expected');
        }

        // Verify email was sent for the missing exe but not for the disabled test.
        $log_contents = file_get_contents($this->logfilename);
        if (!str_contains($log_contents, 'ThisTestFails')) {
            $this->fail("No email sent for test 'ThisTestFails'");
        }
        if (str_contains($log_contents, 'ThisTestIsDisabled')) {
            $this->fail("Erroneous email sent for test 'ThisTestIsDisabled'");
        }

        DatabaseCleanupUtils::removeBuild($buildid);
    }
}
