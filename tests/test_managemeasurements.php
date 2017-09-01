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
        $this->MeasurementId = null;
    }

    public function __destruct()
    {
        if (!is_null($this->BuildId)) {
            remove_build($this->BuildId);
        }
        if (!is_null($this->MeasurementId)) {
            $this->PDO->exec(
                "DELETE FROM measurement WHERE id = $this->MeasurementId");
        }
    }

    public function testManageMeasurements()
    {
        // Submit a test file with a named measurement.
        $testDataFile = dirname(__FILE__) .  '/data/TestMeasurements/Test.xml';
        if (!$this->submission('InsightExample', $testDataFile)) {
            $this->fail("Failed to submit test data");
            return false;
        }

        // Get the ID of the build we just created.
        $stmt = $this->PDO->query(
            "SELECT id FROM build WHERE name = 'test_measurements_example'");
        $this->BuildId = $stmt->fetchColumn();
        if (!$this->BuildId > 0) {
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
        // test measurement for this project.
        $projectid = get_project_id('InsightExample');
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
        $this->MeasurementId = $response_array['id'];
        if (!$this->MeasurementId > 0) {
            $this->fail("Expected positive integer for measurement ID, found $this->MeasurementId");
        }

        // Check that the measurement actually got added to the database.
        $stmt = $this->PDO->query(
            "SELECT id FROM measurement WHERE id = $this->MeasurementId");
        $found = $stmt->fetchColumn();
        if ($found != $this->MeasurementId) {
            $this->fail("Expected $this->MeasurementId but found $found for DB measurement ID");
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
            } elseif ($test_name == 'Test5Procs') {
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
        $this->get($this->url . "/api/v1/testSummary.php?project=$projectid&name=Test5Procs&date=2017-08-29");
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
    }
}
