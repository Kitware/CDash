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
    protected $signature = 'dependencies:update {--D|dev} {--U|upgrade}';

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
        $composerInstallArgs = "--no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader";
        $composerUpdateArgs = "--no-dev --prefer-lowest --prefer-stable";
        if ($this->option("dev")) {
            $composerInstallArgs = "--no-interaction --no-progress --prefer-dist";
            $composerUpdateArgs = "--prefer-lowest --prefer-stable";
        }

        if ($this->option("upgrade")) {
            exec("npm update");
            exec("composer update $composerUpdateArgs");
        }

        //  PHP dependencies via composer
        exec("composer install $composerInstallArgs");

        // Update JavaScript dependencies via npm
        exec("npm install");

        // Run laravel-mix to builds assets
        exec("npm run dev");
    }
}
