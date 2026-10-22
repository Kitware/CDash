<?php

namespace Tests\Feature\Submission;

use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

/**
 * Tests for the BACKUP_TIMEFRAME setting, which controls whether submission files are kept in the
 * "parsed" directory after they have been processed.
 */
class SubmissionBackupTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;
    use DatabaseTransactions;

    private Project $project;
    private MockInterface $log;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->log = Log::spy();

        $this->project = $this->makePublicProject();
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * Submit an XML file and an unparsed data file to the same build, returning the build id.
     */
    private function submitFiles(): int
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/data/build.xml'
        ));
        $buildid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/data/gcov.tar'
        ), 'GcovTar', build_metadata: [
            'build' => 'nobackup',
            'site' => 'localhost',
            'stamp' => '20161004-0500-Nightly',
            'starttime' => 1475599870,
            'endtime' => 1475599870,
        ]);
        $this->assertNotNull($buildid);

        return $buildid;
    }

    public function testKeepsParsedFilesWhenBackupsAreEnabled(): void
    {
        Config::set('cdash.backup_timeframe', 48);

        $this->submitFiles();

        $this->assertCount(2, Storage::files('parsed'));
    }

    public function testDeletesParsedFilesWhenBackupsAreDisabled(): void
    {
        Config::set('cdash.backup_timeframe', 0);

        $buildid = $this->submitFiles();

        $this->assertSame([], Storage::files('parsed'));
        $this->assertSame([], Storage::files('inprogress'));
        $this->log->shouldNotHaveReceived('error');
        $this->log->shouldNotHaveReceived('warning');

        // Both files should still have been parsed into the same build.
        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                name
                command
                buildErrorsCount
                coverage(filters: { eq: { filePath: "./MathFunctions/mysqrt.cxx" } }) {
                  edges {
                    node {
                      linesOfCodeTested
                      linesOfCodeUntested
                    }
                  }
                }
              }
            }
        ', [
            'id' => $buildid,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'name' => 'nobackup',
                    'command' => '/opt/cmake/bin/cmake --build . --config "Release" -- -i',
                    'buildErrorsCount' => 0,
                    'coverage' => [
                        'edges' => [
                            [
                                'node' => [
                                    'linesOfCodeTested' => 17,
                                    'linesOfCodeUntested' => 3,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
