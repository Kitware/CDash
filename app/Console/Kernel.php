<?php

namespace App\Console;

use App\Jobs\PruneAuthTokens;
use App\Jobs\PruneBuilds;
use App\Jobs\PruneDatabase;
use App\Jobs\PruneJobs;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $cdash_directory_name = env('CDASH_DIRECTORY', 'cdash');
        $cdash_app_dir = realpath(app_path($cdash_directory_name));
        $output_filename = $cdash_app_dir . "/AuditReport.log";

        $schedule->command('dependencies:audit')
            ->everySixHours()
            ->sendOutputTo($output_filename);

        $schedule->job(new PruneAuthTokens(), 'low')
            ->hourly()
            ->withoutOverlapping();

        $schedule->job(new PruneBuilds(), 'low')
            ->hourly()
            ->withoutOverlapping();

        $schedule->job(new PruneDatabase(), 'low')
            ->dailyAt('03:00')
            ->withoutOverlapping();

        $schedule->job(new PruneJobs(), 'low')
            ->hourly()
            ->withoutOverlapping();

        if (env('CDASH_AUTHENTICATION_PROVIDER') === 'ldap') {
            $schedule->command('ldap:sync_projects')
                ->everyFiveMinutes();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
