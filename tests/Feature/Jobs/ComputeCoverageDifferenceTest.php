<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ComputeCoverageDifference;
use App\Models\Build;
use App\Models\CoverageDiff;
use App\Models\CoverageFile;
use App\Models\Project;
use App\Models\Site;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;

class ComputeCoverageDifferenceTest extends TestCase
{
    use CreatesProjects;
    use CreatesSites;
    use DatabaseTransactions;

    private Project $project;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = $this->makePublicProject();
        $this->site = $this->makeSite();
    }

    /**
     * Helper to create coverage data for a build.
     */
    private function createCoverage(Build $build, string $path, string $content, string $log): void
    {
        $coverageFile = CoverageFile::create([
            'fullpath' => $path,
            'file' => $content,
            'crc32' => crc32($content),
        ]);

        $build->coverageResults()->create([
            'fileid' => $coverageFile->id,
            'loctested' => 0,
            'locuntested' => 0,
            'branchestested' => 0,
            'branchesuntested' => 0,
            'functionstested' => 0,
            'functionsuntested' => 0,
        ]);

        DB::insert('
            INSERT INTO coveragefilelog (buildid, fileid, log) VALUES(?, ?, ?)
        ', [
            $build->id,
            $coverageFile->id,
            $log,
        ]);
    }

    /**
     * Helper to setup builds, run the job and return the diff.
     */
    private function setupBuildsAndRun(callable $setup): CoverageDiff
    {
        $baseBuild = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);

        $compareBuild = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);

        $setup($baseBuild, $compareBuild);

        ComputeCoverageDifference::dispatch($baseBuild, $compareBuild);

        $diff = CoverageDiff::where('basebuildid', $baseBuild->id)
            ->where('comparebuildid', $compareBuild->id)
            ->first();

        $this->assertNotNull($diff);
        return $diff;
    }

    /**
     * Helper to assert all coverage diff counters.
     */
    private function assertCoverageDiff(
        CoverageDiff $diff,
        int $coveredLinesAdded = 0,
        int $uncoveredLinesAdded = 0,
        int $coveredLinesRemoved = 0,
        int $uncoveredLinesRemoved = 0,
        int $coveredLinesUncovered = 0,
        int $uncoveredLinesCovered = 0,
    ): void {
        $this->assertSame($coveredLinesAdded, $diff->coveredlinesadded);
        $this->assertSame($uncoveredLinesAdded, $diff->uncoveredlinesadded);
        $this->assertSame($coveredLinesRemoved, $diff->coveredlinesremoved);
        $this->assertSame($uncoveredLinesRemoved, $diff->uncoveredlinesremoved);
        $this->assertSame($coveredLinesUncovered, $diff->coveredlinesuncovered);
        $this->assertSame($uncoveredLinesCovered, $diff->uncoveredlinescovered);
    }

    public function testIdenticalFiles(): void
    {
        $diff = $this->setupBuildsAndRun(function ($base, $compare): void {
            $this->createCoverage($base, 'shared.php', "line1\nline2\n", '1:1;2:0;');
            $this->createCoverage($compare, 'shared.php', "line1\nline2\n", '1:1;2:0;');
        });

        $this->assertCoverageDiff($diff);
    }

    public function testCoverageChange(): void
    {
        $diff = $this->setupBuildsAndRun(function ($base, $compare): void {
            // line 1: covered -> uncovered
            // line 2: uncovered -> covered
            $this->createCoverage($base, 'coverage_change.php', "line1\nline2\n", '1:1;2:0;');
            $this->createCoverage($compare, 'coverage_change.php', "line1\nline2\n", '1:0;2:1;');
        });

        $this->assertCoverageDiff($diff, coveredLinesUncovered: 1, uncoveredLinesCovered: 1);
    }

    public function testLinesAddedAsExecutable(): void
    {
        $diff = $this->setupBuildsAndRun(function ($base, $compare): void {
            // Base: line 1 executable, line 2 NOT executable
            // Compare: line 1 executable, line 2 executable (covered)
            $this->createCoverage($base, 'line_added.php', "line1\nline2\n", '1:1;');
            $this->createCoverage($compare, 'line_added.php', "line1\nline2\n", '1:1;2:1;');
        });

        $this->assertCoverageDiff($diff, coveredLinesAdded: 1);
    }

    public function testLinesRemovedAsExecutable(): void
    {
        $diff = $this->setupBuildsAndRun(function ($base, $compare): void {
            // Base: line 1 executable, line 2 executable (covered)
            // Compare: line 1 executable, line 2 NOT executable
            $this->createCoverage($base, 'line_removed.php', "line1\nline2\n", '1:1;2:1;');
            $this->createCoverage($compare, 'line_removed.php', "line1\nline2\n", '1:1;');
        });

        $this->assertCoverageDiff($diff, coveredLinesRemoved: 1);
    }

    public function testContentChangeWithLineShifts(): void
    {
        $diff = $this->setupBuildsAndRun(function ($base, $compare): void {
            // Base: line 1, line 2
            // Compare: line 1, line 1.5, line 2
            $this->createCoverage($base, 'content_change.php', "line1\nline2\n", '1:1;2:1;');
            $this->createCoverage($compare, 'content_change.php', "line1\nline1.5\nline2\n", '1:1;2:1;3:1;');
        });

        $this->assertCoverageDiff($diff, coveredLinesAdded: 1);
    }

    public function testFileRemoved(): void
    {
        $diff = $this->setupBuildsAndRun(function ($base, $compare): void {
            $this->createCoverage($base, 'only_base.php', "line1\n", '1:1;');
        });

        $this->assertCoverageDiff($diff, coveredLinesRemoved: 1);
    }

    public function testFileAdded(): void
    {
        $diff = $this->setupBuildsAndRun(function ($base, $compare): void {
            $this->createCoverage($compare, 'only_compare.php', "line1\n", '1:0;');
        });

        $this->assertCoverageDiff($diff, uncoveredLinesAdded: 1);
    }

    public function testConstructThrowsOnDifferentProjects(): void
    {
        $project2 = $this->makePublicProject();
        $baseBuild = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);
        $compareBuild = $project2->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Builds must belong to the same project.');

        new ComputeCoverageDifference($baseBuild, $compareBuild);
    }

    public function testBatching(): void
    {
        $diff = $this->setupBuildsAndRun(function ($base, $compare): void {
            for ($i = 0; $i < 101; $i++) {
                $this->createCoverage($base, "file{$i}.php", 'content', '1:1;');
                $this->createCoverage($compare, "file{$i}.php", 'content', '1:0;');
            }
        });

        // Each file has 1 covered -> uncovered change. Total 101.
        $this->assertCoverageDiff($diff, coveredLinesUncovered: 101);
    }

    public function testMultipleFilesAdded(): void
    {
        $diff = $this->setupBuildsAndRun(function ($base, $compare): void {
            $this->createCoverage($compare, 'added1.php', "line1\n", '1:1;');
            $this->createCoverage($compare, 'added2.php', "line1\nline2\n", '1:1;2:0;');
        });

        // added1: 1 covered added
        // added2: 1 covered added, 1 uncovered added
        // Total: 2 covered added, 1 uncovered added
        $this->assertCoverageDiff($diff, coveredLinesAdded: 2, uncoveredLinesAdded: 1);
    }

    public function testMultipleFilesRemoved(): void
    {
        $diff = $this->setupBuildsAndRun(function ($base, $compare): void {
            $this->createCoverage($base, 'removed1.php', "line1\n", '1:1;');
            $this->createCoverage($base, 'removed2.php', "line1\nline2\n", '1:1;2:0;');
        });

        // removed1: 1 covered removed
        // removed2: 1 covered removed, 1 uncovered removed
        // Total: 2 covered removed, 1 uncovered removed
        $this->assertCoverageDiff($diff, coveredLinesRemoved: 2, uncoveredLinesRemoved: 1);
    }

    public function testMixedAdditionsAndRemovals(): void
    {
        $diff = $this->setupBuildsAndRun(function ($base, $compare): void {
            $this->createCoverage($base, 'removed.php', "line1\n", '1:1;');
            $this->createCoverage($compare, 'added.php', "line1\n", '1:0;');
            $this->createCoverage($base, 'shared.php', "line1\n", '1:1;');
            $this->createCoverage($compare, 'shared.php', "line1\n", '1:0;');
        });

        // removed.php: 1 covered removed
        // added.php: 1 uncovered added
        // shared.php: 1 covered -> uncovered (coveredLinesUncovered: 1)
        $this->assertCoverageDiff(
            $diff,
            uncoveredLinesAdded: 1,
            coveredLinesRemoved: 1,
            coveredLinesUncovered: 1
        );
    }

    public function testFileAddedWithNoExecutableLines(): void
    {
        $diff = $this->setupBuildsAndRun(function ($base, $compare): void {
            $this->createCoverage($compare, 'no_exec_added.php', "comment\n", '');
        });

        $this->assertCoverageDiff($diff);
    }

    public function testFileRemovedWithNoExecutableLines(): void
    {
        $diff = $this->setupBuildsAndRun(function ($base, $compare): void {
            $this->createCoverage($base, 'no_exec_removed.php', "comment\n", '');
        });

        $this->assertCoverageDiff($diff);
    }
}
