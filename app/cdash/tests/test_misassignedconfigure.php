<?php
require_once dirname(__FILE__) . '/cdash_test_case.php';

use CDash\Model\Project;
use Illuminate\Support\Facades\DB;

class MisassignedConfigureTestCase extends KWWebTestCase
{
    private Project $project;

    public function __construct()
    {
        parent::__construct();
    }

    public function __destruct()
    {
        // Delete project & build created by this test.
        remove_project_builds($this->project->Id);
        $this->project->Delete();
    }

    public function testMisassignedConfigure() : void
    {
        // Create test project.
        $this->login();
        $this->project = new Project();
        $this->project->Id = $this->createProject([
            'Name' => 'MisassignedConfigureProject',
        ]);
        $this->project->Fill();
        $this->deleteLog($this->logfilename);

        $data_dir = dirname(__FILE__) . '/data/MultipleSubprojects/';

        // Submit some testing data.
        $this->submission($this->project->Name, "{$data_dir}/Configure_bad.xml");
        $this->submission($this->project->Name, "{$data_dir}/Build.xml");

        $this->assertTrue($this->checkLog($this->logfilename) !== false);

        $parent_builds = DB::select('
            SELECT id FROM build
            WHERE projectid = :projectid
            AND parentid = -1', ['projectid' => $this->project->Id]);
        $this->assertTrue(1 === count($parent_builds));
        $parentid = $parent_builds[0]->id;

        // Verify expected configure output.
        $this->get("{$this->url}/api/v1/viewConfigure.php?buildid={$parentid}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        if (count($jsonobj['configures']) !== 1) {
            $this->fail('Did not find one configure record when expected');
        }
        $this->deleteLog($this->logfilename);
    }
}
