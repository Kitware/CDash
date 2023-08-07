<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/pdo.php';

class FilterTestLabelsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testFilterLabels()
    {
        // Submit our test data.
        $rep = dirname(__FILE__) . '/data/FilterTestLabels';
        if (!$this->submission('EmailProjectExample', "$rep/Build_1.xml")) {
            $this->fail('failed to submit Update_1.xml');
            return 1;
        }
        if (!$this->submission('EmailProjectExample', "$rep/Test_1.xml")) {
            $this->fail('failed to submit Update_2.xml');
            return 1;
        }

        // Turn this option on.
        pdo_query("UPDATE project SET sharelabelfilters=1
                WHERE name='EmailProjectExample'");

        // Get the buildid that we just created so we can delete it later.
        $buildids = array();
        $buildid_results = pdo_query(
            "SELECT id FROM build WHERE name='labeltest_build'");
        while ($buildid_array = pdo_fetch_array($buildid_results)) {
            $buildids[] = $buildid_array['id'];
        }

        if (count($buildids) != 1) {
            foreach ($buildids as $id) {
                remove_build($id);
            }
            $this->fail('Expected 1 build, found ' . count($buildids));
            return 1;
        }
        $buildid = $buildids[0];

        $success = true;
        $error_msg = "";

        // If any of these checks fail, the build still needs to be deleted
        try {
            // First, check number of tests with no filters
            $content = $this->connect($this->url . '/api/v1/index.php?project=EmailProjectExample&date=2011-07-23');
            $jsonobj = json_decode($content, true);
            $buildgroup = array_pop($jsonobj['buildgroups']);

            if ($buildgroup['numtestnotrun'] != 23) {
                throw new Exception('Expected numtestnotrun=23, found numtestnotrun= ' . qnum($buildgroup['numtestnotrun']));
            }
            if ($buildgroup['numtestfail'] != 2) {
                throw new Exception('Expected numtestfail=2, found numtestfail= ' . qnum($buildgroup['numtestfail']));
            }
            if ($buildgroup['numtestpass'] != 5) {
                throw new Exception('Expected numtestpass=5, found numtestpass= ' . qnum($buildgroup['numtestpass']));
            }

            // Now, add a filter
            $content = $this->connect($this->url . '/api/v1/index.php?project=EmailProjectExample&date=2011-07-23&filtercount=1&showfilters=1&field1=label&compare1=63&value1=SM');
            $jsonobj = json_decode($content, true);
            $buildgroup = array_pop($jsonobj['buildgroups']);

            if ($buildgroup['numtestnotrun'] != 4) {
                throw new Exception('Expected numtestnotrun=4, found numtestnotrun= ' . qnum($buildgroup['numtestnotrun']));
            }
            if ($buildgroup['numtestfail'] != 0) {
                throw new Exception('Expected numtestfail=0, found numtestfail= ' . qnum($buildgroup['numtestfail']));
            }
            if ($buildgroup['numtestpass'] != 0) {
                throw new Exception('Expected numtestpass=0, found numtestpass= ' . qnum($buildgroup['numtestpass']));
            }
        } catch (Exception $e) {
            $success = false;
            $error_msg = $e->getMessage();
        }

        // Delete the build
        remove_build($buildid);

        // Turn the option back off.
        pdo_query("UPDATE project SET sharelabelfilters=0
                WHERE name='EmailProjectExample'");

        if (!$success) {
            $this->fail($error_msg);
            return 1;
        }

        $this->pass('Tests passed');
        return 0;
    }
}
