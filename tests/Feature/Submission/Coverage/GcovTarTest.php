<?php

namespace Tests\Feature\Submission\Coverage;

use App\Models\Build;
use App\Models\BuildFile;
use App\Models\CoverageView;
use App\Models\Project;
use App\Models\Site;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
     * Assert that the coverage in gcov.tar was recorded for a build, with the labels from its Labels.json.
     */
    private function assertGcovTarCoverage(int $buildid): void
    {
        $coverage = Build::findOrFail($buildid)->coverage()->with('labels')->get()
            ->mapWithKeys(fn (CoverageView $file) => [
                (string) $file->fullpath => [$file->loctested, $file->locuntested, $file->labels->pluck('text')->all()],
            ])
            ->sortKeys()
            ->all();

        $this->assertSame([
            './MathFunctions/MakeTable.cxx' => [15, 2, []],
            './MathFunctions/mysqrt.cxx' => [17, 3, []],
            './subdir/uncovered1.cxx' => [0, 1, []],
            './tutorial.cxx' => [14, 0, ['Foo']],
            './uncovered2.cxx' => [0, 1, []],
        ], $coverage);
    }

    public function testRecordsCoverage(): void
    {
        Storage::fake();

        $buildid = $this->makeUnparsedSubmission($this->project->name, base_path('tests/Feature/Submission/data/gcov.tar'), 'GcovTar');

        $this->assertNotNull($buildid);
        $this->assertGcovTarCoverage($buildid);
        $this->assertDatabaseHas('pending_submissions', ['buildid' => $buildid, 'numfiles' => 0]);
    }

    /**
     * The first phase of an upload records which file a build expects, by md5.  Test that another build
     * expecting the same file doesn't receive this build's upload.
     */
    public function testIgnoresAnotherBuildExpectingTheSameFile(): void
    {
        Storage::fake();
        $other_build = $this->project->builds()->create([
            'name' => 'other',
            'uuid' => Str::uuid()->toString(),
            'siteid' => Site::factory()->create()->id,
        ]);
        BuildFile::create([
            'buildid' => $other_build->id,
            'filename' => 'gcov.tar',
            'md5' => md5_file(base_path('tests/Feature/Submission/data/gcov.tar')),
            'type' => 'GcovTar',
        ]);

        $buildid = $this->makeUnparsedSubmission($this->project->name, base_path('tests/Feature/Submission/data/gcov.tar'), 'GcovTar');

        $this->assertNotNull($buildid);
        $this->assertGcovTarCoverage($buildid);
        $this->assertSame(0, $other_build->coverage()->count());
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
