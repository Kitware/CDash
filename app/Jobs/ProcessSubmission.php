<?php

namespace App\Jobs;

use AbstractSubmissionHandler;
use ActionableBuildInterface;
use App\Exceptions\XMLValidationException;
use App\Models\SuccessfulJob;
use App\Utils\UnparsedSubmissionProcessor;
use BuildPropertiesJSONHandler;
use CDash\Model\Build;
use CDash\Model\PendingSubmissions;
use CDash\Model\Repository;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RetryHandler;
use Throwable;
use UpdateHandler;

require_once 'include/ctestparser.php';
require_once 'include/sendemail.php';

class ProcessSubmission implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    public $filename;
    public string $localFilename = '';
    public $projectid;
    public $buildid;
    public $expected_md5;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($filename, $projectid, $buildid, $expected_md5)
    {
        $this->timeout = config('cdash.queue_timeout');

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
    }

    private function renameSubmissionFile($src, $dst): bool
    {
        if (config('cdash.remote_workers')) {
            $url = url('/api/v1/deleteSubmissionFile.php');
            $client = new Client();
            $response = $client->request('DELETE', $url, [
                'query' => ['filename' => encrypt($src), 'dest' => encrypt($dst)],
            ]);
            return $response->getStatusCode() === 200;
        } else {
            return Storage::move($src, $dst);
        }
    }

    private function deleteSubmissionFile($filename): bool
    {
        if (config('cdash.remote_workers')) {
            $url = url('/api/v1/deleteSubmissionFile.php');
            $client = new Client();
            $response = $client->request('DELETE', $url, [
                'query' => ['filename' => encrypt($filename)],
            ]);
            return $response->getStatusCode() === 200;
        } else {
            return Storage::delete($filename);
        }
    }

    private function requeueSubmissionFile($buildid): bool
    {
        if (config('cdash.remote_workers')) {
            $url = url('/api/v1/requeueSubmissionFile.php');
            $client = new Client();
            $response = $client->request('POST', $url, [
                'query' => [
                    'filename' => encrypt($this->filename),
                    'buildid' => $buildid,
                    'projectid' => $this->projectid,
                ],
            ]);
            if ($this->localFilename !== '') {
                unlink($this->localFilename);
            }
            return $response->getStatusCode() == 200;
        } else {
            // Increment retry count.
            $retry_handler = new RetryHandler(Storage::path("inprogress/{$this->filename}"));
            $retry_handler->increment();

            // Move file back to inbox.
            Storage::move("inprogress/{$this->filename}", "inbox/{$this->filename}");

            // Requeue the file with exponential backoff.
            PendingSubmissions::IncrementForBuildId($this->buildid);
            $delay = pow(config('cdash.retry_base'), $retry_handler->Retries);
            if (config('queue.default') === 'sqs-fifo') {
                // Special handling for sqs-fifo, which does not support per-message delays.
                sleep(10);
                self::dispatch($this->filename, $this->projectid, $buildid, md5_file(Storage::path("inbox/{$this->filename}")));
            } else {
                self::dispatch($this->filename, $this->projectid, $buildid, md5_file(Storage::path("inbox/{$this->filename}")))->delay(now()->addSeconds($delay));
            }

            return true;
        }
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws XMLValidationException
     */
    public function handle()
    {
        // Move file from inbox to inprogress.
        if (!$this->renameSubmissionFile("inbox/{$this->filename}", "inprogress/{$this->filename}")) {
            // Return early if the rename operation fails.
            // Presumably this means some other runner picked up the job before us.
            return;
        }

        // Parse file.
        $handler = $this->doSubmit("inprogress/{$this->filename}", $this->projectid, $this->buildid, $this->expected_md5, true);

        if (!is_object($handler)) {
            return;
        }

        // Resubmit the file if necessary.
        if (is_a($handler, 'DoneHandler') && $handler->shouldRequeue()) {
            $this->requeueSubmissionFile($handler->getBuild()->Id);
        }

        if ((int) config('cdash.backup_timeframe') === 0) {
            // We are configured not to store parsed files. Delete it now.
            $this->deleteSubmissionFile("inprogress/{$this->filename}");
        } else {
            // Move the file to a pretty name in the parsed directory.
            $this->renameSubmissionFile("inprogress/{$this->filename}", "parsed/{$handler->backupFileName}");
        }

        if ((bool) config('cdash.remote_workers') && $this->localFilename !== '') {
            unlink($this->localFilename);
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
        $this->renameSubmissionFile("inprogress/{$this->filename}", "failed/{$this->filename}");

        if ((bool) config('cdash.remote_workers') && $this->localFilename !== '') {
            unlink($this->localFilename);
        }
    }

    /**
     * This method could be running on a worker that is either remote or local, so it accepts
     * a file handle or a filename that it can query the CDash API for.
     *
     * @throws XMLValidationException
     **/
    private function doSubmit($filename, $projectid, $buildid = null, $expected_md5 = ''): AbstractSubmissionHandler|UnparsedSubmissionProcessor|false
    {
        $filehandle = $this->getSubmissionFileHandle($filename);
        if ($filehandle === false) {
            return false;
        }

        // Special handling for "build metadata" files created while the DB was down.
        if (str_contains($filename, '_-_build-metadata_-_') && str_contains($filename, '.json')) {
            $handler = new UnparsedSubmissionProcessor();
            $handler->backupFileName = $this->filename;
            $handler->deserializeBuildMetadata($filehandle);
            fclose($filehandle);
            $handler->initializeBuild();
            $handler->populateBuildFileRow();
            return $handler;
        }

        // Parse the XML file
        $handler = ctest_parse($filehandle, $filename, $projectid, $expected_md5, $buildid);
        fclose($filehandle);
        unset($filehandle);

        // this is the md5 checksum fail case
        if ($handler == false) {
            // no need to log an error since ctest_parse already did
            return false;
        }

        $build = $handler->getBuild();
        $pendingSubmissions = new PendingSubmissions();
        $pendingSubmissions->Build = $build;
        if ($pendingSubmissions->Exists()) {
            $pendingSubmissions->Decrement();
        }

        // Set status on repository.
        if ($handler instanceof UpdateHandler
            || $handler instanceof BuildPropertiesJSONHandler
        ) {
            Repository::setStatus($build, false);
        }

        // Send emails about update problems.
        if ($handler instanceof UpdateHandler) {
            send_update_email($handler, intval($projectid));
        }

        // Send more general build emails.
        if ($handler instanceof ActionableBuildInterface) {
            sendemail($handler, intval($projectid));
        }

        return $handler;
    }

    /**
     * Given a filename, query the CDash API for its contents and return
     * a read-only file handle.
     * This is used by workers running on other machines that need access to build xml.
     **/
    private function getRemoteSubmissionFileHandle($filename)
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $_t = tempnam(Storage::path('inbox'), 'cdash-submission-');
        $this->localFilename = "{$_t}.{$ext}";
        rename($_t, $this->localFilename);

        $client = new Client();
        $url = url('/api/v1/getSubmissionFile.php');
        $response = $client->request('GET', $url, [
            'query' => ['filename' => encrypt($filename)],
            'sink' => $this->localFilename,
        ]);

        if ($response->getStatusCode() === 200) {
            // @todo I'm sure Guzzle can be used to return a file handle from the stream, but for now
            // I'm just creating a temporary file with the output
            return fopen($this->localFilename, 'r');
        } else {
            // Log the status code and requested filename.
            // (404 status means it's already been processed).
            \Log::warning('Failed to retrieve a file handle from filename ' .
                    $filename . '(' . (string) $response->getStatusCode() . ')');
            return false;
        }
    }

    private function getSubmissionFileHandle($filename)
    {
        if ((bool) config('cdash.remote_workers') && is_string($filename)) {
            return $this->getRemoteSubmissionFileHandle($filename);
        } elseif (Storage::exists($filename)) {
            return fopen(Storage::path($filename), 'r');
        } else {
            \Log::error('Failed to get a file handle for submission (was type ' . gettype($filename) . ')');
            return false;
        }
    }
}
