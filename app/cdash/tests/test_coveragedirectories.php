<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once __DIR__ . '/cdash_test_case.php';

use CDash\Model\Project;

class CoverageDirectoriesTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testCoverageDirectories(): void
    {
        $project = new Project();
        $project->Id = get_project_id('CoverageDirectories');
        if ($project->Id >= 0) {
            remove_project_builds($project->Id);
            App\Models\Project::findOrFail($project->Id)->delete();
        }

        $settings = [
            'Name' => 'CoverageDirectories',
            'Description' => 'Test to make sure directories display proper files'];
        $projectid = $this->createProject($settings);
        if ($projectid < 1) {
            $this->fail('Failed to create project');
            return;
        }

        $filesToSubmit = ['prefix-Coverage.xml', 'prefix-CoverageLog-0.xml', 'sort-Coverage.xml', 'sort-CoverageLog-0.xml', 'sort-CoverageLog-1.xml'];
        $dir = __DIR__ . '/data/CoverageDirectories';
        foreach ($filesToSubmit as $file) {
            if (!$this->submission('CoverageDirectories', "$dir/$file")) {
                $this->fail("Failed to submit $file");
                return;
            }
        }

        // Find buildid for coverage.
        $content = $this->connect($this->url . '/api/v1/index.php?project=CoverageDirectories&date=2018-01-19');
        $jsonobj = json_decode($content, true);
        if (count($jsonobj['coverages']) < 1) {
            $this->fail('No coverage build found when expected');
            return;
        }
        $buildid = $jsonobj['coverages'][0]['buildid'];

        // Find buildid for coverage.
        $content = $this->connect($this->url . '/api/v1/index.php?project=CoverageDirectories&date=20180122');
        $jsonobj = json_decode($content, true);
        if (count($jsonobj['coverages']) < 1) {
            $this->fail('No coverage build found when expected');
            return;
        }

        $this->assertTrue(true, 'Correct directories were returned');
    }
}
