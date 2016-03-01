<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

class TestHistoryTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testTestHistory()
    {
        // Submit our testing data.
        $rep = dirname(__FILE__) . '/data/TestHistory';
        if (!$this->submission('InsightExample', "$rep/Test_1.xml")) {
            $this->fail('Failed to submit Test_1.xml');
            return 1;
        }
        if (!$this->submission('InsightExample', "$rep/Test_2.xml")) {
            $this->fail('Failed to submit Test_1.xml');
            return 1;
        }

        // Get the IDs for the two builds that we just created.
        $result = pdo_query("SELECT id FROM build WHERE name='TestHistory'");
        $num_builds = pdo_num_rows($result);
        if ($num_builds != 2) {
            $this->fail("Expected 2 builds, found $num_builds");
            return 1;
        }

        $buildids = array();
        while ($row = pdo_fetch_array($result)) {
            $buildids[] = $row['id'];
        }

        // Verify that testing history matches what we expect.
        $content = $this->connect($this->url . '/api/v1/viewTest.php?groupid=15&previous_builds=' . $buildids[1] . ',+' . $buildids[0] . '&projectid=5&tests%5B%5D=fails&tests%5B%5D=notrun&tests%5B%5D=flaky&tests%5B%5D=passes&time_begin=2015-11-16T01:00:00&time_end=2015-11-17T01:00:00');

        $jsonobj = json_decode($content, true);

        $success = true;
        $error_message = '';

        foreach ($jsonobj['tests'] as $test) {
            $history = $test['history'];
            if ($test['name'] == 'fails' && $history != 'Broken') {
                $error_message = "Expected history for test 'fails' to be 'Broken', instead found '$history'";
                $success = false;
            }
            if ($test['name'] == 'notrun' && $history != 'Inactive') {
                $error_message = "Expected history for test 'notrun' to be 'Inactive', instead found '$history'";
                $success = false;
            }
            if ($test['name'] == 'flaky' && $history != 'Unstable') {
                $error_message = "Expected history for test 'flaky' to be 'Unstable', instead found '$history'";
                $success = false;
            }
            if ($test['name'] == 'passes' && $history != 'Stable') {
                $error_message = "Expected history for test 'passes' to be 'Stable', instead found '$history'";
                $success = false;
            }
        }

        // Delete the builds that we created during this test.
        remove_build($buildids[0]);
        remove_build($buildids[1]);

        if ($success) {
            $this->pass('Test passed');
            return 0;
        } else {
            $this->fail($error_message);
            return 1;
        }
    }
}
