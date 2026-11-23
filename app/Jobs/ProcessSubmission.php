<?php

namespace App\Jobs;

use App\Enums\SubmissionStatus;
use App\Enums\UnparsedSubmissionType;
use App\Exceptions\BadSubmissionException;
use App\Http\Submission\Handlers\AbstractSubmissionHandler;
use App\Http\Submission\Handlers\DoneHandler;
use App\Http\Submission\Handlers\RetryHandler;
use App\Models\Build;
use App\Models\BuildFile;
use App\Models\PendingSubmissions;
use App\Models\Project;
use App\Models\Site;
use App\Models\Submission;
use App\Models\SuccessfulJob;
use App\Services\SubmissionService;
use App\Utils\SubmissionUtils;
use App\Utils\UnparsedSubmissionProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\FilesystemException;
use Throwable;

class ProcessSubmission implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $filename;
    public $projectid;
    public $buildid;
    public $expected_md5;
    public ?string $submissionid = null;

    /**
     * The job isn't retried: a retry would find its own row already claimed and return without parsing, and the
     * first attempt has already moved the file out of inbox/.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($filename, $projectid, $buildid, $expected_md5, ?string $submissionid = null)
    {
        $context = [];
        if (isset($projectid)) {
            $context['projectid'] = $projectid;
        }
        if (isset($buildid)) {
            $context['buildid'] = $buildid;
        }
        Log::shareContext($context);

        $this->filename = $filename;
        $this->projectid = $projectid;
        $this->buildid = $buildid;
        $this->expected_md5 = $expected_md5;
        $this->submissionid = $submissionid;
    }

    private function renameSubmissionFile($src, $dst): bool
    {
        return Storage::move($src, $dst);
    }

    private function deleteSubmissionFile($filename): bool
    {
        return Storage::delete($filename);
    }

    private function requeueSubmissionFile(): bool
    {
        // Increment retry count.
        $retry_handler = new RetryHandler("inprogress/{$this->filename}");
        $retry_handler->increment();

        // Requeue the file with exponential backoff.  The row's update, the build's pending count and the file's
        // move back to inbox/ run in one transaction, so if any of them fails, the others roll back and failed()
        // finds the row processing and the file in inprogress/.  The transaction holds the build's lock, so it
        // can't deadlock with the parse of another file uploaded for this build, which may delete the build.  The
        // job is dispatched last, outside the transaction, because the sync queue runs the next attempt
        // immediately.
        $next_attempt_at = now('UTC')->addSeconds(((int) config('cdash.retry_base')) ** $retry_handler->Retries);
        $buildid = $this->buildid === null ? null : (int) $this->buildid;
        SubmissionService::withBuildLock($buildid, function () use ($next_attempt_at): void {
            if ($this->submissionid !== null) {
                SubmissionService::requeueSubmission($this->submissionid, "inbox/{$this->filename}", $next_attempt_at);
            }
            PendingSubmissions::where('buildid', $this->buildid)->increment('numfiles');
            Storage::move("inprogress/{$this->filename}", "inbox/{$this->filename}");
        });
        self::dispatch($this->filename, $this->projectid, $this->buildid, $this->expected_md5, $this->submissionid)->delay($next_attempt_at);

        return true;
    }

    /**
     * Claim the submission's row, so that no other job processes its file.  Returns false if the row isn't
     * queued, e.g. because another job claimed it.  A job whose row no longer exists, e.g. because it was
     * pruned while the job waited in a long backlog, is detached from it and carries on as a job without one.
     * Detaching only affects this run: failed() runs on a fresh copy of the job, whose id then matches no row.
     */
    private function claimOrDetachSubmission(string $submissionid): bool
    {
        if (SubmissionService::claimSubmission($submissionid)) {
            return true;
        }

        try {
            $submission = Submission::find($submissionid, ['status']);
        } catch (Throwable $e) {
            // Failing here would let failed() mark the other job's row failed and move its file.
            report($e);
            return false;
        }
        if ($submission !== null) {
            // A failed row usually means the job was retried with queue:retry, which can't work: the file has
            // already moved to failed/.
            $message = "Not processing submission {$submissionid}, whose status is {$submission->status->value}";
            $submission->status === SubmissionStatus::FAILED ? Log::warning($message) : Log::info($message);
            return false;
        }

        $this->submissionid = null;
        return true;
    }

    /**
     * Whether a parsed file is a Done.xml that has to wait for its build's other files, and is requeued.
     */
    private static function waitsForOtherFiles(object $handler): bool
    {
        return $handler instanceof DoneHandler && $handler->shouldRequeue();
    }

    /**
     * Execute the job.
     *
     * @throws BadSubmissionException
     */
    public function handle(): void
    {
        if ($this->submissionid !== null && !$this->claimOrDetachSubmission($this->submissionid)) {
            return;
        }

        // Move file from inbox to inprogress.
        if (!$this->renameSubmissionFile("inbox/{$this->filename}", "inprogress/{$this->filename}")) {
            // Return early if the rename operation fails.
            // Presumably this means some other runner picked up the job before us.
            return;
        }
        SubmissionService::updateSubmission($this->submissionid, ['path' => "inprogress/{$this->filename}"]);

        // Project existence validation occurs prior to jobs being queued, so we expect this to always exist.
        $project = Project::findOrFail((int) $this->projectid);

        // Parse file.
        $handler = $this->doSubmit("inprogress/{$this->filename}", $project, $this->buildid, $this->expected_md5);

        if (!is_object($handler)) {
            SubmissionService::failSubmission($this->submissionid, [
                'error' => 'Failed to locate submission file',
                'finished_at' => now('UTC'),
            ]);
            return;
        }

        // Resubmit the file if necessary.
        if (self::waitsForOtherFiles($handler)) {
            $this->requeueSubmissionFile();
            return;
        }

        // A deleted file's row keeps the path it had last.
        $path = "inprogress/{$this->filename}";
        if ((int) config('cdash.backup_timeframe') === 0) {
            // We are configured not to store parsed files. Delete it now.
            $this->deleteSubmissionFile($path);
        } else {
            // Move the file to a pretty name in the parsed directory.
            $backup_path = "parsed/{$handler->backupFileName}";
            $this->renameSubmissionFile($path, $backup_path);
            $path = $backup_path;
        }

        SubmissionService::updateSubmission($this->submissionid, ['path' => $path]);

        // Record the build the file was parsed into, which may differ from the one named at upload, if any.
        // This is a separate update so that a build deleted in the meantime can't keep the row from recording
        // where the file is.
        $buildid = $handler instanceof AbstractSubmissionHandler ? $handler->getBuild()->Id : null;
        if (is_numeric($buildid) && (int) $buildid > 0) {
            SubmissionService::updateSubmission($this->submissionid, ['buildid' => (int) $buildid]);
        }

        unset($handler);
        $handler = null;

        // Store record for successful job if asynchronously parsing.
        if (config('queue.default') !== 'sync') {
            SuccessfulJob::create([
                'filename' => $this->filename,
            ]);
        }
    }

    /**
     * The job failed to process.
     */
    public function failed(Throwable $exception): void
    {
        Log::warning("Failed to process {$this->filename} with message: {$exception}");

        // A row that's done, requeued for a later attempt, or already failed is left as it is, with its file where
        // it is.
        if (SubmissionService::isSubmissionFinishedOrRequeued($this->submissionid)) {
            return;
        }

        // The message can include bytes from the file, and Postgres rejects text with invalid UTF-8 or null
        // bytes.  The full message is in the log.
        $attributes = [
            'error' => Str::limit(str_replace("\0", '', mb_scrub($exception->getMessage(), 'UTF-8')), 1000),
            'finished_at' => now('UTC'),
        ];
        try {
            $this->renameSubmissionFile("inprogress/{$this->filename}", "failed/{$this->filename}");
            $attributes['path'] = "failed/{$this->filename}";
        } catch (FilesystemException $e) {
            report($e);
        }
        SubmissionService::failSubmission($this->submissionid, $attributes);
    }

    /**
     * Parse a submission file and, unless it's a Done.xml that has to wait for its build's other files, mark its
     * row done in the same transaction.  Then update the build's count of pending files and send notifications.
     *
     * @throws BadSubmissionException
     **/
    private function doSubmit($filename, Project $project, $buildid = null, $expected_md5 = ''): AbstractSubmissionHandler|UnparsedSubmissionProcessor|false
    {
        if (!Storage::exists($filename)) {
            Log::error("Failed to locate submission file {$filename}");
            return false;
        }

        // Special handling for "build metadata" files created while the DB was down.  Only QueueSubmissions
        // queues these, so their jobs have no row.
        if (SubmissionUtils::isBuildMetadataFilename($this->filename)) {
            $filehandle = Storage::readStream($filename) ?? throw new BadSubmissionException("Failed to open file {$filename}");
            $handler = new UnparsedSubmissionProcessor();
            $handler->backupFileName = $this->filename;
            $handler->deserializeBuildMetadata($filehandle);
            fclose($filehandle);
            $handler->initializeBuild();
            $handler->populateBuildFileRow();
            return $handler;
        }

        $handler = DB::transaction(function () use ($filename, $project, $buildid, $expected_md5) {
            $handler = self::parseSubmissionFile($filename, $project, $buildid, $expected_md5);

            // Mark the row done in the same transaction, so that a crash can't leave a parsed file that isn't
            // marked done.  A Done.xml that has to wait for its build's other files is requeued instead.
            if ($this->submissionid !== null && !self::waitsForOtherFiles($handler)) {
                SubmissionService::markSubmissionDone($this->submissionid);
            }

            return $handler;
        });

        $build = $handler->getBuild();
        PendingSubmissions::where('buildid', $build->Id ?? -1)->decrement('numfiles');

        SubmissionService::setRepositoryStatus($handler);
        SubmissionService::sendBuildEmails($handler, $project);

        return $handler;
    }

    /**
     * Parse a submission file as an unparsed data file if it has a buildfile row, or as CTest XML otherwise.
     *
     * @throws BadSubmissionException
     */
    private static function parseSubmissionFile(string $filename, Project $project, ?int $buildid, ?string $expected_md5): AbstractSubmissionHandler
    {
        $buildfile = null;
        if ($expected_md5 !== null) {
            if ($buildid === null) {
                $buildfile = BuildFile::where(['md5' => $expected_md5])->first();
            } else {
                $buildfile = BuildFile::where(['buildid' => $buildid, 'md5' => $expected_md5])->first();
            }
        }
        if ($buildfile === null) {
            return SubmissionService::parseCTestXml($filename, $project, $buildid);
        }

        $type = UnparsedSubmissionType::tryFrom($buildfile->type);
        if ($type === null) {
            Log::error("No handler include file for {$buildfile->type}");
            $buildfile->delete();
            return SubmissionService::parseCTestXml($filename, $project, $buildid);
        }

        /** @var Build $build */
        $build = $buildfile->build()->firstOrFail();
        /** @var Site $site */
        $site = $build->site()->firstOrFail();

        $handler = SubmissionService::parseDataFile($filename, $build->id, $type);

        // Another job may have parsed this file while this one waited for the build's lock.
        if (BuildFile::whereKey($buildfile->getKey())->delete() === 0) {
            throw new BadSubmissionException("Submission file {$filename} was already parsed");
        }

        $handler->backupFileName = SubmissionService::generateBackupFileName(
            $project->name,
            '',
            $build->name,
            $site->name,
            $build->stamp,
            $buildfile->filename,
        );

        return $handler;
    }
}
