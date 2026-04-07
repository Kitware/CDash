<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use App\Utils\DatabaseCleanupUtils;
use Illuminate\Support\Facades\DB;

require_once __DIR__ . '/cdash_test_case.php';

class BuildDetailsTestCase extends KWWebTestCase
{
    protected $testDataDir;
    protected $testDataFiles;
    protected $builds;

    public function __construct()
    {
        parent::__construct();

        $this->testDataDir = __DIR__ . '/data/BuildDetails';
        $this->testDataFiles = ['Subbuild1.xml', 'Subbuild2.xml', 'Subbuild3.xml'];

        $this->createProject([
            'Name' => 'BuildDetails',
            'CvsViewerType' => null,
        ]);

        foreach ($this->testDataFiles as $testDataFile) {
            if (!$this->submission('BuildDetails', $this->testDataDir . '/' . $testDataFile)) {
                $this->fail('Failed to submit ' . $testDataFile);
                return 1;
            }
        }

        $this->builds = [];
        $builds = pdo_query("SELECT * FROM build WHERE name = 'linux' ORDER BY id");
        while ($build = pdo_fetch_array($builds)) {
            $this->builds[] = $build;
        }
    }

    public function __destruct()
    {
        foreach ($this->builds as $build) {
            DatabaseCleanupUtils::removeBuild($build['id']);
        }
    }

    // This will be specific to a test xml
    public function testViewTestReturnsProperFormat()
    {
        $testDataFile = $this->testDataDir . '/Insight_Experimental_Test.xml';
        if (!$this->submission('BuildDetails', $testDataFile)) {
            $this->fail('Failed to submit ' . $testDataFile);
            return 1;
        }

        $buildId = DB::select("SELECT id FROM build WHERE name = 'BuildDetails-Linux-g++-4.1-LesionSizingSandbox_Debug'")[0];
        $json = $this->get("{$this->url}/api/v1/viewTest.php?buildid={$buildId->id}");
        $actualResponse = json_decode($json);
        $expectedResponse = json_decode(
            file_get_contents($this->testDataDir . '/InsightExperimentalExample_Expected.json'));

        $this->assertEqual(count($actualResponse->tests), count($expectedResponse->tests));

        $this->assertEqual($actualResponse->numPassed, $expectedResponse->numPassed);
        $this->assertEqual($actualResponse->numFailed, $expectedResponse->numFailed);
        $this->assertEqual($actualResponse->numNotRun, $expectedResponse->numNotRun);
        $this->assertEqual($actualResponse->numTimeFailed, $expectedResponse->numTimeFailed);

        DatabaseCleanupUtils::removeBuild($buildId->id);
    }

    public function testViewTestReturnsProperFormatForParentBuilds()
    {
        $testDataFile = $this->testDataDir . '/Insight_Experimental_Test_Subbuild.xml';
        if (!$this->submission('BuildDetails', $testDataFile)) {
            $this->fail('Failed to submit ' . $testDataFile);
            return 1;
        }

        $buildId = DB::select("SELECT id FROM build WHERE name = 'BuildDetails-Linux-g++-4.1-LesionSizingSandbox_Debug-has-subbuild' AND parentid=-1")[0];

        $response = json_decode($this->get($this->url . '/api/v1/viewTest.php?buildid=' . $buildId->id));

        $this->assertTrue($response->parentBuild);

        foreach ($response->tests as $test) {
            $this->assertTrue(property_exists($test, 'subprojectid'));
            $this->assertTrue($test->subprojectname === 'some-subproject');
        }

        DatabaseCleanupUtils::removeBuild($buildId->id);
    }
}
