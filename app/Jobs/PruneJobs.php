<?php

namespace App\Jobs;

use App\Models\SuccessfulJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/**
 * Removes job results which are expired.  Job lifetime is controlled by the BACKUP_TIMEFRAME
 * configuration option.
 */
class PruneJobs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $lifetime = config('cdash.backup_timeframe');

        Artisan::call("queue:prune-failed --hours=$lifetime");

        // The successful_jobs table is a CDash specific table, so we have to prune it manually
        SuccessfulJob::where('finished_at', '<', Carbon::now()->subHours($lifetime))->delete();
    }
}
