<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

require_once 'include/common.php';

class CheckKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'key:check';

    /**
     * The console command description.
     *
     * @var ?string
     */
    protected $description = 'Check whether the APP_KEY environment variable is set, and print a new one if not';

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
     * @return mixed
     */
    public function handle()
    {
        if (trim(config('app.key')) === '') {
            echo "Error: APP_KEY environment variable is not set.  You can use the following randomly generated key:" . PHP_EOL;
            // Print a new key to the screen.  Note: we can't use Artisan's key:generate command if there is no .env,
            // so we generate a random key ourselves.
            echo generate_password(32) . PHP_EOL;
            return 1;
        } else {
            return 0;
        }
    }
}
