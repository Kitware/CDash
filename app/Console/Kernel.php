<?php

namespace App\Console;

use App\Jobs\NotifyExpiringAuthTokens;
use App\Jobs\PruneAuthTokens;
use App\Jobs\PruneJobs;
use App\Jobs\PruneUploads;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->job(new PruneJobs())
            ->hourly()
            ->withoutOverlapping();

        $schedule->job(new PruneAuthTokens())
            ->hourly()
            ->withoutOverlapping();

        $schedule->job(new PruneUploads())
            ->hourly()
            ->withoutOverlapping();

        $schedule->job(new NotifyExpiringAuthTokens())
            ->daily()
            ->withoutOverlapping();

        if ((bool) config('cdash.ldap_enabled')) {
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
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
