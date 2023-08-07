<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/pdo.php';

class ExternalLinksFromTestsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testExternalLinksFromTests()
    {
        // Submit our testing data.
        $file_to_submit =
            dirname(__FILE__) . '/data/ExternalLinksFromTests/Test.xml';
        if (!$this->submission('InsightExample', $file_to_submit)) {
            $this->fail("Failed to submit $file_to_submit");
            return 1;
        }

        // Get the IDs for the build and test that we just created.
        $result = pdo_single_row_query(
            "SELECT id FROM build WHERE name = 'test_output_link'");
        $buildid = $result['id'];

        $result = pdo_single_row_query(
            "SELECT id FROM build2test WHERE buildid=$buildid");
        $buildtestid = $result['id'];

        // Use the API to verify that our external link was parsed properly.
        $this->get($this->url . "/api/v1/testDetails.php?buildtestid=$buildtestid");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $measurement = array_pop($jsonobj['test']['measurements']);

        $success = true;
        $error_msg = '';

        if ($measurement['name'] !== 'Interesting website') {
            $error_msg = "Expected name to be 'Interesting website', instead found " . $measurement['name'];
            $success = false;
        }

        if ($measurement['type'] !== 'text/link') {
            $error_msg = "Expected type to be 'text/link', instead found " . $measurement['type'];
            $success = false;
        }

        if ($measurement['value'] !== 'http://www.google.com') {
            $error_msg = "Expected value to be 'http://www.google.com', instead found " . $measurement['value'];
            $success = false;
        }

        // Delete the build that we created during this test.
        remove_build($buildid);

        if (!$success) {
            $this->fail($error_msg);
            return 1;
        }

        $this->pass('Tests passed');
        return 0;
    }
}
