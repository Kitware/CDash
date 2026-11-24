<?php

namespace Tests\Feature\Submission;

use App\Enums\SubmissionStatus;
use App\Exceptions\BadSubmissionException;
use App\Jobs\ProcessSubmission;
use App\Models\BuildFile;
use App\Models\PendingSubmissions;
use App\Models\Project;
use App\Models\Site;
use App\Models\Submission;
use App\Services\SubmissionService;
use App\Utils\SubmissionUtils;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToMoveFile;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class SubmissionRecordingTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;
    use DatabaseTransactions;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();

        $this->project = $this->makePublicProject();
        // Loading a project sets PHP's default timezone to the project's nightly timezone.  Use one other than
        // UTC, so that the tests catch times recorded in it.
        $this->project->update(['nightlytime' => '01:00:00 America/New_York']);
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        parent::tearDown();
    }

    private function recordedSubmission(): Submission
    {
        $this->assertSame(1, Submission::where('projectid', $this->project->id)->count());

        return Submission::where('projectid', $this->project->id)->firstOrFail();
    }

    /**
     * Write an XML file to the inbox and record it, as an upload would, returning the inbox filename.
     */
    private function receiveXmlFile(string $contents, ?int $buildid, string $type): string
    {
        $uuid = Str::uuid7()->toString();
        $filename = SubmissionUtils::xmlFilename($this->project->name, '', $uuid, '');
        Storage::put("inbox/{$filename}", $contents);
        $this->assertSame($uuid, SubmissionService::recordSubmission($uuid, $this->project->id, $buildid, $type, "inbox/{$filename}"));

        return $filename;
    }

    /**
     * Write a Done.xml file to the inbox for a build that has another file pending, so that the job requeues it.
     *
     * @return array{string, int} The inbox filename and the build's id
     */
    private function receiveWaitingDoneFile(): array
    {
        $build = $this->project->builds()->create([
            'name' => 'requeued',
            'uuid' => Str::uuid()->toString(),
            'siteid' => Site::factory()->create()->id,
        ]);
        // Done.xml waits while any file besides itself is pending.
        PendingSubmissions::upsert([
            'buildid' => $build->id,
            'numfiles' => 2,
            'recheck' => false,
        ], 'buildid');
        $filename = $this->receiveXmlFile('<?xml version="1.0" encoding="UTF-8"?><Done><buildId>'
            . $build->id . '</buildId><time>' . time() . '</time></Done>', $build->id, 'Done');

        return [$filename, $build->id];
    }

    /**
     * Receive a data file for a build, writing its buildfile row and recording it, as an upload would, and return the
     * inbox filename and the submission's id.
     *
     * @return array{string, string}
     */
    private function receiveDataFile(int $buildid, string $contents, string $filename): array
    {
        $md5 = md5($contents);
        $inbox_filename = SubmissionUtils::dataFilename($this->project->name, '', 'BuildPropertiesJSON', $buildid, $md5, 'json');
        Storage::put("inbox/{$inbox_filename}", $contents);
        BuildFile::create([
            'buildid' => $buildid,
            'type' => 'BuildPropertiesJSON',
            'md5' => $md5,
            'filename' => $filename,
        ]);
        $submissionid = Str::uuid7()->toString();
        SubmissionService::recordSubmission($submissionid, $this->project->id, $buildid, 'BuildPropertiesJSON', "inbox/{$inbox_filename}", $filename);

        return [$inbox_filename, $submissionid];
    }

    public function testRecordsParsedXmlFile(): void
    {
        Config::set('cdash.backup_timeframe', 48);

        $this->makeSubmission($this->project->name, base_path('tests/Feature/Submission/data/build.xml'));

        $submission = $this->recordedSubmission();
        $this->assertSame(SubmissionStatus::DONE, $submission->status);
        $this->assertSame('Build', $submission->type);
        $this->assertSame($this->project->builds()->firstOrFail()->id, $submission->buildid);
        $this->assertSame(Storage::files('parsed'), [$submission->path]);
        $this->assertSame(1, $submission->attempts);
        $this->assertNull($submission->error);
        $this->assertNotNull($submission->started_at);
        $this->assertNotNull($submission->finished_at);
    }

    public function testRecordsTimesInUtc(): void
    {
        $now = Carbon::parse('2026-01-15 12:00:00', 'UTC');
        $this->travelTo($now);

        $this->makeSubmission($this->project->name, base_path('tests/Feature/Submission/data/build.xml'));

        $submission = $this->recordedSubmission();
        $this->assertEquals($now, $submission->received_at);
        $this->assertEquals($now, $submission->next_attempt_at);
        $this->assertEquals($now, $submission->started_at);
        $this->assertEquals($now, $submission->finished_at);
    }

    public function testRecordsParsedDataFile(): void
    {
        Config::set('cdash.backup_timeframe', 48);
        $file_to_submit = base_path('tests/Feature/Submission/data/gcov.tar');

        $buildid = $this->makeUnparsedSubmission($this->project->name, $file_to_submit, 'GcovTar');

        $submission = $this->recordedSubmission();
        $this->assertSame(SubmissionStatus::DONE, $submission->status);
        $this->assertSame('GcovTar', $submission->type);
        $this->assertSame('gcov.tar', $submission->filename);
        $this->assertSame($buildid, $submission->buildid);
        $this->assertSame(Storage::files('parsed'), [$submission->path]);
        $this->assertSame(1, $submission->attempts);
        $this->assertNull($submission->error);
    }

    public function testKeepsLastPathOfDeletedFile(): void
    {
        Config::set('cdash.backup_timeframe', 0);

        $this->makeSubmission($this->project->name, base_path('tests/Feature/Submission/data/build.xml'));

        $submission = $this->recordedSubmission();
        $this->assertSame(SubmissionStatus::DONE, $submission->status);
        $this->assertSame("inprogress/{$this->project->name}_-__-_{$submission->id}_-_.xml", $submission->path);
        $this->assertSame([], Storage::allFiles());
    }

    public function testRecordsFailedFile(): void
    {
        $filename = $this->receiveXmlFile('Not a submission', null, 'Build');
        $submissionid = $this->recordedSubmission()->id;

        $this->assertThrows(
            fn () => ProcessSubmission::dispatchSync($filename, $this->project->id, null, '', $submissionid),
            BadSubmissionException::class,
        );

        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::FAILED, $submission->status);
        $this->assertSame("failed/{$filename}", $submission->path);
        $this->assertStringContainsString('Could not determine submission file type', (string) $submission->error);
        $this->assertSame(1, $submission->attempts);
        $this->assertNotNull($submission->finished_at);
    }

    public function testCleansUpFailureMessage(): void
    {
        $filename = $this->receiveXmlFile('Not a submission', null, 'Build');
        $submissionid = $this->recordedSubmission()->id;
        Storage::move("inbox/{$filename}", "inprogress/{$filename}");

        (new ProcessSubmission($filename, $this->project->id, null, '', $submissionid))
            ->failed(new RuntimeException("Invalid \xe9 and null \0 bytes " . str_repeat('x', 2000)));

        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::FAILED, $submission->status);
        $this->assertSame("failed/{$filename}", $submission->path);
        $this->assertSame('Invalid ? and null  bytes ' . str_repeat('x', 974) . '...', $submission->error);
    }

    public function testMovesFailedFileFromWhereItsRowSaysItIs(): void
    {
        $filename = $this->receiveXmlFile('Not a submission', null, 'Build');
        $submissionid = $this->recordedSubmission()->id;

        // The file is still in the inbox, as when a job fails before moving it to inprogress/.
        (new ProcessSubmission($filename, $this->project->id, null, '', $submissionid))->failed(new RuntimeException('Failed'));

        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::FAILED, $submission->status);
        $this->assertSame("failed/{$filename}", $submission->path);
        $this->assertSame(["failed/{$filename}"], Storage::allFiles());
    }

    public function testKeepsPathOfFailedFileThatIsMissing(): void
    {
        Exceptions::fake();
        $filename = $this->receiveXmlFile('Not a submission', null, 'Build');
        $submissionid = $this->recordedSubmission()->id;
        Storage::delete("inbox/{$filename}");

        (new ProcessSubmission($filename, $this->project->id, null, '', $submissionid))->failed(new RuntimeException('Failed'));

        Exceptions::assertReported(UnableToMoveFile::class);
        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::FAILED, $submission->status);
        $this->assertSame("inbox/{$filename}", $submission->path);
        $this->assertSame('Failed', $submission->error);
    }

    public function testMovesFailedFileFromInprogressWhenItsRowIsOutOfDate(): void
    {
        $filename = $this->receiveXmlFile('Not a submission', null, 'Build');
        $submissionid = $this->recordedSubmission()->id;
        Submission::whereKey($submissionid)->update(['path' => 'inbox/moved.xml']);
        Storage::move("inbox/{$filename}", 'inprogress/moved.xml');

        // As when the job moved the file to inprogress/ but couldn't record that in the row.
        (new ProcessSubmission($filename, $this->project->id, null, '', $submissionid))->failed(new RuntimeException('Failed'));

        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::FAILED, $submission->status);
        $this->assertSame('failed/moved.xml', $submission->path);
        $this->assertSame(['failed/moved.xml'], Storage::allFiles());
    }

    public function testRecordsFailureWhenStorageIsUnreachable(): void
    {
        Exceptions::fake();
        $filename = $this->receiveXmlFile('Not a submission', null, 'Build');
        $submissionid = $this->recordedSubmission()->id;
        $disk = Mockery::mock(Storage::disk())->makePartial();
        $disk->shouldReceive('exists')->andThrow(UnableToCheckFileExistence::forLocation("inbox/{$filename}"));
        Storage::set(Config::string('filesystems.default'), $disk);

        (new ProcessSubmission($filename, $this->project->id, null, '', $submissionid))->failed(new RuntimeException('Failed'));

        Exceptions::assertReported(UnableToCheckFileExistence::class);
        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::FAILED, $submission->status);
        $this->assertSame('Failed', $submission->error);
        $this->assertSame("inbox/{$filename}", $submission->path);
    }

    public function testMovesFailedFileOfJobWhoseRowIsGone(): void
    {
        $filename = $this->receiveXmlFile('Not a submission', null, 'Build');
        $submissionid = $this->recordedSubmission()->id;
        Submission::whereKey($submissionid)->delete();

        $this->assertThrows(
            fn () => ProcessSubmission::dispatchSync($filename, $this->project->id, null, '', $submissionid),
            BadSubmissionException::class,
        );

        $this->assertSame(["failed/{$filename}"], Storage::allFiles());
    }

    public function testFindsFileAtPathInItsRow(): void
    {
        Config::set('cdash.backup_timeframe', 48);
        $filename = $this->receiveXmlFile((string) file_get_contents(base_path('tests/Feature/Submission/data/build.xml')), null, 'Build');
        $submissionid = $this->recordedSubmission()->id;
        Storage::move("inbox/{$filename}", 'inbox/moved.xml');
        Submission::whereKey($submissionid)->update(['path' => 'inbox/moved.xml']);

        ProcessSubmission::dispatchSync($filename, $this->project->id, null, '', $submissionid);

        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::DONE, $submission->status);
        $this->assertSame(Storage::files('parsed'), [$submission->path]);
        $this->assertSame([], Storage::files('inbox'));
    }

    public function testLocksUploadBuildOfXmlFileWhoseBuildWasDeleted(): void
    {
        $build = $this->project->builds()->create([
            'name' => 'replaced',
            'uuid' => Str::uuid()->toString(),
            'siteid' => Site::factory()->create()->id,
        ]);
        $filename = $this->receiveXmlFile((string) file_get_contents(base_path('tests/Feature/Submission/data/build.xml')), $build->id, 'Build');
        $submissionid = $this->recordedSubmission()->id;
        $build->delete();
        $locks = [];
        DB::listen(function (QueryExecuted $query) use (&$locks): void {
            if (str_contains($query->sql, 'pg_advisory_xact_lock')) {
                $locks[] = $query->bindings;
            }
        });

        ProcessSubmission::dispatchSync($filename, $this->project->id, $build->id, '', $submissionid);

        // The lock still keeps the file from being parsed alongside the build's other files.
        $this->assertSame([[$build->id]], $locks);
        $this->assertSame(SubmissionStatus::DONE, Submission::findOrFail($submissionid)->status);
    }

    public function testParsesDataFileIntoBuildInItsRow(): void
    {
        $build = $this->project->builds()->create([
            'name' => 'recorded',
            'uuid' => Str::uuid()->toString(),
            'siteid' => Site::factory()->create()->id,
        ]);
        $other_build = $this->project->builds()->create([
            'name' => 'other',
            'uuid' => Str::uuid()->toString(),
            'siteid' => $build->siteid,
        ]);
        $contents = '{"key": "value"}';
        [$filename, $submissionid] = $this->receiveDataFile($build->id, $contents, 'properties.json');

        // The job names another build, which has no buildfile row for this file.
        ProcessSubmission::dispatchSync($filename, $this->project->id, $other_build->id, md5($contents), $submissionid);

        $this->assertSame(SubmissionStatus::DONE, Submission::findOrFail($submissionid)->status);
        $this->assertDatabaseHas('buildproperties', ['buildid' => $build->id]);
        $this->assertDatabaseMissing('buildproperties', ['buildid' => $other_build->id]);
    }

    public function testKeepsBuildFileRowOfAnotherUploadWithTheSameContents(): void
    {
        $build = $this->project->builds()->create([
            'name' => 'twice',
            'uuid' => Str::uuid()->toString(),
            'siteid' => Site::factory()->create()->id,
        ]);
        $contents = '{"key": "value"}';
        BuildFile::create([
            'buildid' => $build->id,
            'type' => 'BuildPropertiesJSON',
            'md5' => md5($contents),
            'filename' => 'second.json',
        ]);
        [$filename, $submissionid] = $this->receiveDataFile($build->id, $contents, 'first.json');

        ProcessSubmission::dispatchSync($filename, $this->project->id, $build->id, md5($contents), $submissionid);

        $this->assertSame(SubmissionStatus::DONE, Submission::findOrFail($submissionid)->status);
        $this->assertDatabaseMissing('buildfile', ['buildid' => $build->id, 'filename' => 'first.json']);
        $this->assertDatabaseHas('buildfile', ['buildid' => $build->id, 'filename' => 'second.json']);
    }

    public function testFailsDataFileWhoseBuildWasDeleted(): void
    {
        $build = $this->project->builds()->create([
            'name' => 'deleted',
            'uuid' => Str::uuid()->toString(),
            'siteid' => Site::factory()->create()->id,
        ]);
        $contents = (string) file_get_contents(base_path('tests/Feature/Submission/data/gcov.tar'));
        $md5 = md5($contents);
        $filename = SubmissionUtils::dataFilename($this->project->name, '', 'GcovTar', $build->id, $md5, 'tar');
        Storage::put("inbox/{$filename}", $contents);
        $submissionid = Str::uuid7()->toString();
        SubmissionService::recordSubmission($submissionid, $this->project->id, $build->id, 'GcovTar', "inbox/{$filename}", 'gcov.tar');
        $build->delete();

        $this->assertThrows(
            fn () => ProcessSubmission::dispatchSync($filename, $this->project->id, $build->id, $md5, $submissionid),
            BadSubmissionException::class,
            'no longer exists',
        );

        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::FAILED, $submission->status);
        $this->assertSame("failed/{$filename}", $submission->path);
        $this->assertStringContainsString('no longer exists', (string) $submission->error);
    }

    public function testFailsJobWhenClaimFails(): void
    {
        Exceptions::fake();
        $filename = $this->receiveXmlFile((string) file_get_contents(base_path('tests/Feature/Submission/data/build.xml')), null, 'Build');
        $submissionid = $this->recordedSubmission()->id;
        $thrown = false;
        DB::listen(function (QueryExecuted $query) use (&$thrown): void {
            if (!$thrown && str_contains($query->sql, 'update "submissions"') && ($query->bindings[0] ?? null) === 'PROCESSING') {
                $thrown = true;
                throw new RuntimeException('Failed to claim the submission');
            }
        });

        $this->assertThrows(
            fn () => ProcessSubmission::dispatchSync($filename, $this->project->id, null, '', $submissionid),
            RuntimeException::class,
        );

        // failed() moves the file from inbox/, where the row says it still is.
        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::FAILED, $submission->status);
        $this->assertSame('Failed to claim the submission', $submission->error);
        $this->assertNull($submission->started_at);
        $this->assertSame("failed/{$filename}", $submission->path);
        $this->assertSame(["failed/{$filename}"], Storage::allFiles());
        $this->assertSame(0, $this->project->builds()->count());
    }

    /**
     * A second job for the same submission, such as one queued twice, leaves the first job's file alone.
     *
     * @throws BadSubmissionException
     */
    public function testDuplicateJobLeavesClaimedSubmissionAlone(): void
    {
        Exceptions::fake();
        $filename = $this->receiveXmlFile('Not a submission', null, 'Build');
        $submissionid = $this->recordedSubmission()->id;
        // Another job has claimed the submission and moved its file.
        $this->assertTrue(SubmissionService::claimSubmission($submissionid));
        Storage::move("inbox/{$filename}", "inprogress/{$filename}");

        (new ProcessSubmission($filename, $this->project->id, null, '', $submissionid))->handle();

        Exceptions::assertNothingReported();
        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::PROCESSING, $submission->status);
        $this->assertSame(1, $submission->attempts);
        $this->assertSame(["inprogress/{$filename}"], Storage::allFiles());
    }

    /**
     * @throws BadSubmissionException
     */
    public function testDuplicateJobLeavesFinishedSubmissionAlone(): void
    {
        Exceptions::fake();
        Config::set('cdash.backup_timeframe', 48);
        $filename = $this->receiveXmlFile((string) file_get_contents(base_path('tests/Feature/Submission/data/build.xml')), null, 'Build');
        $submissionid = $this->recordedSubmission()->id;
        ProcessSubmission::dispatchSync($filename, $this->project->id, null, '', $submissionid);
        $files = Storage::allFiles();

        (new ProcessSubmission($filename, $this->project->id, null, '', $submissionid))->handle();

        Exceptions::assertNothingReported();
        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::DONE, $submission->status);
        $this->assertSame(1, $submission->attempts);
        $this->assertSame($files, Storage::allFiles());
        $this->assertSame(1, $this->project->builds()->count());
    }

    /**
     * A job whose row has already failed, e.g. one retried with queue:retry, leaves the row and the file alone and
     * logs a warning.
     *
     * @throws BadSubmissionException
     */
    public function testJobOfFailedSubmissionLogsWarning(): void
    {
        $log = Log::spy();
        $filename = $this->receiveXmlFile('Not a submission', null, 'Build');
        $submissionid = $this->recordedSubmission()->id;
        SubmissionService::failSubmission($submissionid, ['error' => 'Invalid XML', 'path' => "failed/{$filename}"]);
        Storage::move("inbox/{$filename}", "failed/{$filename}");

        (new ProcessSubmission($filename, $this->project->id, null, '', $submissionid))->handle();

        $log->shouldHaveReceived('warning')->once();
        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::FAILED, $submission->status);
        $this->assertSame('Invalid XML', $submission->error);
        $this->assertSame(["failed/{$filename}"], Storage::allFiles());
    }

    /**
     * A job whose row was pruned while it waited still parses its file, as a job without a row does.
     */
    public function testParsesFileWhoseRowIsGone(): void
    {
        $filename = $this->receiveXmlFile((string) file_get_contents(base_path('tests/Feature/Submission/data/build.xml')), null, 'Build');
        $submission = $this->recordedSubmission();
        $submission->delete();

        ProcessSubmission::dispatchSync($filename, $this->project->id, null, '', $submission->id);

        $this->assertSame(1, $this->project->builds()->count());
        $this->assertSame(0, Submission::where('projectid', $this->project->id)->count());
    }

    /**
     * A failure after the parse committed, such as moving the file to parsed/, leaves the row done and the file
     * where it is.
     */
    public function testFailureAfterParseLeavesSubmissionDone(): void
    {
        Config::set('cdash.backup_timeframe', 48);
        $filename = $this->receiveXmlFile((string) file_get_contents(base_path('tests/Feature/Submission/data/build.xml')), null, 'Build');
        $submissionid = $this->recordedSubmission()->id;
        // A file named parsed keeps the job from moving the parsed file into a parsed/ directory.
        Storage::put('parsed', '');

        $this->assertThrows(fn () => ProcessSubmission::dispatchSync($filename, $this->project->id, null, '', $submissionid));

        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::DONE, $submission->status);
        $this->assertNull($submission->error);
        $this->assertSame(1, $this->project->builds()->count());
        $this->assertEqualsCanonicalizing(['parsed', "inprogress/{$filename}"], Storage::allFiles());
    }

    /**
     * A job that dies after requeueing a file has its failed() run much later, possibly while the next attempt
     * is still waiting to run.  It must leave the row and the file to that attempt.
     */
    public function testFailedJobLeavesRequeuedSubmissionAlone(): void
    {
        Exceptions::fake();
        $filename = $this->receiveXmlFile('Not a submission', null, 'Build');
        $submissionid = $this->recordedSubmission()->id;
        // The job claimed the submission, then requeued it.
        $this->assertTrue(SubmissionService::claimSubmission($submissionid));
        SubmissionService::requeueSubmission($submissionid, "inbox/{$filename}", now('UTC')->addHour());

        (new ProcessSubmission($filename, $this->project->id, null, '', $submissionid))->failed(new RuntimeException('Failed'));

        Exceptions::assertNothingReported();
        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::QUEUED, $submission->status);
        $this->assertSame(1, $submission->attempts);
        $this->assertNull($submission->error);
        $this->assertSame(["inbox/{$filename}"], Storage::allFiles());
    }

    /**
     * @return array<string,array{SubmissionStatus}>
     */
    public static function finishedOrRequeuedCases(): array
    {
        return [
            'done' => [SubmissionStatus::DONE],
            'failed' => [SubmissionStatus::FAILED],
            'requeued' => [SubmissionStatus::QUEUED],
        ];
    }

    #[DataProvider('finishedOrRequeuedCases')]
    public function testDoesNotFailFinishedOrRequeuedSubmission(SubmissionStatus $status): void
    {
        $this->receiveXmlFile('Not a submission', null, 'Build');
        $submission = $this->recordedSubmission();
        // With attempts above 0, a queued row counts as requeued.
        $submission->update(['status' => $status, 'attempts' => 1]);

        SubmissionService::failSubmission($submission->id, ['error' => 'Failed']);

        $submission->refresh();
        $this->assertSame($status, $submission->status);
        $this->assertNull($submission->error);
    }

    public function testFailedJobLeavesFailedSubmissionAlone(): void
    {
        Exceptions::fake();
        $filename = $this->receiveXmlFile('Not a submission', null, 'Build');
        $submissionid = $this->recordedSubmission()->id;
        // A later attempt failed with its own error and moved the file.
        SubmissionService::failSubmission($submissionid, ['error' => 'Invalid XML', 'path' => "failed/{$filename}"]);
        Storage::move("inbox/{$filename}", "failed/{$filename}");

        (new ProcessSubmission($filename, $this->project->id, null, '', $submissionid))->failed(new RuntimeException('Failed'));

        Exceptions::assertNothingReported();
        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::FAILED, $submission->status);
        $this->assertSame('Invalid XML', $submission->error);
        $this->assertSame(["failed/{$filename}"], Storage::allFiles());
    }

    /**
     * The parse and the row's done status commit together, so if marking the row done fails, the parse is rolled
     * back too.
     */
    public function testRollsBackParseWhenMarkingDoneFails(): void
    {
        $filename = $this->receiveXmlFile((string) file_get_contents(base_path('tests/Feature/Submission/data/build.xml')), null, 'Build');
        $submissionid = $this->recordedSubmission()->id;
        $thrown = false;
        DB::listen(function (QueryExecuted $query) use (&$thrown): void {
            if (!$thrown && str_contains($query->sql, 'update "submissions"') && ($query->bindings[0] ?? null) === 'DONE') {
                $thrown = true;
                throw new RuntimeException('Failed to mark the submission done');
            }
        });

        $this->assertThrows(
            fn () => ProcessSubmission::dispatchSync($filename, $this->project->id, null, '', $submissionid),
            RuntimeException::class,
        );

        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::FAILED, $submission->status);
        $this->assertSame('Failed to mark the submission done', $submission->error);
        $this->assertSame(0, $this->project->builds()->count());
        $this->assertSame(["failed/{$filename}"], Storage::allFiles());
    }

    /**
     * A best-effort write that fails, such as recording the parsed build's id, is reported, and the job still
     * finishes.
     */
    public function testFinishesJobWhenRecordingBuildFails(): void
    {
        Exceptions::fake();
        Config::set('cdash.backup_timeframe', 48);
        $filename = $this->receiveXmlFile((string) file_get_contents(base_path('tests/Feature/Submission/data/build.xml')), null, 'Build');
        $submissionid = $this->recordedSubmission()->id;
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'update "submissions" set "buildid"')) {
                throw new RuntimeException('Failed to record the build');
            }
        });

        ProcessSubmission::dispatchSync($filename, $this->project->id, null, '', $submissionid);

        Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'Failed to record the build');
        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::DONE, $submission->status);
        $this->assertNull($submission->buildid);
        $this->assertSame(Storage::files('parsed'), [$submission->path]);
        $this->assertSame(1, $this->project->builds()->count());
    }

    /**
     * If updating the build's pending count fails while requeueing a waiting Done.xml, the row's requeue is
     * undone, so failed() handles it like any other failure.
     */
    public function testRecordsDoneFileWhoseRequeueCountFailed(): void
    {
        [$filename, $buildid] = $this->receiveWaitingDoneFile();
        $submissionid = $this->recordedSubmission()->id;
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'update "pending_submissions" set "numfiles" = "numfiles" + 1')) {
                throw new RuntimeException('Failed to count the pending file');
            }
        });

        $this->assertThrows(
            fn () => ProcessSubmission::dispatchSync($filename, $this->project->id, $buildid, '', $submissionid),
            RuntimeException::class,
        );

        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::FAILED, $submission->status);
        $this->assertSame("failed/{$filename}", $submission->path);
        $this->assertTrue(Storage::fileExists("failed/{$filename}"));
        // The parse counted the Done.xml as no longer pending, and the requeue's count was rolled back.
        $this->assertSame(1, PendingSubmissions::where('buildid', $buildid)->value('numfiles'));
    }

    /**
     * If moving a waiting Done.xml back to inbox/ fails, its row isn't requeued either, so failed() handles it
     * like any other failure.
     */
    public function testRecordsDoneFileWhoseRequeueMoveFailed(): void
    {
        [$filename, $buildid] = $this->receiveWaitingDoneFile();
        $submissionid = $this->recordedSubmission()->id;
        // Once the requeue has updated the row, put a directory where the file would move to.
        DB::listen(function (QueryExecuted $query) use ($filename): void {
            if (in_array("inbox/{$filename}", $query->bindings, true) && !Storage::directoryExists("inbox/{$filename}")) {
                Storage::makeDirectory("inbox/{$filename}");
            }
        });

        $this->assertThrows(
            fn () => ProcessSubmission::dispatchSync($filename, $this->project->id, $buildid, '', $submissionid),
            UnableToMoveFile::class,
        );

        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::FAILED, $submission->status);
        $this->assertSame("failed/{$filename}", $submission->path);
        $this->assertTrue(Storage::fileExists("failed/{$filename}"));
        // The parse counted the Done.xml as no longer pending, and the requeue's count was rolled back.
        $this->assertSame(1, PendingSubmissions::where('buildid', $buildid)->value('numfiles'));
    }

    /**
     * @throws BadSubmissionException
     */
    public function testRecordsRequeuedDoneFile(): void
    {
        Queue::fake();
        [$filename, $buildid] = $this->receiveWaitingDoneFile();
        $submissionid = $this->recordedSubmission()->id;
        $locks = [];
        DB::listen(function (QueryExecuted $query) use (&$locks): void {
            if (str_contains($query->sql, 'pg_advisory_xact_lock')) {
                $locks[] = $query->bindings;
            }
        });

        (new ProcessSubmission($filename, $this->project->id, $buildid, '', $submissionid))->handle();

        // The parse and the requeue each hold the build's lock.
        $this->assertSame([[$buildid], [$buildid]], $locks);
        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::QUEUED, $submission->status);
        $this->assertSame("inbox/{$filename}", $submission->path);
        $this->assertSame(1, $submission->attempts);
        $this->assertTrue($submission->next_attempt_at?->isFuture());
        $this->assertNull($submission->finished_at);
        Queue::assertPushed(ProcessSubmission::class, fn (ProcessSubmission $job) => $job->filename === $filename
            && $job->submissionid === $submissionid);
    }

    /**
     * @throws BadSubmissionException
     */
    public function testRequeuesDoneFileFromPathInItsRow(): void
    {
        Queue::fake();
        [$filename, $buildid] = $this->receiveWaitingDoneFile();
        $submissionid = $this->recordedSubmission()->id;
        Storage::move("inbox/{$filename}", 'inbox/moved.xml');
        Submission::whereKey($submissionid)->update(['path' => 'inbox/moved.xml']);

        (new ProcessSubmission($filename, $this->project->id, $buildid, '', $submissionid))->handle();

        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::QUEUED, $submission->status);
        $this->assertSame('inbox/moved.xml', $submission->path);
        $this->assertSame(['inbox/moved.xml'], Storage::allFiles());
    }

    public function testRecordsDoneFileRequeuedOnSyncQueue(): void
    {
        [$filename, $buildid] = $this->receiveWaitingDoneFile();
        $submissionid = $this->recordedSubmission()->id;

        // The sync queue runs each retry inside the previous attempt, until Done.xml stops waiting.
        ProcessSubmission::dispatchSync($filename, $this->project->id, $buildid, '', $submissionid);

        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::DONE, $submission->status);
        // The first attempt and the five retries that DoneHandler allows.
        $this->assertSame(6, $submission->attempts);
        $this->assertNotNull($submission->finished_at);
    }

    public function testLoadsJobQueuedWithoutSubmissionId(): void
    {
        // Jobs queued before submissions were recorded don't have the property.
        $data = (new ProcessSubmission('file.xml', $this->project->id, null, '', Str::uuid7()->toString()))->__serialize();
        unset($data['submissionid']);
        $job = (new ReflectionClass(ProcessSubmission::class))->newInstanceWithoutConstructor();
        $job->__unserialize($data);

        $this->assertSame('file.xml', $job->filename);
        $this->assertNull($job->submissionid);
    }
}
