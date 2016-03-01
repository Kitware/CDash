<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class ImportBackupTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testImportBackupTest()
    {
        //make sure we can't visit the importBackup page while logged out
        $this->logout();
        $content = $this->get($this->url . "/importBackup.php");
        if (strpos($content, "<title>Login</title>") === false) {
            $this->fail("'<title>Login</title>' not found when expected");
            return 1;
        }

        //make sure we can visit the page while logged in
        $this->login();
        $content = $this->get($this->url . "/importBackup.php");
        if (strpos($content, "import xml") === false) {
            $this->fail("'import xml' not found when expected");
            return 1;
        }
        $content = $this->clickSubmitByName("Submit");

        //check for expected output
        if (strpos($content, "Import backup complete") === false) {
            $this->fail("'Import backup complete' not found on importBackup.php\n$content\n");
            return 1;
        }

        $this->pass("Passed");
        return 0;
    }
}
