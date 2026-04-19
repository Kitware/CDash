<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use App\Models\TestMeasurement;
use App\Utils\DatabaseCleanupUtils;
use Illuminate\Support\Facades\DB;

require_once __DIR__ . '/cdash_test_case.php';

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
            __DIR__ . '/data/ExternalLinksFromTests/Test.xml';
        if (!$this->submission('InsightExample', $file_to_submit)) {
            $this->fail("Failed to submit $file_to_submit");
            return 1;
        }

        // Get the IDs for the build and test that we just created.
        $result = DB::select(
            "SELECT id FROM build WHERE name = 'test_output_link'")[0];
        $buildid = $result->id;

        $result = DB::select(
            "SELECT id FROM build2test WHERE buildid=$buildid")[0];
        $buildtestid = $result->id;

        // Verify that our external link was parsed properly.
        $testMeasurement = TestMeasurement::where('testid', (int) $buildtestid)->firstOrFail();
        $this->assertEqual('Interesting website', $testMeasurement->name);
        $this->assertEqual('text/link', $testMeasurement->type);
        $this->assertEqual('http://www.google.com', $testMeasurement->value);

        // Delete the build that we created during this test.
        DatabaseCleanupUtils::removeBuild($buildid);

        $this->pass('Tests passed');
        return 0;
    }
}
