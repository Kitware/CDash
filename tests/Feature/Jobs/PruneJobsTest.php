<?php

namespace Tests\Feature\Jobs;

use App\Jobs\PruneJobs;
use App\Models\SuccessfulJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneJobsTest extends TestCase
{
    /**
     * Changing the config is difficult since multiple processes are involved.
     * Instead, we just rely upon the default value of 48 hours.
     */
    public function testExpiredSuccessfulJobDeleted(): void
    {
        $filename = 'test-filename' . Str::uuid()->toString();
        $job = new SuccessfulJob([
            'filename' => $filename,
        ]);
        $job->finished_at = Carbon::now()->subHours(1000); // finished_at isn't fillable...
        $job->save();

        self::assertEquals(1, SuccessfulJob::where('filename', $filename)->count());

        PruneJobs::dispatch();

        self::assertEquals(0, SuccessfulJob::where('filename', $filename)->count());
    }

    public function testRecentSuccessfulJobNotDeleted(): void
    {
        $filename = 'test-filename' . Str::uuid()->toString();
        // The timestamp defaults to NOW().
        SuccessfulJob::create([
            'filename' => $filename,
        ]);

        self::assertEquals(1, SuccessfulJob::where('filename', $filename)->count());

        PruneJobs::dispatch();

        self::assertEquals(1, SuccessfulJob::where('filename', $filename)->count());
    }
}
