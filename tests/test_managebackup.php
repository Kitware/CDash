<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class ManageBackupTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testManageBackup()
    {
        $this->login();
        $content = $this->get($this->url . '/manageBackup.php');
        if (strpos($content, 'Import') === false) {
            $this->fail("'Import' not found on manageBackup.php");
        }
        $this->pass('Passed');
    }
}
