<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';



class BuildDetailsTestCase extends KWWebTestCase
{
    protected $testDataDir;
    protected $testDataFiles;
    protected $builds;

    public function __construct()
    {
        parent::__construct();

        $this->testDataDir = dirname(__FILE__) . '/data/BuildDetails';
        $this->testDataFiles = array('Subbuild1.xml', 'Subbuild2.xml', 'Subbuild3.xml');

        $this->createProject(['Name' => 'BuildDetails']);

        foreach ($this->testDataFiles as $testDataFile) {
            if (!$this->submission('BuildDetails', $this->testDataDir . '/' . $testDataFile)) {
                $this->fail('Failed to submit ' . $testDataFile);
                return 1;
            }
        }

        $this->builds = array();
        $builds = pdo_query("SELECT * FROM build WHERE name = 'linux' ORDER BY id");
        while ($build = pdo_fetch_array($builds)) {
            $this->builds[] = $build;
        }
    }

    public function __destruct()
    {
        foreach ($this->builds as $build) {
            remove_build($build['id']);
        }
    }

    public function testViewBuildErrorReturnsErrorForNonexistentBuild()
    {
        $response = $this->get($this->url . '/api/v1/viewBuildError.php?buildid=80000001');
        $response = json_decode($response);

        $this->assertTrue(strlen($response->error) > 0);
    }

    public function testViewBuildErrorReturnsErrorForInvalidBuildId()
    {
        $response = $this->get($this->url . '/api/v1/viewBuildError.php?buildid=im-non-numeric');
        $response = json_decode($response);

        $this->assertTrue(strlen($response->error) > 0);
    }

    public function testViewBuildErrorReturnsArrayOfErrorsOnChildBuilds()
    {
        foreach ($this->builds as $build) {
            if ($build['parentid'] != -1) {
                $response = json_decode($this->get(
                    $this->url . '/api/v1/viewBuildError.php?buildid=' . $build['id']));

                $this->assertTrue(is_array($response->errors));
            }
        }
    }

    public function testViewBuildErrorReturnsProperFormat()
    {
        // This test is specific to Subbuild3.xml
        $build = $this->builds[3];
        $build_response = json_decode($this->get(
            $this->url . '/api/v1/viewBuildError.php?buildid=' . $build['id']));

        $this->assertTrue(count($build_response->errors) === 3);

        $expectedErrors = json_decode(file_get_contents($this->testDataDir . '/' . 'Subbuild3_errors.json'));

        for ($i=0; $i<count($expectedErrors); $i++) {
            $this->assertEqual($build_response->errors[$i], $expectedErrors[$i]);
        }
    }

    public function testViewBuildErrorReturnsProperFormatForParentBuilds()
    {
        $build = $this->builds[0];
        $build_response = json_decode($this->get(
            $this->url . '/api/v1/viewBuildError.php?buildid=' . $build['id']));

        $this->assertTrue($build_response->numSubprojects === 1);
        $this->assertTrue(count($build_response->errors) == 3);

        foreach ($build_response->errors as $error) {
            $this->assertTrue($error->subprojectname == 'my_subproject');
        }
    }

    // This will be specific to a test xml
    public function testViewTestReturnsProperFormat()
    {
        $testDataFile = $this->testDataDir . '/' . 'Insight_Experimental_Test.xml';
        if (!$this->submission('BuildDetails', $testDataFile)) {
            $this->fail('Failed to submit ' . $testDataFile);
            return 1;
        }

        $buildId = pdo_single_row_query("SELECT id FROM build WHERE name = 'BuildDetails-Linux-g++-4.1-LesionSizingSandbox_Debug'");
        $json = $this->get("{$this->url}/api/v1/viewTest.php?buildid={$buildId['id']}");
        $actualResponse = json_decode($json);
        $expectedResponse = json_decode(
            file_get_contents($this->testDataDir . '/' . 'InsightExperimentalExample_Expected.json'));

        $this->assertEqual(count($actualResponse->tests), count($expectedResponse->tests));

        $this->assertEqual($actualResponse->numPassed, $expectedResponse->numPassed);
        $this->assertEqual($actualResponse->numFailed, $expectedResponse->numFailed);
        $this->assertEqual($actualResponse->numNotRun, $expectedResponse->numNotRun);
        $this->assertEqual($actualResponse->numTimeFailed, $expectedResponse->numTimeFailed);

        remove_build($buildId['id']);
    }

    public function testViewTestReturnsProperFormatForParentBuilds()
    {
        $testDataFile = $this->testDataDir . '/' . 'Insight_Experimental_Test_Subbuild.xml';
        if (!$this->submission('BuildDetails', $testDataFile)) {
            $this->fail('Failed to submit ' . $testDataFile);
            return 1;
        }

        $buildId = pdo_single_row_query("SELECT id FROM build WHERE name = 'BuildDetails-Linux-g++-4.1-LesionSizingSandbox_Debug-has-subbuild' AND parentid=-1");

        $response = json_decode($this->get($this->url . '/api/v1/viewTest.php?buildid=' . $buildId['id']));

        $this->assertTrue($response->parentBuild);

        foreach ($response->tests as $test) {
            $this->assertTrue(property_exists($test, 'subprojectid'));
            $this->assertTrue($test->subprojectname == 'some-subproject');
        }

        remove_build($buildId['id']);
    }
}
