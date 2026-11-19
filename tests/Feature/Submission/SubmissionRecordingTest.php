<?php

namespace Tests\Feature\Submission;

use App\Enums\SubmissionStatus;
use App\Exceptions\BadSubmissionException;
use App\Jobs\ProcessSubmission;
use App\Models\PendingSubmissions;
use App\Models\Project;
use App\Models\Site;
use App\Models\Submission;
use App\Services\SubmissionService;
use App\Utils\SubmissionUtils;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToMoveFile;
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

    public function testKeepsPathOfFailedFileThatWasNotMoved(): void
    {
        Exceptions::fake();
        $filename = $this->receiveXmlFile('Not a submission', null, 'Build');
        $submissionid = $this->recordedSubmission()->id;

        // The file is still in the inbox, so it can't be moved from inprogress/ to failed/.
        (new ProcessSubmission($filename, $this->project->id, null, '', $submissionid))->failed(new RuntimeException('Failed'));

        Exceptions::assertReported(UnableToMoveFile::class);
        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::FAILED, $submission->status);
        $this->assertSame("inbox/{$filename}", $submission->path);
        $this->assertSame('Failed', $submission->error);
    }

    public function testFinishesJobWhenRecordingFails(): void
    {
        Exceptions::fake();
        $filename = $this->receiveXmlFile((string) file_get_contents(base_path('tests/Feature/Submission/data/build.xml')), null, 'Build');
        $submission = $this->recordedSubmission();
        // Make the job's first update fail, with an attempt count that overflows the column.
        $submission->update(['attempts' => 2147483647]);

        ProcessSubmission::dispatchSync($filename, $this->project->id, null, '', $submission->id);

        Exceptions::assertReported(QueryException::class);
        $submission->refresh();
        $this->assertNull($submission->started_at);
        $this->assertSame(SubmissionStatus::DONE, $submission->status);
        $this->assertSame($this->project->builds()->firstOrFail()->id, $submission->buildid);
    }

    /**
     * @throws BadSubmissionException
     */
    public function testRecordsRequeuedDoneFile(): void
    {
        Queue::fake();
        [$filename, $buildid] = $this->receiveWaitingDoneFile();
        $submissionid = $this->recordedSubmission()->id;

        (new ProcessSubmission($filename, $this->project->id, $buildid, '', $submissionid))->handle();

        $submission = Submission::findOrFail($submissionid);
        $this->assertSame(SubmissionStatus::QUEUED, $submission->status);
        $this->assertSame("inbox/{$filename}", $submission->path);
        $this->assertSame(1, $submission->attempts);
        $this->assertTrue($submission->next_attempt_at?->isFuture());
        $this->assertNull($submission->finished_at);
        Queue::assertPushed(ProcessSubmission::class, fn (ProcessSubmission $job) => $job->filename === $filename
            && $job->submissionid === $submissionid);
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
