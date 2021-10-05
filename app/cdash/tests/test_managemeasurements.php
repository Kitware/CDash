<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\Project;

class ManageMeasurementsTestCase extends KWWebTestCase
{
    private $PDO;
    private $BuildId;
    private $SubProjectBuildId;
    private $MeasurementIds;

    public function __construct()
    {
        parent::__construct();

        $this->PDO = CDash\Database::getInstance();
        $this->PDO->getPdo();
        $this->BuildId = null;
        $this->SubProjectBuildId = null;
        $this->MeasurementIds = [];
    }

    public function __destruct()
    {
        if (!is_null($this->BuildId)) {
            remove_build($this->BuildId);
        }
        if (!is_null($this->SubProjectBuildId)) {
            remove_build($this->SubProjectBuildId);
        }
        foreach ($this->MeasurementIds as $measurement_id) {
            $this->PDO->query(
                "DELETE FROM measurement WHERE id = $measurement_id");
        }
    }

    // function to validate test results returned by the API.
    private function validate_test($test_name, $num_procs, $proc_time, $page)
    {
        // For those special moments when 4.2 does not equal 4.2
        $proc_time = sprintf("%.1f", $proc_time);
        if ($test_name == 'TestNoProcs') {
            if ($proc_time != 1.4) {
                $this->fail("Expected 1.4 proc time on $page, found $proc_time");
            }
        } elseif ($test_name == 'Test3Procs') {
            if ($num_procs != 3) {
                $this->fail("Expected 3 processors on $page, found $num_procs");
            }
            if ($proc_time != 4.2) {
                $this->fail("Expected 4.2 proc time on $page, found $proc_time");
            }
        } elseif ($test_name == 'Test5Procs') {
            if ($num_procs != 5) {
                $this->fail("Expected 5 processors on $page, found $num_procs");
            }
            if ($proc_time != 6.5) {
                $this->fail("Expected 6.5 proc time on $page, found $proc_time");
            }
        } else {
            $this->fail("Unexpected test $test_name on $page");
        }
    }

    // Perform similar checks for the subproject case.
    private function validate_subproject_test($test_name, $num_procs, $proc_time, $io_wait_time, $page)
    {
        // For those special moments when 6.6 does not equal 6.6
        $proc_time = sprintf("%.1f", $proc_time);
        $io_wait_time = sprintf("%.1f", $io_wait_time);
        if ($test_name == 'experimentalFail1') {
            if ($num_procs != 2) {
                $this->fail("Expected 2 processors on $page, found $num_procs");
            }
            if ($proc_time != 2.2) {
                $this->fail("Expected 2.2 proc time on $page, found $proc_time");
            }
            if ($io_wait_time != 4.2) {
                $this->fail("Expected 4.2 io_wait time on $page, found $io_wait_time");
            }
        } elseif ($test_name == 'experimentalFail2') {
            if ($num_procs != 3) {
                $this->fail("Expected 3 processors on $page, found $num_procs");
            }
            if ($proc_time != 6.6) {
                $this->fail("Expected 6.6 proc time on $page, found $proc_time");
            }
            if ($io_wait_time != 5.3) {
                $this->fail("Expected 5.3 io_wait time on $page, found $io_wait_time");
            }
        } elseif ($test_name == 'production') {
            if ($num_procs != 4) {
                $this->fail("Expected 4 processors on $page, found $num_procs");
            }
            if ($proc_time != 13.2) {
                $this->fail("Expected 13.2 proc time on $page, found $proc_time");
            }
            if ($io_wait_time != 6.4) {
                $this->fail("Expected 6.4 io_wait time on $page, found $io_wait_time");
            }
        } else {
            $this->fail("Unexpected test $test_name on $page");
        }
    }

    public function testManageMeasurements()
    {
        // Submit a test file with a named measurement.
        $testDataFile = dirname(__FILE__) .  '/data/TestMeasurements/Test.xml';
        if (!$this->submission('InsightExample', $testDataFile)) {
            $this->fail("Failed to submit Test.xml");
            return false;
        }

        // Get the ID of the build we just created.
        $stmt = $this->PDO->query(
            "SELECT id FROM build WHERE name = 'test_measurements_example'");
        $this->BuildId = $stmt->fetchColumn();
        if (!$this->BuildId > 0) {
            $this->fail("Expected positive integer for build ID, found $this->BuildId");
        }

        // Submit subproject test data too.
        $projectFile = dirname(__FILE__) .  '/data/MultipleSubprojects/Project.xml';
        if (!$this->submission('SubProjectExample', $projectFile)) {
            $this->fail("Failed to submit Project.xml");
            return false;
        }
        $testDataFile = dirname(__FILE__) .  '/data/TestMeasurements/Test_subproj.xml';
        if (!$this->submission('SubProjectExample', $testDataFile)) {
            $this->fail("Failed to submit Test_subproj.xml");
            return false;
        }
        $stmt = $this->PDO->query(
            "SELECT id FROM build WHERE name = 'subprojects_measurements_example' AND parentid = -1");
        $this->SubProjectBuildId = $stmt->fetchColumn();
        if (!$this->SubProjectBuildId > 0) {
            $this->fail("Expected positive integer for build ID, found $this->BuildId");
        }

        // Verify that the buildtesttime table correctly multiplies
        // test execution time by the number of processors used.
        $stmt = $this->PDO->prepare("SELECT time FROM buildtesttime WHERE buildid = :buildid");
        $this->PDO->execute($stmt, [':buildid' => $this->BuildId]);
        $this->assertEqual(12.13, $stmt->fetchColumn());

        // Login as admin.
        $client = $this->getGuzzleClient();

        // POST to manageMeasurements.php to add 'Processors' and
        // 'I/O Wait Time' as test measurements for these projects.
        $measurement_ids = [];
        $this->ProjectId =  get_project_id('InsightExample');
        $this->SubProjectId =  get_project_id('SubProjectExample');
        $new_measurements = ['Processors', 'I/O Wait Time'];
        foreach ($new_measurements as $new_measurement) {
            foreach ([$this->ProjectId, $this->SubProjectId] as $projectid) {
                $measurements = [];
                $measurements[] = [
                    'id' => -1,
                    'name' => $new_measurement,
                    'summarypage' => 1,
                    'testpage' => 1
                ];
                try {
                    $response = $client->request('POST',
                            $this->url . '/api/v1/manageMeasurements.php',
                            ['json' => ['projectid' => $projectid, 'measurements' => $measurements]]);
                } catch (GuzzleHttp\Exception\ClientException $e) {
                    $this->fail($e->getMessage());
                    return false;
                }

                // Response should have the ID of the newly created measurement.
                $response_array = json_decode($response->getBody(), true);
                $measurement_id = $response_array['id'];
                if (!$measurement_id > 0) {
                    $this->fail("Expected positive integer for measurement ID, found $measurement_id");
                }
                $this->MeasurementIds[] = $measurement_id;
                // Check that the measurement actually got added to the database.
                $stmt = $this->PDO->query(
                        "SELECT id FROM measurement WHERE id = $measurement_id");
                $found = $stmt->fetchColumn();
                if ($found != $measurement_id) {
                    $this->fail("Expected $measurement_id but found $found for DB measurement ID");
                }
            }
        }

        // Verify that the new measurements are displayed on viewTest.php.
        $this->get($this->url . "/api/v1/viewTest.php?buildid=$this->BuildId");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $found = $jsonobj['columncount'];
        if ($found != 2) {
            $this->fail("Expected 2 extra columns on viewTest.php, found $found");
        }
        foreach ($new_measurements as $new_measurement) {
            if (!in_array($new_measurement, $jsonobj['columnnames'])) {
                $this->fail("Did not find expected extra column '$new_measurement'");
            }
        }
        $found = count($jsonobj['tests']);
        if ($found != 3) {
            $this->fail("Expected three tests, found $found");
        }
        $first = true;
        $proc_idx = array_search('Processors', $jsonobj['columnnames']);
        foreach ($jsonobj['tests'] as $test) {
            $test_name = $test['name'];
            $num_procs = $test['measurements'][$proc_idx];
            $proc_time = $test['procTimeFull'];
            $this->validate_test($test_name, $num_procs, $proc_time, 'viewTest.php');
            if ($first && $num_procs) {
                $selected_test_id = $test['id'];
                $selected_nprocs =  $num_procs;
                $first = false;
            }
        }

        // Verify that 'Processors' is also displayed on testSummary.php.
        $this->get($this->url . "/api/v1/testSummary.php?project=$this->ProjectId&name=Test5Procs&date=2017-08-29");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $found = $jsonobj['columncount'];
        if ($found != 1) {
            $this->fail("Expected 1 extra column on testSummary.php, found $found");
        }
        $found = $jsonobj['columns'][0];
        if ($found != 'Processors') {
            $this->fail("Expected extra column to be called 'Processors', found $found");
        }
        $found = count($jsonobj['builds']);
        if ($found != 1) {
            $this->fail("Expected one build, found $found");
        }
        $found = $jsonobj['builds'][0]['measurements'][0];
        if ($found != 5) {
            $this->fail("Expected 5 processors on testSummary.php, found $found");
        }
        $found = $jsonobj['builds'][0]['proctime'];
        if ($found != 6.5) {
            $this->fail("Expected proctime to be 6.5, found $found");
        }
        $this->assertEqual("/api/v1/testSummary.php?project=$this->ProjectId&name=Test5Procs&date=2017-08-29&export=csv", $jsonobj['csvlink']);

        // Make sure download as CSV works too.
        $this->get($this->url . "/api/v1/testSummary.php?project=$this->ProjectId&name=Test5Procs&date=2017-08-29&export=csv");
        $this->assertTrue($this->checkLog($this->logfilename) !== false);

        // Check queryTests.php for this extra data too.
        $this->get($this->url . '/api/v1/queryTests.php?project=InsightExample&date=2017-08-29');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        if ($jsonobj['hasprocessors'] !== true) {
            $this->fail("hasprocessors not true for queryTests.php");
        }
        $this->assertEqual(1, count($jsonobj['extrameasurements']));
        if (!in_array('I/O Wait Time', $jsonobj['extrameasurements'])) {
            $this->fail("Did not find expected extra measurement 'I/O Wait Time' on queryTests.php");
        }
        $this->assertEqual(3, count($jsonobj['builds']));
        foreach ($jsonobj['builds'] as $build) {
            $this->validate_test($build['testname'], $build['nprocs'], $build['procTime'], 'queryTests.php');
        }

        $this->get($this->url . "/api/v1/viewTest.php?buildid=$this->SubProjectBuildId");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $found = $jsonobj['columncount'];
        if ($found != 2) {
            $this->fail("Expected 2 extra columns on viewTest.php, found $found");
        }
        foreach ($new_measurements as $new_measurement) {
            if (!in_array($new_measurement, $jsonobj['columnnames'])) {
                $this->fail("Did not find expected extra column '$new_measurement'");
            }
        }
        $found = count($jsonobj['tests']);
        if ($found != 3) {
            $this->fail("Expected three tests, found $found");
        }
        $proc_idx = array_search('Processors', $jsonobj['columnnames']);
        $io_wait_idx = array_search('I/O Wait Time', $jsonobj['columnnames']);
        foreach ($jsonobj['tests'] as $test) {
            $test_name = $test['name'];
            $num_procs = $test['measurements'][$proc_idx];
            $proc_time = $test['procTimeFull'];
            $io_wait_time = $test['measurements'][$io_wait_idx];
            $this->validate_subproject_test($test_name, $num_procs, $proc_time, $io_wait_time, 'viewTest.php');
        }

        $this->get($this->url . '/api/v1/queryTests.php?project=SubProjectExample&date=2017-08-29');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        if ($jsonobj['hasprocessors'] !== true) {
            $this->fail("hasprocessors not true for queryTests.php");
        }
        foreach ($jsonobj['builds'] as $build) {
            $this->validate_subproject_test($build['testname'], $build['nprocs'], $build['procTime'], $build['measurements'][0], 'queryTests.php');
        }

        // Verify that correct Proc Time values are shown on index.php for
        // subproject builds.
        $this->get($this->url . "/api/v1/index.php?project=SubProjectExample&parentid=$this->SubProjectBuildId");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        foreach ($buildgroup['builds'] as $build) {
            $label = $build['label'];
            $found = $build['test']['procTimeFull'];
            $expected = null;
            if ($label == 'MyExperimentalFeature') {
                $expected = 8.8;
            } elseif ($label == 'MyProductionCode') {
                $expected = 13.2;
            } else {
                $this->fail("Unexpected build label $label");
            }
            if ($expected != $found) {
                $this->fail("Expected proc time to be $expected but found $found for subproject build $label");
            }
        }

        // Verify that our test graphs correctly report Processors.
        $this->get($this->url .  "/api/v1/testGraph.php?testid={$selected_test_id}&buildid={$this->BuildId}&measurementname=Processors&type=measurement");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertTrue($jsonobj[0]['data'][0]['y']  == $selected_nprocs);
        $this->assertTrue(count($jsonobj[0]['data']) === 1);
    }
}
