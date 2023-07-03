<?php

namespace App\Console\Commands;

use CDash\Model\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeUnusedProjects extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'projects:clean';

    /**
     * The console command description.
     *
     * @var string|null
     */
    protected $description = 'Delete projects with no builds';

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
     * @return void
     */
    public function handle()
    {
        set_time_limit(0);

        $result = DB::select('SELECT id FROM project');
        $all_project_ids = [];
        foreach ($result as $row) {
            $all_project_ids[] = (int) $row->id;
        }

        foreach ($all_project_ids as $projectid) {
            $project = new Project();
            $project->Id = $projectid;
            $project->Fill();

            if ($project->GetNumberOfBuilds() === 0) {
                echo 'Deleting project: ' . $project->Name . PHP_EOL;
                $project->Delete();
            }
        }
    }
}
