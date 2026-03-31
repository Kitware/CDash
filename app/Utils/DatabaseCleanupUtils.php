<?php

declare(strict_types=1);

namespace App\Utils;

use App\Models\Build;
use App\Models\BuildGroup;
use App\Models\BuildUpdate;
use App\Models\Configure;
use App\Models\CoverageFile;
use App\Models\Image;
use App\Models\Note;
use App\Models\TestOutput;
use App\Models\UploadFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class DatabaseCleanupUtils
{
    /** Remove builds by their group-specific auto-remove timeframe setting */
    public static function removeBuildsGroupwise(int $projectid, int $maxbuilds, bool $force = false): void
    {
        if (!$force && !(bool) config('cdash.autoremove_builds')) {
            return;
        }

        @set_time_limit(0);

        $buildids = [];

        $buildgroups = BuildGroup::where(['projectid' => $projectid])->get();
        foreach ($buildgroups as $buildgroup) {
            $days = (int) $buildgroup->autoremovetimeframe;
            if ($days < 2) {
                continue;
            }

            $cutoff = time() - 3600 * 24 * $days;
            $cutoffdate = date(FMT_DATETIME, $cutoff);

            $groupid = (int) $buildgroup->id;

            $builds = DB::select('
                          SELECT build.id AS id
                          FROM build, build2group
                          WHERE
                              build.parentid IN (0, -1)
                              AND build.starttime < ?
                              AND build2group.buildid = build.id
                              AND build2group.groupid = ?
                          ORDER BY build.starttime ASC
                          LIMIT ?
                      ', [$cutoffdate, $groupid, $maxbuilds]);

            foreach ($builds as $build) {
                $buildids[] = (int) $build->id;
            }
        }

        $s = 'removing old buildids for projectid: ' . $projectid;
        Log::info($s);
        if (app()->runningInConsole()) {
            echo '  -- ' . $s . "\n";
        }
        self::removeBuildsChunked($buildids);
    }

    /** Remove the first builds that are at the beginning of the queue */
    public static function removeFirstBuilds(int $projectid, int $days, int $maxbuilds, bool $force = false): void
    {
        @set_time_limit(0);
        $remove_builds = config('cdash.autoremove_builds');

        if (!$force && !(bool) $remove_builds) {
            return;
        }

        if (!$force && $days < 2) {
            return;
        }

        // First remove the builds with the wrong date
        $currentdate = time() - 3600 * 24 * $days;
        $startdate = date(FMT_DATETIME, $currentdate);

        Log::info('about to query for builds to remove');

        $buildids = Build::whereIn('parentid', [0, -1])
            ->where('starttime', '<', $startdate)
            ->where('projectid', '=', $projectid)
            ->orderBy('starttime')
            ->limit($maxbuilds)
            ->pluck('id')->toArray();

        $s = 'removing old buildids for projectid: ' . $projectid;
        Log::info($s);
        if (app()->runningInConsole()) {
            echo '  -- ' . $s . "\n"; // for "interactive" command line feedback
        }
        self::removeBuildsChunked($buildids);
    }

    /**
     * Remove all related inserts for a given build or any build in an array of builds
     *
     * @param array<int>|int $buildid
     *
     * @throws InvalidArgumentException
     */
    public static function removeBuild($buildid): void
    {
        // TODO: (williamjallen) much of this work could be done on the DB side automatically by setting up
        //       proper foreign-key relationships between between entities, and using the DB's cascade functionality.
        //       For complex cascades, custom SQL functions can be written.

        if (!is_array($buildid)) {
            $buildid = [$buildid];
        }

        $buildids = [];
        foreach ($buildid as $b) {
            if (!is_numeric($b)) {
                throw new InvalidArgumentException('Invalid Build ID');
            }
            $buildids[] = (int) $b;
        }

        // Use Eloquent relationships to delete shared records that are only
        // used by builds that are about to be deleted.

        Configure::whereHas('builds', function (Builder $query) use ($buildids): void {
            $query->whereIn('id', $buildids);
        })->whereDoesntHave('builds', function (Builder $query) use ($buildids): void {
            $query->whereNotIn('id', $buildids);
        })->delete();

        CoverageFile::whereHas('builds', function (Builder $query) use ($buildids): void {
            $query->whereIn('build.id', $buildids);
        })->whereDoesntHave('builds', function (Builder $query) use ($buildids): void {
            $query->whereNotIn('build.id', $buildids);
        })->delete();

        Note::whereHas('builds', function (Builder $query) use ($buildids): void {
            $query->whereIn('id', $buildids);
        })->whereDoesntHave('builds', function (Builder $query) use ($buildids): void {
            $query->whereNotIn('id', $buildids);
        })->delete();

        BuildUpdate::whereHas('builds', function (Builder $query) use ($buildids): void {
            $query->whereIn('build.id', $buildids);
        })->whereDoesntHave('builds', function (Builder $query) use ($buildids): void {
            $query->whereNotIn('build.id', $buildids);
        })->delete();

        Image::whereHas('tests.build', function (Builder $query) use ($buildids): void {
            $query->whereIn('build.id', $buildids);
        })->whereDoesntHave('tests.build', function (Builder $query) use ($buildids): void {
            $query->whereNotIn('build.id', $buildids);
        })->delete();

        TestOutput::whereHas('tests.build', function (Builder $query) use ($buildids): void {
            $query->whereIn('build.id', $buildids);
        })->whereDoesntHave('tests.build', function (Builder $query) use ($buildids): void {
            $query->whereNotIn('build.id', $buildids);
        })->delete();

        $filesToDelete = UploadFile::whereHas('builds', function (Builder $query) use ($buildids): void {
            $query->whereIn('build.id', $buildids);
        })->whereDoesntHave('builds', function (Builder $query) use ($buildids): void {
            $query->whereNotIn('build.id', $buildids);
        })->get();

        foreach ($filesToDelete as $uploadFile) {
            Storage::delete("upload/{$uploadFile->sha1sum}");
            $uploadFile->delete();
        }

        $childids = Build::whereIn('parentid', $buildids)->pluck('id');
        if ($childids->isNotEmpty()) {
            self::removeBuild($childids->toArray());
        }

        Build::whereIn('id', $buildids)->delete();
    }

    /**
     * Call removeBuild() in batches of 100.
     *
     * @param array<int> $buildids
     */
    public static function removeBuildsChunked(array $buildids): void
    {
        foreach (array_chunk($buildids, 100) as $chunk) {
            self::removeBuild($chunk);
        }
    }

    /** Delete unused rows in batches */
    public static function deleteUnusedRows(string $table, string $field, string $targettable, string $selectfield = 'id'): void
    {
        $start = DB::table($table)->min($field);
        $max = DB::table($table)->max($field);
        if (!is_numeric($start) || !is_numeric($max)) {
            Log::info("Could not determine min and max for `{$field}` on `{$table}`");
            return;
        }

        $start = (int) $start;
        $max = (int) $max;
        $total = $max - $start + 1;
        if ($total < 1) {
            Log::info("Invalid values found for min ({$start}) and/or max ({$max}) for `{$field}` on `{$table}`");
            return;
        }
        $num_done = 0;
        $num_deleted = 0;
        $next_report = 10;
        $done = false;
        Log::info("Deleting unused rows from `{$table}`");
        while (!$done) {
            $end = $start + 49999;
            $num_deleted += DB::delete("
                DELETE FROM {$table}
                WHERE {$field} BETWEEN {$start} AND {$end}
                      AND NOT EXISTS
                      (SELECT 1 FROM {$targettable} WHERE {$targettable}.{$selectfield} = {$table}.{$field})");
            $num_done += 50000;
            if ($end >= $max) {
                $done = true;
            } else {
                usleep(1);
                $start += 50000;
                // Calculate percentage of work completed so far.
                $percent = round(($num_done / $total) * 100, -1);
                if ($percent > $next_report) {
                    Log::info("Cleaning `{$table}`: {$next_report}%");
                    $next_report += 10;
                }
            }
        }
        Log::info("{$num_deleted} rows deleted from `{$table}`");
    }
}
