<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

require_once 'include/pdo.php';
require_once 'include/autoremove.php';

class AutoRemoveBuilds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'build:remove
                            {project : The project to clean, or "all"}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old builds for project';

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
        set_time_limit(0);
        $projectname = $this->argument('project');
        echo "removing builds for $projectname \n";
        $sql = " WHERE name='" . $projectname . "'";
        if ($projectname == 'all') {
            $sql = '';
        }

        $project = pdo_query('SELECT id,autoremovetimeframe,autoremovemaxbuilds FROM project' . $sql);
        if (!$project) {
            add_last_sql_error('autoRemoveBuilds');
            return false;
        }
        while ($project_array = pdo_fetch_array($project)) {
            removeFirstBuilds($project_array['id'], $project_array['autoremovetimeframe'], $project_array['autoremovemaxbuilds'], true); // force the autoremove
            removeBuildsGroupwise($project_array['id'], $project_array['autoremovemaxbuilds'], true); // force the autoremove
        }
        return 0;
    }
}
