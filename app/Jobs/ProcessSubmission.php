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

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Move file from inbox to inprogress.
        if (!Storage::move("inbox/{$this->filename}", "inprogress/{$this->filename}")) {
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
            // Increment retry count.
            $retry_handler = new \RetryHandler(Storage::path("inprogress/{$this->filename}"));
            $retry_handler->Increment();
            $build = get_build_from_handler($handler);

            // Move file back to inbox.
            Storage::move("inprogress/{$this->filename}", "inbox/{$this->filename}");

            // Requeue the file.
            PendingSubmissions::IncrementForBuildId($this->buildid);
            self::dispatch($this->filename, $this->projectid, $build->Id, md5_file(Storage::path("inbox/{$this->filename}")));
            return;
        }

        if (config('cdash.backup_timeframe') === 0 && is_file($filename)) {
            // We are configured not to store parsed files. Delete it now.
            Storage::delete("inprogress/{$this->filename}");
        } else {
            // Move the file to a pretty name in the parsed directory.
            Storage::move("inprogress/{$this->filename}", "parsed/{$handler->backupFileName}");
        }

        unset($handler);
        $handler = null;
    }
}
