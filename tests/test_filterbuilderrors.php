<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\Project;

class FilterBuildErrorsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->PDO = get_link_identifier()->getPdo();
    }

    public function testFilterBuildErrors()
    {
        // Create a new project.
        $settings = [
            'Name' => 'FilterErrors',
            'Public' => 1,
            'ErrorsFilter' => <<<FILTER
was not declared in this scope
No such file or directory
FILTER
];
        $projectid = $this->createProject($settings);
        if ($projectid < 1) {
            $this->fail('Failed to create project');
        }
        $project = new Project();
        $project->Id = $projectid;

        // Submit our test data.
        $rep = dirname(__FILE__) . '/data/BuildFailureDetails';
        if (!$this->submission('FilterErrors', "$rep/Build_1.xml")) {
            $this->fail('failed to submit Build_1.xml');
            return 1;
        }

        // Get the buildid that we just created so we can delete it later.
        $buildids = array();
        $buildid_results = pdo_query(
            "SELECT id FROM build WHERE name='test_buildfailure'");
        while ($buildid_array = pdo_fetch_array($buildid_results)) {
            $buildids[] = $buildid_array['id'];
        }
        $buildid = $buildids[0];

        // Validate the build.
        $stmt = $this->PDO->query(
                "SELECT builderrors, buildwarnings, testfailed, testpassed,
                configureerrors, configurewarnings
                FROM build WHERE id = $buildid");
        $row = $stmt->fetch();

        $answer_key = [
            'builderrors' => 0,
            'buildwarnings' => 0,
        ];
        foreach ($answer_key as $key => $expected) {
            $found = $row[$key];
            if ($found != $expected) {
                $this->fail("Expected $expected for $key but found $found for $buildid");
            }
        }

        // Cleanup.
        remove_build($buildid);
        $project->Delete();
    }
}
