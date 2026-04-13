<?php

require_once __DIR__ . '/cdash_test_case.php';

use App\Models\Build;
use App\Models\BuildUpdate;
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
        $this->submission($this->project->name, __DIR__ . '/data/UpdateNumeric.xml');

        // Check index.php, make sure it shows the expected revision.
        $this->get($this->url . '/api/v1/index.php?project=' . $this->project->name . '&date=2020-06-01');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $this->assertTrue('084161' === $buildgroup['builds'][0]['update']['files']);

        // Verify Revision and PriorRevision.
        $buildid = $buildgroup['builds'][0]['id'];

        /** @var BuildUpdate $update */
        $update = Build::findOrFail((int) $buildid)->updateStep;

        $this->assertEqual('08416132611118b6817515907055112111663314', $update->revision);
        $this->assertEqual('07416132611118b6817515907055112111663314', $update->priorrevision);
    }
}
