<?php

namespace App\Jobs;

use App\Models\BuildEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Deletes sent email logs older than 48 hours.
 */
class PruneEmails implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        BuildEmail::where('time', '<', Carbon::now()->subDays(2))->delete();
    }
}
