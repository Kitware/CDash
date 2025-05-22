<?php

namespace Tests\Feature\Submission\CaseInsensitivity;

use App\Models\Build;
use App\Models\BuildGroup;
use App\Models\Project;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class CaseInsensitivityTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;

    private BuildGroup $buildgroup;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        // The trait doesn't initialize the default buildgroups for us, so we do it manually
        $legacy_project = new \CDash\Model\Project();
        $legacy_project->Id = $this->project->id;
        $legacy_project->InitialSetup();

        $this->project->refresh();

        // The XML file associated with this test uses 'my-custom-group' instead.
        $this->buildgroup = BuildGroup::create([
            'name' => 'My-Custom-Group',
            'projectid' => $this->project->id,
        ]);
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * Test that submissions are parsed successfully when the case doesn't match
     * for their project and build group names.
     */
    public function testCaseInsensitiveSubmission(): void
    {
        $this->submitFiles(strtoupper($this->project->name), [
            base_path('tests/Feature/Submission/CaseInsensitivity/data/Build.xml'),
        ]);

        self::assertEquals($this->project->builds()->count(), 1);

        $build = $this->project->builds()->first();
        self::assertNotNull($build);

        $group_from_build = $build->buildGroups()->first();
        self::assertNotNull($group_from_build);

        self::assertEquals($group_from_build->id, $this->buildgroup->id);
    }
}
