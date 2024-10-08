<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Removes builds that have expired according to per-project and
 * per-buildgroup settings.
 */
class PruneBuilds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!(bool) config('cdash.autoremove_builds')) {
            return;
        }

        $skip_threshold = (int) config('cdash.autoremove_builds_skip_threshold');
        if (DB::table('jobs')->count() > $skip_threshold) {
            return;
        }

        Artisan::call('build:remove all');
    }
}
