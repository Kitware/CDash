<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Console\Command;

class DeleteProject extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'project:delete {name : The name of the project to delete}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently delete a project and all of its associated data';

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
        $name = $this->argument('name');

        $project = Project::where('name', $name)->first();
        if ($project === null) {
            $this->error("Project $name does not exist");
            return;
        }

        ProjectService::delete($project);
        $this->info("Deleted project $name");
    }
}
