<?php

namespace App\Console\Commands;

use App\Http\Controllers\AdminController;
use Illuminate\Console\Command;

class SetVersion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'version:set';

    /**
     * The console command description.
     *
     * @var string|null
     */
    protected $description = 'Set the current CDash version in the database.';

    /**
     * Create or update a user.
     *
     * @return void
     */
    public function handle()
    {
        $version = AdminController::setVersion();
        echo "Database schema set to CDash version $version." . PHP_EOL;
    }
}
