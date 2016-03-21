<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

class BuildDetailsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function setUp()
    {
        $this->testDataDir = dirname(__FILE__) . '/data/BuildDetails';
        $this->testDataFiles = array('Subbuild1.xml', 'Subbuild2.xml', 'Subbuild3.xml');

        pdo_query("INSERT INTO project (name) VALUES ('BuildDetails')");

        foreach ($this->testDataFiles as $testDataFile) {
            if (!$this->submission('BuildDetails', $this->testDataDir . '/' . $testDataFile)) {
                $this->fail('Failed to submit ' . $testDataFile);
                return 1;
            }
        }

        $this->builds = array();
        $builds = pdo_query("SELECT * FROM build WHERE name = 'linux'");
        while ($build = pdo_fetch_array($builds)) {
            $this->builds[] = $build;
        }
    }

    public function tearDown()
    {
        foreach ($this->builds as $build) {
            remove_build($build['id']);
        }
    }

    public function testViewBuildErrorReturnsErrorForNonexistentBuild()
    {
        $response = $this->get($this->url . '/api/v1/viewBuildError.php?buildid=some-non-existent-build-id');
        $response = json_decode($response);

        $this->assertTrue(strpos($response->error, 'This build does not exist') === 0);
    }

    public function testViewBuildErrorReturnsArrayOfErrors()
    {
        foreach ($this->builds as $build) {
            $response = json_decode($this->get(
                $this->url . '/api/v1/viewBuildError.php?buildid=' . $build['id']));

            $this->assertTrue(is_array($response->errors));
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
}
