<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

require_once 'include/do_submit.php';

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
        $handler = do_submit($this->filename, $this->projectid, $this->buildid, $this->expected_md5, true);

        if (!is_object($handler)) {
            return;
        }

        if (property_exists($handler, 'backupFileName') && $handler->backupFileName) {
            $pos = strpos($handler->backupFileName, 'inbox/');
            if ($pos === false) {
                \Log::error("Submission file ($handler->backupFileName} located outside of in app/storage/inbox");
            } else {
                $parsed_filename = substr_replace($handler->backupFileName, 'parsed/', $pos, strlen('inbox/'));
                Storage::move($handler->backupFileName, $parsed_filename);
                Storage::delete($this->filename);
            }
        }
    }
}
