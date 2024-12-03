<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use Tests\Traits\CreatesSubmissions;

require_once dirname(__FILE__) . '/cdash_test_case.php';

class SubmitSortingDataTestCase extends KWWebTestCase
{
    use CreatesSubmissions;

    public function submitFile($build, $type)
    {
        $rep = dirname(__FILE__) . '/data/SortingExample';
        $file = "$rep/$build" . '_' . "$type.xml";
        $this->submitFiles('InsightExample', [$file]);
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmitSortingData()
    {
        $builds = ['short', 'medium', 'long'];
        $types = ['Build', 'Configure', 'Test', 'Update', 'Notes'];
        foreach ($builds as $build) {
            foreach ($types as $type) {
                $this->submitFile($build, $type);
            }
        }
        $this->deleteLog($this->logfilename);
    }
}
