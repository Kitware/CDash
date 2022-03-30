<?php

namespace App\Jobs;

use CDash\Model\PendingSubmissions;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

require_once 'include/do_submit.php';
require_once 'xml_handlers/done_handler.php';
require_once 'xml_handlers/retry_handler.php';

class ProcessSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $filename;
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
        $this->filename = $filename;
        $this->projectid = $projectid;
        $this->buildid = $buildid;
        $this->expected_md5 = $expected_md5;
    }

    private function renameSubmissionFile($src, $dst)
    {
        if (config('cdash.remote_workers')) {
            $url = config('app.url') . '/api/v1/deleteSubmissionFile.php';
            $client = new \GuzzleHttp\Client();
            $response = $client->request('DELETE', $url, ['query' => ['filename' => $src, 'dest' => $dst]]);
            return $response->getStatusCode() == 200;
        } else {
            return Storage::move($src, $dst);
        }
        return false;
    }

    private function deleteSubmissionFile($filename)
    {
        if (config('cdash.remote_workers')) {
            $url = config('app.url') . '/api/v1/deleteSubmissionFile.php';
            $client = new \GuzzleHttp\Client();
            $response = $client->request('DELETE', $url, ['query' => ['filename' => $filename]]);
            return $response->getStatusCode() == 200;
        } else {
            return Storage::delete($filename);
        }
        return false;
    }

    private function requeueSubmissionFile($buildid)
    {
        if (config('cdash.remote_workers')) {
            $url = config('app.url') . '/api/v1/requeueSubmissionFile.php';
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $url, ['query' => [
                    'filename' => $this->filename,
                    'buildid' => $buildid,
                    'projectid' => $this->projectid
                ]]);
            return $response->getStatusCode() == 200;
        } else {
            // Increment retry count.
            $retry_handler = new \RetryHandler(Storage::path("inprogress/{$this->filename}"));
            $retry_handler->Increment();

            // Move file back to inbox.
            Storage::move("inprogress/{$this->filename}", "inbox/{$this->filename}");

            // Requeue the file.
            PendingSubmissions::IncrementForBuildId($this->buildid);
            self::dispatch($this->filename, $this->projectid, $buildid, md5_file(Storage::path("inbox/{$this->filename}")));
            return true;
        }
    }

    /**
     * Execute the job.
     *
     * @return void
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
        $handler = do_submit("inprogress/{$this->filename}", $this->projectid, $this->buildid, $this->expected_md5, true);

        if (!is_object($handler)) {
            return;
        }

        // Resubmit the file if necessary.
        if (is_a($handler, 'DoneHandler') && $handler->shouldRequeue()) {
            $build = get_build_from_handler($handler);
            return $this->requeueSubmissionFile($build->Id);
        }

        if (config('cdash.backup_timeframe') === 0 && is_file($filename)) {
            // We are configured not to store parsed files. Delete it now.
            $this->deleteSubmissionFile("inprogress/{$this->filename}");
        } else {
            // Move the file to a pretty name in the parsed directory.
            $this->renameSubmissionFile("inprogress/{$this->filename}", "parsed/{$handler->backupFileName}");
        }

        unset($handler);
        $handler = null;
    }

    /**
     * The job failed to process.
     *
     * @param  Exception  $exception
     * @return void
     */
    public function failed(\Exception $exception)
    {
        \Log::warning("Failed to process {$this->filename}");
        $this->renameSubmissionFile("inprogress/{$this->filename}", "failed/{$this->filename}");
    }
}
