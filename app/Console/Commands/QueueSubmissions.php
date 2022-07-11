<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSubmission;
use CDash\Model\Project;

use Illuminate\Console\Command;

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
     *
     * @return mixed
     */
    public function handle()
    {
        // Queue the "build metadata" JSON files first, so they have a chance
        // to get parsed before the subsequent payload files.
        foreach (\Storage::files('inbox') as $inboxFile) {
            if (strpos($inboxFile, '_build_metadata_') === false || strpos($inboxFile, '.json') === false) {
                continue;
            }
            $this->queueFile($inboxFile);
        }

        // Iterate over our inbox files again, queueing them for parsing.
        foreach (\Storage::files('inbox') as $inboxFile) {
            if (strpos($inboxFile, '_build_metadata_') !== false && strpos($inboxFile, '.json') !== false) {
                continue;
            }
            $this->queueFile($inboxFile);
        }
    }

    private function queueFile($inboxFile)
    {
        $filename = str_replace('inbox/', '', $inboxFile);
        $pos = strpos($filename, '_');
        if ($pos === false) {
            echo "Could not extract projectname from $filename\n";
            return;
        }

        $projectname = substr($filename, 0, $pos);
        $project = new Project();
        $project->Name = $projectname;
        $project->GetIdByName();
        if (!$project->Id) {
            echo "Could not find project $projectname\n";
            return;
        }

        // Get md5 from filename (if any).
        $md5 = '';
        $last_underscore_pos = strrpos($filename, '_');
        if ($last_underscore_pos !== false) {
            $offset = $last_underscore_pos - strlen($filename) - 1;
            $next_to_last_underscore_pos = strrpos($filename, '_', $offset);
            if ($next_to_last_underscore_pos !== false) {
                $next_to_last_underscore_pos += 1;
                $len = $last_underscore_pos - $next_to_last_underscore_pos;
                $md5 = substr($filename, $next_to_last_underscore_pos, $len);
            }
        }

        ProcessSubmission::dispatch($filename, $project->Id, null, $md5);
    }
}
