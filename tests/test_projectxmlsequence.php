<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'tests/kwtest/kw_unlink.php';

class ProjectXmlSequenceTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function submitFile($filename)
    {
        $file = dirname(__FILE__) . "/data/ProjectXmlSequence/$filename";

        if (!$this->submission('SubProjectExample', $file)) {
            return false;
        }

        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testProjectXmlSequence()
    {
        $filenames = array(
            'Trilinos_129273760744.57_Project.xml',
            'Trilinos_129273770005.15_Project.xml',
            'Trilinos_129273771745.07_Project.xml',
            'Trilinos_129273989317.97_Project.xml',
            'Trilinos_129274192973.58_Project.xml',
        );

        foreach ($filenames as $filename) {
            echo "submitting $filename\n";
            $this->submitFile($filename);
        }

        // Preserve the cdash.log for post-test-inspection:
        //
        //    global $cdashpath;
        //    $logcopy = $cdashpath."/backup/cdash.testProjectXmlSequence.log";
        //    echo "copying log file to [$logcopy]\n";
        //    if(file_exists($logcopy))
        //      {
        //      cdash_testsuite_unlink($logcopy);
        //      }
        //    copy($this->logfilename, $logcopy);

        $this->deleteLog($this->logfilename);
    }
}
