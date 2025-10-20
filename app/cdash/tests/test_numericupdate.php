<?php

require_once dirname(__FILE__) . '/cdash_test_case.php';

use App\Models\Project;
use Tests\Traits\CreatesProjects;

class NumericUpdateTestCase extends KWWebTestCase
{
    use CreatesProjects;

    private Project $project;

    public function __construct()
    {
        parent::__construct();
        $this->project = $this->makePublicProject();
    }

    public function __destruct()
    {
        // Delete project & build created by this test.
        remove_project_builds($this->project->id);
        $this->project->delete();
    }

    public function testNumericUpdate(): void
    {
        // Submit our testing data.
        $this->submission($this->project->name, dirname(__FILE__) . '/data/UpdateNumeric.xml');

        // Check index.php, make sure it shows the expected revision.
        $this->get($this->url . '/api/v1/index.php?project=' . $this->project->name . '&date=2020-06-01');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $this->assertTrue('084161' === $buildgroup['builds'][0]['update']['files']);

        // Verify Revision and PriorRevision on viewUpdate.php.
        $buildid = $buildgroup['builds'][0]['id'];
        $this->get("{$this->url}/api/v1/viewUpdate.php?buildid={$buildid}");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $this->assertTrue('08416132611118b6817515907055112111663314' === $jsonobj['update']['revision']);
        $this->assertTrue('07416132611118b6817515907055112111663314' === $jsonobj['update']['priorrevision']);
    }
}
