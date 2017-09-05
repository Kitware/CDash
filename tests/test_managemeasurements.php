<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';
require_once 'models/project.php';

class ManageMeasurementsTestCase extends KWWebTestCase
{
    private $testDataFiles;
    private $testDataDir;
    private $builds;
    private $parentBuilds;

    public function __construct()
    {
        parent::__construct();

        $this->PDO = get_link_identifier()->getPdo();
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
            $this->PDO->exec(
                "DELETE FROM measurement WHERE id = $measurement_id");
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

        // Login as admin.
        $client = new GuzzleHttp\Client(['cookies' => true]);
        global $CDASH_BASE_URL;
        try {
            $response = $client->request('POST',
                    $CDASH_BASE_URL . '/user.php',
                    ['form_params' => [
                        'login' => 'simpletest@localhost',
                        'passwd' => 'simpletest',
                        'sent' => 'Login >>']]);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            $this->fail($e->getMessage());
            return false;
        }

        // POST to manageMeasurements.php to add 'Processors' as a
        // test measurement for these projects.
        $measurement_ids = [];
        $this->ProjectId =  get_project_id('InsightExample');
        $this->SubProjectId =  get_project_id('SubProjectExample');
        foreach ([$this->ProjectId, $this->SubProjectId] as $projectid) {
            $measurements = [];
            $measurements[] = [
                'id' => -1,
                'name' => 'Processors',
                'summarypage' => 1,
                'testpage' => 1
            ];
            try {
                $response = $client->request('POST',
                        $CDASH_BASE_URL . '/api/v1/manageMeasurements.php',
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

        // Verify that the 'Processors' measurement is displayed on viewTest.php.
        $this->get($this->url . "/api/v1/viewTest.php?buildid=$this->BuildId");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $found = $jsonobj['columncount'];
        if ($found != 1) {
            $this->fail("Expected 1 extra column on viewTest.php, found $found");
        }
        $found = $jsonobj['columnnames'][0];
        if ($found != 'Processors') {
            $this->fail("Expected extra column to be called 'Processors', found $found");
        }
        $found = count($jsonobj['tests']);
        if ($found != 2) {
            $this->fail("Expected two tests, found $found");
        }
        foreach ($jsonobj['tests'] as $test) {
            $test_name = $test['name'];
            $num_procs = $test['measurements'][0];
            $proc_time = $test['procTimeFull'];
            if ($test_name == 'Test3Procs') {
                if ($num_procs != 3) {
                    $this->fail("Expected 3 processors on viewTest.php, found $num_procs");
                }
                if ($proc_time != 4.2) {
                    $this->fail("Expected 4.2 proc time, found $proc_time");
                }
            } else if ($test_name == 'Test5Procs') {
                if ($num_procs != 5) {
                    $this->fail("Expected 5 processors on viewTest.php, found $num_procs");
                }
                if ($proc_time != 6.5) {
                    $this->fail("Expected 6.5 proc time, found $proc_time");
                }
            } else {
                $this->fail("Unexpected test $test_name");
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

        // Perform similar checks for the subproject case.
        $this->get($this->url . "/api/v1/viewTest.php?buildid=$this->SubProjectBuildId");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $found = $jsonobj['columncount'];
        if ($found != 1) {
            $this->fail("Expected 1 extra column on viewTest.php, found $found");
        }
        $found = $jsonobj['columnnames'][0];
        if ($found != 'Processors') {
            $this->fail("Expected extra column to be called 'Processors', found $found");
        }
        $found = count($jsonobj['tests']);
        if ($found != 3) {
            $this->fail("Expected three tests, found $found");
        }
        foreach ($jsonobj['tests'] as $test) {
            $test_name = $test['name'];
            $num_procs = $test['measurements'][0];
            $proc_time = $test['procTimeFull'];
            if ($test_name == 'experimentalFail1') {
                if ($num_procs != 2) {
                    $this->fail("Expected 2 processors on viewTest.php, found $num_procs");
                }
                if ($proc_time != 2.2) {
                    $this->fail("Expected 2.2 proc time, found $proc_time");
                }
            } else if ($test_name == 'experimentalFail2') {
                if ($num_procs != 3) {
                    $this->fail("Expected 3 processors on viewTest.php, found $num_procs");
                }
                if ($proc_time != 6.6) {
                    $this->fail("Expected 6.6 proc time, found $proc_time");
                }
            } else if ($test_name == 'production') {
                if ($num_procs != 4) {
                    $this->fail("Expected 4 processors on viewTest.php, found $num_procs");
                }
                if ($proc_time != 13.2) {
                    $this->fail("Expected 13.2 proc time, found $proc_time");
                }
            } else {
                $this->fail("Unexpected test $test_name");
            }
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
            } else if ($label == 'MyProductionCode') {
                $expected = 13.2;
            } else {
                $this->fail("Unexpected build label $label");
            }
            if ($expected != $found) {
                $this->fail("Expected proc time to be $expected but found $found for subproject build $label");
            }
        }
    }
}
