<?php

namespace App\Jobs;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Deletes submission records which are expired.  Their lifetime is controlled by the BACKUP_TIMEFRAME
 * configuration option.
 */
class PruneSubmissions implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $lifetime = (int) config('cdash.backup_timeframe');

        Submission::where('finished_at', '<', Carbon::now()->subHours($lifetime))->delete();

        // A submission whose job was lost never finishes.  Give unfinished submissions at least a day, since a
        // long queue or a Done.xml waiting for the build's other files can keep one unfinished for hours.
        Submission::whereNull('finished_at')
            ->where('received_at', '<', Carbon::now()->subHours(max($lifetime, 24)))
            ->delete();
    }
}
