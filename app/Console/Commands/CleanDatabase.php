<?php

namespace App\Console\Commands;

use App\Utils\DatabaseCleanupUtils;
use Illuminate\Console\Command;

class CleanDatabase extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'db:clean';

    /**
     * The console command description.
     */
    protected $description = 'Prune unused records from the CDash database';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Reconfigure laravel to log to stderr for the rest of this command.
        config(['logging.default' => 'stderr']);

        DatabaseCleanupUtils::deleteUnusedRows('configure', 'id', 'build2configure', 'configureid');
        DatabaseCleanupUtils::deleteUnusedRows('coveragefile', 'id', 'coverage', 'fileid');
        DatabaseCleanupUtils::deleteUnusedRows('note', 'id', 'build2note', 'noteid');
        DatabaseCleanupUtils::deleteUnusedRows('testoutput', 'id', 'build2test', 'outputid');
        DatabaseCleanupUtils::deleteUnusedRows('uploadfile', 'id', 'build2uploadfile', 'fileid');
        DatabaseCleanupUtils::deleteUnusedRows('buildupdate', 'id', 'build', 'updateid');
        DatabaseCleanupUtils::deleteUnusedRows('image', 'id', 'test2image', 'imgid');
    }
}
