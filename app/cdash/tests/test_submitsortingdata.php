<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class SubmitSortingDataTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function submitFile($build, $type)
    {
        $rep = dirname(__FILE__) . '/data/SortingExample';
        $file = "$rep/$build" . '_' . "$type.xml";
        if (!$this->submission('InsightExample', $file)) {
            return false;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmitSortingData()
    {
        $builds = array('short', 'medium', 'long');
        $types = array('Build', 'Configure', 'Test', 'Update', 'Notes');
        foreach ($builds as $build) {
            foreach ($types as $type) {
                $this->submitFile($build, $type);
            }
        }
        $this->deleteLog($this->logfilename);
    }
}
