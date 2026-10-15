<?php

namespace Tests\Feature\Submission\Update;

use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class UpdateXMLTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;
    use DatabaseTransactions;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * Test that the ChangeId attribute of the Site element is stored for an existing
     * build which was created by a submission without a ChangeId.
     */
    public function testChangeIdFromSiteAttribute(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Update/data/change_id_build.xml'
        ));
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Update/data/change_id_test.xml'
        ));

        $this->assertSame(1, $this->project->builds()->count());
        $build = $this->project->builds()->firstOrFail();
        $this->assertSame('555', $build->changeid);
        $this->assertSame('ctest-3.14.0-rc1', $build->generator);
    }

    /**
     * Test that the ChangeId element of an Update.xml file is stored for the build.
     */
    public function testChangeIdFromUpdate(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Update/data/change_id_update.xml'
        ));

        $this->assertSame(1, $this->project->builds()->count());
        $build = $this->project->builds()->firstOrFail();
        $this->assertSame('555', $build->changeid);
        $this->assertSame('ctest-3.14.0-rc1', $build->generator);
    }
}
