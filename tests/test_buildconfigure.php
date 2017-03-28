<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';
require_once 'models/buildconfigure.php';
require_once 'models/buildconfigureerror.php';
require_once 'models/buildconfigureerrordiff.php';
require_once 'models/label.php';

class BuildConfigureTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testBuildConfigure()
    {
        $configure = new BuildConfigure();
        $configure->BuildId = 'foo';
        if ($configure->Exists()) {
            $this->fail("configure with invalid buildid should not exist");
        }
        $log_contents = file_get_contents($this->logfilename);
        if (strpos($log_contents, 'BuildId is not numeric') === false) {
            $this->fail("'BuildId is not numeric' not found from Exists()");
        }

        $configure->BuildId = null;
        if ($configure->Exists()) {
            $this->fail("Configure exists with null buildid");
        }
        $log_contents = file_get_contents($this->logfilename);
        if (strpos($log_contents, 'BuildId is not numeric') === false) {
            $this->fail("'BuildId is not numeric' not found from Exists()");
        }

        $configure->BuildId = 1;
        $configure->Command = "cmake .";
        $configure->Log = "configure log";
        $configure->StartTime = gmdate(FMT_DATETIME);
        $configure->EndTime = gmdate(FMT_DATETIME);
        $configure->Status = 0;
        if (!$configure->Insert()) {
            $this->fail("configure->Insert returned false");
        }

        $error = new BuildConfigureError();
        $error->ConfigureId = 1;
        $error->Type = 1;
        $configure->AddError($error);

        $diff = new BuildConfigureErrorDiff();
        $diff->BuildId = 1;
        $configure->AddErrorDifference($diff);

        $label = new Label();
        $configure->AddLabel($label);


        $configure->BuildId = 2;
        // This is expected to return false because the configure row already exists.
        if ($configure->Insert()) {
            $this->fail("configure->Insert returned true");
        }

        if ($configure->Delete()) {
            $this->fail("configure->Delete returned true");
        }

        $configure->BuildId = 2;
        if (!$configure->Delete()) {
            $this->fail("configure->Delete returned false");
        }

        if ($configure->Exists()) {
            $this->fail("configure exists after delete");
        }

        $this->deleteLog($this->logfilename);
    }
}
