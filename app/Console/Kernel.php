<?php

namespace App\Console;

use App\Jobs\NotifyExpiringAuthTokens;
use App\Jobs\PerformLegacyDailyUpdates;
use App\Jobs\PruneAuthTokens;
use App\Jobs\PruneBuilds;
use App\Jobs\PruneJobs;
use App\Jobs\PruneSubmissionFiles;
use App\Jobs\PruneUploads;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
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

        $schedule->job(new PruneSubmissionFiles())
            ->hourly()
            ->withoutOverlapping();

        // TODO: This currently runs daily because the autoremovemaxbuilds project setting specifies
        // the maximum number of builds to be removed per day.  In the future, we should evaluate
        // whether this setting is meaningful.  Ideally, this job would run more frequently--perhaps
        // hourly.
        $schedule->job(new PruneBuilds())
            ->daily()
            ->withoutOverlapping();

        // A wrapper for the legacy "daily updates" process.  Pieces of this should be moved elsewhere.
        $schedule->job(new PerformLegacyDailyUpdates())
            ->daily()
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
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
