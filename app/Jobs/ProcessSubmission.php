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
use League\Flysystem\UnableToMoveFile;
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

        // Move file back to inbox.
        Storage::move("inprogress/{$this->filename}", "inbox/{$this->filename}");

        // Requeue the file with exponential backoff.  The row is updated first, because the sync queue runs
        // the next attempt immediately.
        PendingSubmissions::where('buildid', $this->buildid)->increment('numfiles');
        $next_attempt_at = now('UTC')->addSeconds(((int) config('cdash.retry_base')) ** $retry_handler->Retries);
        SubmissionService::updateSubmission($this->submissionid, [
            'status' => SubmissionStatus::QUEUED,
            'path' => "inbox/{$this->filename}",
            'next_attempt_at' => $next_attempt_at,
        ]);
        self::dispatch($this->filename, $this->projectid, $this->buildid, $this->expected_md5, $this->submissionid)->delay($next_attempt_at);

        return true;
    }

    /**
     * Execute the job.
     *
     * @throws BadSubmissionException
     */
    public function handle(): void
    {
        // Move file from inbox to inprogress.
        if (!$this->renameSubmissionFile("inbox/{$this->filename}", "inprogress/{$this->filename}")) {
            // Return early if the rename operation fails.
            // Presumably this means some other runner picked up the job before us.
            return;
        }

        // Times are taken in UTC, because parsing calls the legacy Project::Fill(), which sets PHP's default
        // timezone to the project's nightly timezone.
        SubmissionService::updateSubmission($this->submissionid, [
            'status' => SubmissionStatus::PROCESSING,
            'path' => "inprogress/{$this->filename}",
            'attempts' => DB::raw('attempts + 1'),
            'started_at' => now('UTC'),
        ]);

        // Project existence validation occurs prior to jobs being queued, so we expect this to always exist.
        $project = Project::findOrFail((int) $this->projectid);

        // Parse file.
        $handler = $this->doSubmit("inprogress/{$this->filename}", $project, $this->buildid, $this->expected_md5);

        if (!is_object($handler)) {
            SubmissionService::updateSubmission($this->submissionid, [
                'status' => SubmissionStatus::FAILED,
                'error' => 'Failed to locate submission file',
                'finished_at' => now('UTC'),
            ]);
            return;
        }

        // Resubmit the file if necessary.
        if (is_a($handler, DoneHandler::class) && $handler->shouldRequeue()) {
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

        SubmissionService::updateSubmission($this->submissionid, [
            'status' => SubmissionStatus::DONE,
            'path' => $path,
            'finished_at' => now('UTC'),
        ]);

        // Record the build the file was parsed into, which may differ from the one named at upload, if any.
        // This is a separate update so that a build deleted in the meantime can't keep the row from being
        // marked done.
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
        // The message can include bytes from the file, and Postgres rejects text with invalid UTF-8 or null
        // bytes.  The full message is in the log.
        $attributes = [
            'status' => SubmissionStatus::FAILED,
            'error' => Str::limit(str_replace("\0", '', mb_scrub($exception->getMessage(), 'UTF-8')), 1000),
            'finished_at' => now('UTC'),
        ];
        try {
            $this->renameSubmissionFile("inprogress/{$this->filename}", "failed/{$this->filename}");
            $attributes['path'] = "failed/{$this->filename}";
        } catch (UnableToMoveFile $e) {
            report($e);
        }
        SubmissionService::updateSubmission($this->submissionid, $attributes);
    }

    /**
     * Parse a submission file, then update the build's count of pending files and send notifications.
     *
     * @throws BadSubmissionException
     **/
    private function doSubmit($filename, Project $project, $buildid = null, $expected_md5 = ''): AbstractSubmissionHandler|UnparsedSubmissionProcessor|false
    {
        if (!Storage::exists($filename)) {
            Log::error("Failed to locate submission file {$filename}");
            return false;
        }

        // Special handling for "build metadata" files created while the DB was down.
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

        $handler = DB::transaction(fn () => self::parseSubmissionFile($filename, $project, $buildid, $expected_md5));

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
