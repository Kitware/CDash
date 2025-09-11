<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToRetrieveMetadata;

/**
 * Deletes submission files older than the configured BACKUP_TIMEFRAME.
 */
class PruneSubmissionFiles implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $deletion_time_threshold = time() - (int) config('cdash.backup_timeframe') * 3600;
        $dirs_to_clean = ['parsed', 'failed', 'inprogress'];
        foreach ($dirs_to_clean as $dir_to_clean) {
            $files = Storage::allFiles($dir_to_clean);
            foreach ($files as $file) {
                try {
                    $last_modified = Storage::lastModified($file);
                } catch (UnableToRetrieveMetadata) {
                    continue;
                }
                if ($last_modified < $deletion_time_threshold) {
                    try {
                        Storage::delete($file);
                    } catch (UnableToDeleteFile) {
                        continue;
                    }
                }
            }
        }
    }
}
