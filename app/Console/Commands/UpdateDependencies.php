<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateDependencies extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dependencies:update {--D|dev}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Attempt to update CDash\'s dependencies';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $composerArgs = "--no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader";
        if ($this->option("dev"))
        {
            $composerArgs = "--no-interaction --no-progress --prefer-dist";
        }

        // Update PHP dependencies via composer
        exec("composer update");
        exec("composer install $composerArgs");

        // Update JavaScript dependencies via npm
        exec("npm update");
        exec("npm install");

        // Run laravel-mix to builds assets
        exec("npm run dev");
    }
}
