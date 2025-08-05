<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSubmission;
use App\Utils\AuthTokenUtil;
use CDash\Model\Project;
use Illuminate\Console\Command;
use League\Flysystem\UnableToMoveFile;
use Storage;

class QueueSubmissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'submission:queue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue submitted files in the inbox directory';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Queue the "build metadata" JSON files first, so they have a chance
        // to get parsed before the subsequent payload files.
        foreach (Storage::files('inbox') as $inboxFile) {
            if (!str_contains($inboxFile, '_-_build-metadata_-_') || !str_contains($inboxFile, '.json')) {
                continue;
            }
            $this->queueFile($inboxFile);
        }

        // Iterate over our inbox files again, queueing them for parsing.
        foreach (Storage::files('inbox') as $inboxFile) {
            if (str_contains($inboxFile, '_-_build-metadata_-_') && str_contains($inboxFile, '.json')) {
                continue;
            }
            $this->queueFile($inboxFile);
        }
    }

    private function queueFile($inboxFile): void
    {
        $filename = str_replace('inbox/', '', $inboxFile);
        $pos = strpos($filename, '_-_');
        if ($pos === false) {
            try {
                Storage::move("inbox/{$filename}", "failed/{$filename}");
            } catch (UnableToMoveFile $e) {
                report($e);
            }
            echo "Could not extract projectname from $filename\n";
            return;
        }

        $projectname = substr($filename, 0, $pos);
        $project = new Project();
        $project->FindByName($projectname);
        if (!$project->Id) {
            try {
                Storage::move("inbox/{$filename}", "failed/{$filename}");
            } catch (UnableToMoveFile $e) {
                report($e);
            }
            echo "Could not find project $projectname\n";
            return;
        }

        if ($project->AuthenticateSubmissions) {
            // Get authtoken hash from filename.
            $begin = $pos + 3;
            $end = strpos($filename, '_-_', $begin);
            if ($end === false) {
                try {
                    Storage::move("inbox/{$filename}", "failed/{$filename}");
                } catch (UnableToMoveFile $e) {
                    report($e);
                }
                echo "Could not extract authtoken from $filename\n";
                return;
            }
            $len = $end - $begin;
            if (!AuthTokenUtil::checkToken(substr($filename, $begin, $len), $project->Id)) {
                try {
                    Storage::move("inbox/{$filename}", "failed/{$filename}");
                } catch (UnableToMoveFile $e) {
                    report($e);
                }
                echo "Invalid authentication token for $filename\n";
                return;
            }
        }

        // Get md5 from filename (if any).
        $md5 = '';
        $last_underscore_pos = strrpos($filename, '_-_');
        if ($last_underscore_pos !== false) {
            $offset = $last_underscore_pos - strlen($filename) - 3;
            $next_to_last_underscore_pos = strrpos($filename, '_-_', $offset);
            if ($next_to_last_underscore_pos !== false) {
                $next_to_last_underscore_pos += 3;
                $len = $last_underscore_pos - $next_to_last_underscore_pos;
                $md5 = substr($filename, $next_to_last_underscore_pos, $len);
            }
        }

        ProcessSubmission::dispatch($filename, $project->Id, null, $md5);
    }
}
