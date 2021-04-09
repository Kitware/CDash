<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\Project;

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

        // No errors in the log.
        $this->assertTrue($this->checkLog($this->logfilename) !== false);

        // Get test output.
        $buildtestid = $this->getIdForTest('colortest_long');
        $content = $this->connect($this->url . "/api/v1/testDetails.php?buildtestid=$buildtestid");
        $json_content = json_decode($content, true);
        $output = $json_content['test']['output'];

        // Check for expected escape sequences.
        if (strpos($output, "\x1B[32m") === false) {
            $this->fail('Could not find first escape sequence');
        }

        if (strpos($output, "\x1B[91m") === false) {
            $this->fail('Could not find second escape sequence');
        }

        if (strpos($output, "\x1B[0m") === false) {
            $this->fail('Could not find third escape sequence');
        }

        // Verify that color output works as expected for preformatted test measurements too.
        $file = dirname(__FILE__) . '/data/OutputColor/Test_2.xml';
        if (!$this->submission('OutputColor', $file)) {
            $this->fail("Failed to submit $file");
            return;
        }
        $this->assertTrue($this->checkLog($this->logfilename) !== false);
        $buildtestid = $this->getIdForTest('preformatted_color');
        $content = $this->connect($this->url . "/api/v1/testDetails.php?buildtestid=$buildtestid");
        $json_content = json_decode($content, true);
        $this->assertEqual(1, count($json_content['test']['preformatted_measurements']));
        $measurement = $json_content['test']['preformatted_measurements'][0];
        $this->assertEqual('Color Output', $measurement['name']);
        $expected = 'not bold[NON-XML-CHAR-0x1B][1m bold[NON-XML-CHAR-0x1B][0;0m not bold
[NON-XML-CHAR-0x1B][32mHello world![NON-XML-CHAR-0x1B][0m
[NON-XML-CHAR-0x1B][31mThis is test output[NON-XML-CHAR-0x1B][0m';
        $this->assertEqual($expected, $measurement['value']);

        // Submit build data for later check in viewBuildErrors.
        $file = dirname(__FILE__) . '/data/OutputColor/Build.xml';
        if (!$this->submission('OutputColor', $file)) {
            $this->fail("Failed to submit $file");
            return;
        }
    }

    private function getIdForTest($testname)
    {
        $buildtestid_results = \DB::select(
            DB::raw(
            "SELECT build2test.id FROM build2test
            JOIN test ON (build2test.testid = test.id)
            WHERE test.name = '$testname'")
        );
        $this->assertEqual(1, count($buildtestid_results));
        return $buildtestid_results[0]->id;
    }
}
