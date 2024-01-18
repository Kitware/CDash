<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MakeStorageDirectories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:mkdirs';

    /**
     * The console command description.
     *
     * @var string|null
     */
    protected $description = 'Create storage directories if they do not already exist';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $storage_path = storage_path();
        $dirs_to_check = [
            "{$storage_path}/app/failed",
            "{$storage_path}/app/inbox",
            "{$storage_path}/app/inprogress",
            "{$storage_path}/app/parsed",
            "{$storage_path}/app/public",
            "{$storage_path}/app/upload",
            "{$storage_path}/framework/cache/data",
            "{$storage_path}/framework/sessions",
            "{$storage_path}/framework/views",
            "{$storage_path}/logs",
        ];

        foreach ($dirs_to_check as $dir_to_check) {
            if (is_dir($dir_to_check)) {
                echo "$dir_to_check already exists\n";
            } else {
                echo "creating $dir_to_check\n";
                mkdir($dir_to_check, 0o755, true);
            }
        }

        return Command::SUCCESS;
    }
}
