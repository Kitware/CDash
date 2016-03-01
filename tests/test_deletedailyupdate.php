<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';

class DeleteDailyUpdateTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();

        global $db;
        $this->databaseName = $db['name'];

        $this->deleteLog($this->logfilename);
    }

    public function testDeleteDailyUpdate()
    {
        //double check that it's the testing database before doing anything hasty...
        if ($this->databaseName !== 'cdash4simpletest') {
            $this->fail("can only test on a database named 'cdash4simpletest'");
            return 1;
        }

        //remove the daily update entry for some projects so that subsequent tests
        //will cover dailyupdate.php more thoroughly

        $cvsID = get_project_id('InsightExample');
        if (!$query = pdo_query("DELETE FROM dailyupdate WHERE projectid='$cvsID'")) {
            $this->fail('pdo_query returned false');
            return 1;
        }
        $svnID = get_project_id('EmailProjectExample');
        if (!$query = pdo_query("DELETE FROM dailyupdate WHERE projectid='$svnID'")) {
            $this->fail('pdo_query returned false');
            return 1;
        }
        $gitID = get_project_id('PublicDashboard');
        if (!$query = pdo_query("DELETE FROM dailyupdate WHERE projectid='$gitID'")) {
            $this->fail('pdo_query returned false');
            return 1;
        }
        $this->pass('Passed');
    }
}
