<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AuditDependencies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dependencies:audit';

    /**
     * The console command description.
     *
     * @var string|null
     */
    protected $description = 'Run security audit on CDash\'s dependencies';

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
     * Handle the running of the command.
     *
     * Print a header and then the output of each command.
     * Laravel will pipe the output to a file if run by the scheduler
     * or it will print to the console if run manually.
     *
     * @return void
     */
    public function handle()
    {
        //  PHP auditing via composer
        $output = null;
        print("\n\nComposer Report:\n\n");
        exec("HOME=" . base_path() . " composer audit --no-interaction -d" . base_path(), $output);
        print(implode("\n", $output));

        //  NPM audit too
        print("\n\nNPM Report:\n\n");
        $output = null;
        exec("/usr/bin/npm audit", $output);
        print(implode("\n", $output));
    }
}
