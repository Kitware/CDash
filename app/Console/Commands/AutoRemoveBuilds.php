<?php

namespace App\Console\Commands;

use CDash\Database;
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
                            {project : The project to clean, or "all"}
                            {--all-builds : Remove ALL builds for the specified project}';

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
        $db = new Database();
        $sql = 'SELECT id, autoremovetimeframe, autoremovemaxbuilds
                FROM project';
        $args = [];
        if ($projectname != 'all') {
            $sql .= ' WHERE name = ?';
            $args = [$projectname];
        }
        $stmt = $db->prepare($sql);

        if ($this->option('all-builds')) {
            if ($projectname == 'all') {
                echo "Removing all builds for all projects is not supported.\n";
                return;
            }
            echo "removing ALL builds for $projectname \n";
            $db->execute($stmt, $args);
            $project_array = $stmt->fetch();
            remove_project_builds($project_array['id']);
        } else {
            echo "removing builds for $projectname \n";

            $db->execute($stmt, $args);
            while ($project_array = $stmt->fetch()) {
                removeFirstBuilds(
                    $project_array['id'],
                    $project_array['autoremovetimeframe'],
                    $project_array['autoremovemaxbuilds'],
                    true // force the autoremove
                );
                removeBuildsGroupwise(
                    $project_array['id'],
                    $project_array['autoremovemaxbuilds'],
                    true // force the autoremove
                );
            }
        }
    }
}
