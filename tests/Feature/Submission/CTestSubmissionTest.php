<?php

namespace Tests\Feature\Submission;

use App\Models\Project;
use App\Models\Site;
use App\Models\Submission;
use App\Utils\DatabaseCleanupUtils;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

/**
 * Runs a real CTest dashboard script against CDash, so that CDash receives the files CTest actually
 * generates, submitted over HTTP the way CTest submits them.  The web server stores the results, so
 * this test can't run in a transaction.  It also relies on the web server using the sync queue, so
 * that each file has been processed by the time CTest finishes submitting it.
 */
class CTestSubmissionTest extends TestCase
{
    use CreatesProjects;

    private Project $project;
    private string $site_name;
    private string $binary_directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
        $this->site_name = 'site_' . Str::uuid()->toString();
        $this->binary_directory = '/tmp/CDashTesting/' . Str::uuid()->toString();
    }

    protected function tearDown(): void
    {
        foreach ($this->project->builds as $build) {
            DatabaseCleanupUtils::removeBuild($build->id);
        }
        // Delete the files the web server kept, before deleting the project deletes their records.
        Storage::delete(Submission::where('projectid', $this->project->id)->pluck('path')->all());
        $this->project->delete();
        Site::where('name', $this->site_name)->delete();
        File::deleteDirectory($this->binary_directory);

        parent::tearDown();
    }

    public function testStoresResultsOfDashboardScript(): void
    {
        $script = base_path('tests/Feature/Submission/data/ctest/dashboard.cmake');
        // Include CTest's output in failure messages, since the build directory is deleted afterwards.
        $result = Process::timeout(180)->run([
            'ctest',
            '-V',
            '-S', $script,
            '-D', "CTEST_SITE={$this->site_name}",
            '-D', "CTEST_BINARY_DIRECTORY={$this->binary_directory}",
            '-D', 'CTEST_SUBMIT_URL=' . url('/submit.php') . '?project=' . urlencode($this->project->name),
        ])->throw();
        $output = $result->output() . $result->errorOutput();

        self::assertSame(1, $this->project->builds()->count(), $output);
        $build = $this->project->builds()->firstOrFail();
        self::assertSame('simple', $build->name);
        self::assertSame($this->site_name, $build->site?->name);
        self::assertTrue($build->done, $output);

        self::assertSame(0, $build->configure?->status, $output);
        self::assertSame(0, $build->configureerrors, $output);
        self::assertSame(0, $build->builderrors, $output);

        self::assertSame(1, $build->tests()->count(), $output);
        $test = $build->tests()->firstOrFail();
        self::assertSame('simple', $test->testname);
        self::assertSame('passed', $test->status, $output);
        self::assertSame(['42'], $test->testMeasurements()->where('name', 'Answer')->pluck('value')->all(), $output);

        self::assertSame(1, $build->coverage()->count(), $output);
        $coverage = $build->coverage()->firstOrFail();
        self::assertSame('./simple.cxx', $coverage->fullpath);
        self::assertSame(File::get(base_path('tests/Feature/Submission/data/ctest/project/simple.cxx')), $coverage->file, $output);
        self::assertNotSame([], $coverage->coveredLines, $output);
        // The uncovered function in simple.cxx is never called.
        self::assertGreaterThan(0, $coverage->loctested, $output);
        self::assertGreaterThan(0, $coverage->locuntested, $output);

        self::assertSame([$script], $build->notes()->pluck('name')->all(), $output);
    }
}
