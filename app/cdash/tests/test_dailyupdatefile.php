<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';


require_once 'include/pdo.php';

use CDash\Model\DailyUpdateFile;

class DailyUpdateFileTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testDailyUpdateFile()
    {
        $dailyupdatefile = new DailyUpdateFile();

        //no id, no matching database entry
        $dailyupdatefile->DailyUpdateId = 0;
        if ($dailyupdatefile->Exists()) {
            $this->fail('Exists() should return false when DailyUpdateId is 0');
            return 1;
        }

        ob_start();
        $dailyupdatefile->Save();
        $output = ob_get_contents();
        ob_end_clean();
        if ($output !== 'DailyUpdateFile::Save(): DailyUpdateId not set!') {
            $this->fail("'DailyUpdateId not set!' not found from Save()");
            return 1;
        }

        //no filename
        $dailyupdatefile->Filename = '';
        $dailyupdatefile->DailyUpdateId = 1;
        ob_start();
        $dailyupdatefile->Save();
        $output = ob_get_contents();
        ob_end_clean();
        if ($output !== 'DailyUpdateFile::Save(): Filename not set!') {
            $this->fail("'Filename not set!' not found from Save()");
            return 1;
        }

        //no matching database entry
        if ($dailyupdatefile->Exists()) {
            $this->fail('Exists() should return false before Save() has been called');
            return 1;
        }

        $dailyupdatefile->Filename = 'dailyupdatefile.log';
        ob_start();
        $dailyupdatefile->Save();
        $output = ob_get_contents();
        ob_end_clean();
        if ($output !== 'DailyUpdateFile::Save(): CheckinDate not set!') {
            $this->fail("'CheckinDate not set!' not found from Save()");
            return 1;
        }

        $dailyupdatefile->CheckinDate = '2010-10-10 10:10:10';

        //call save twice to cover different execution paths
        if (!$dailyupdatefile->Save()) {
            $this->fail('Save() returned false on call #1');
            return 1;
        }
        if (!$dailyupdatefile->Save()) {
            $this->fail('Save() returned false on call #2');
            return 1;
        }

        $this->pass('Passed');
        return 0;
    }
}
