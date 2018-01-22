<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';
require_once 'models/project.php';
require_once 'models/subproject.php';
require_once 'models/user.php';
require_once 'models/labelemail.php';

class OutputColorTestCase extends KWWebTestCase
{
    private $builds;
    private $parentBuilds;

    public function __construct()
    {
        parent::__construct();
        $this->PDO = get_link_identifier()->getPdo();
    }

    public function testOutputColor()
    {
        $project = new Project();
        $project->Id = get_project_id('OutputColor');
        if ($project->Id >= 0) {
            remove_project_builds($project->Id);
            $project->Delete();
        }

        $settings = array(
                'Name' => 'OutputColor',
                'Description' => 'Test to make sure test output uses terminal colors');
        $projectid = $this->createProject($settings);
        if ($projectid < 1) {
            $this->fail('Failed to create project');
            return;
        }

        // Submit testing data.
        $file = dirname(__FILE__) . '/data/OutputColor/Test.xml';
        if (!$this->submission('OutputColor', $file)) {
            $this->fail("Failed to submit $file");
            return;
        }

        $buildid_results = pdo_single_row_query('SELECT id FROM build WHERE projectid = ' . $projectid);
        $buildid = $buildid_results['id'];

        $testid_results = pdo_single_row_query("SELECT id FROM test WHERE name = 'colortest_long'");
        $testid = $testid_results['id'];

        // Get the output.
        $content = $this->connect($this->url . '/api/v1/testDetails.php?build=' . $buildid . '&test=' . $testid);
        $json_content = json_decode($content, true);
        $output = $json_content['test']['output'];

        if (strpos($output, "\x1B[32m") === false) {
            $this->fail('Could not find first escape sequence');
            return;
        }

        if (strpos($output, "\x1B[91m") === false) {
            $this->fail('Could not find second escape sequence');
            return;
        }

        if (strpos($output, "\x1B[0m") === false) {
            $this->fail('Could not find third escape sequence');
            return;
        }

        $this->assertTrue(true, 'All escape sequences found');
    }
}
