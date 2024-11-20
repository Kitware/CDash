<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use Tests\Traits\CreatesSubmissions;

require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'tests/kwtest/kw_unlink.php';

class ProjectXmlSequenceTestCase extends KWWebTestCase
{
    use CreatesSubmissions;

    public function submitFile($filename)
    {
        $file = dirname(__FILE__) . "/data/ProjectXmlSequence/$filename";

        $this->submitFiles('SubProjectExample', [$file]);

        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testProjectXmlSequence()
    {
        $filenames = [
            'Trilinos_129273760744.57_Project.xml',
            'Trilinos_129273770005.15_Project.xml',
            'Trilinos_129273771745.07_Project.xml',
            'Trilinos_129273989317.97_Project.xml',
            'Trilinos_129274192973.58_Project.xml',
        ];

        foreach ($filenames as $filename) {
            echo "submitting $filename\n";
            $this->submitFile($filename);
        }

        $this->deleteLog($this->logfilename);
    }
}
