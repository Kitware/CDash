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
        foreach (\Storage::files('inbox') as $inboxFile) {
            $filename = str_replace('inbox/', '', $inboxFile);
            $pos = strpos($filename, '_');
            if ($pos === false) {
                echo "Could not extract projectname from $filename\n";
                continue;
            }

            $projectname = substr($filename, 0, $pos);
            $project = new Project();
            $project->Name = $projectname;
            $project->GetIdByName();
            if (!$project->Id) {
                echo "Could not find project $projectname\n";
                continue;
            }

            ProcessSubmission::dispatch($filename, $project->Id, null, '');
        }
    }
}
