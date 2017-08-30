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
    }
}
