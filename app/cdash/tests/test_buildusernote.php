<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';


require_once 'include/pdo.php';

use CDash\Model\BuildUserNote;

class BuildUserNoteTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testBuildUserNote()
    {
        $buildusernote = new BuildUserNote();
        $result = $buildusernote->Insert();
        if ($result) {
            $this->fail('Insert() should return false when BuildId is 0');
            return 1;
        }

        $log_contents = file_get_contents($this->logfilename);
        if (strpos($log_contents, 'BuildId is not set') === false) {
            $this->fail("'BuildId is not set' not found from Insert()");
            return 1;
        }

        $buildusernote->BuildId = 1;
        $result = $buildusernote->Insert();
        if ($result) {
            $this->fail('Insert() should return false when UserId is 0');
            return 1;
        }
        $log_contents = file_get_contents($this->logfilename);
        if (strpos($log_contents, 'UserId is not set') === false) {
            $this->fail("'UserId is not set' not found from Insert()");
            return 1;
        }

        $buildusernote->UserId = 1;
        if ($buildusernote->Insert()) {
            $this->fail('Insert() should return false but returned true');
            return 1;
        }

        $buildusernote->Note = 'test';
        if ($buildusernote->Insert()) {
            $this->fail('Insert() should return false but returned true');
            return 1;
        }

        $buildusernote->TimeStamp = '2010-10-10 10:10:10';
        if ($buildusernote->Insert()) {
            $this->fail('Insert() should return false but returned true');
            return 1;
        }

        $buildusernote->Status = 1;
        if (!$buildusernote->Insert()) {
            $this->fail("Insert() returned false when it should be true.\n");
            return 1;
        }
    }
}
