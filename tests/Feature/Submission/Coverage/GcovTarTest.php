<?php

namespace Tests\Feature\Submission\Coverage;

use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class GcovTarTest extends TestCase
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
     * Test that a tarball containing a symbolic link, as CTestCoverageCollectGCOV creates for a symlinked
     * uncovered source file, is rejected.
     */
    public function testRejectsSymbolicLinks(): void
    {
        Storage::fake();

        $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Coverage/data/GcovTar_with_symlink.tar'
        ), 'GcovTar', 500);

        $this->assertCount(1, Storage::files('failed'));
    }
}
