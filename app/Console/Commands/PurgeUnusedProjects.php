<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;

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
     * @var string
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
     */
    public function handle(): void
    {
        foreach (Project::doesntHave('builds')->get() as $project) {
            echo 'Deleting project: ' . $project->name . PHP_EOL;
            $project->delete();
        }
    }
}
