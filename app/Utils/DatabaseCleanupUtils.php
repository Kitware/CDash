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
use App\Models\RichBuildAlertDetails;
use App\Models\Test;
use App\Models\UploadFile;
use CDash\Database;
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

        // buildfailuredetails
        RichBuildAlertDetails::whereHas('builds', function (Builder $query) use ($buildids): void {
            $query->whereIn('build.id', $buildids);
        })
        ->whereDoesntHave('builds', function (Builder $query) use ($buildids): void {
            $query->whereNotIn('build.id', $buildids);
        })->delete();

        // configure
        Configure::whereHas('builds', function (Builder $query) use ($buildids): void {
            $query->whereIn('id', $buildids);
        })
        ->whereDoesntHave('builds', function (Builder $query) use ($buildids): void {
            $query->whereNotIn('id', $buildids);
        })->delete();

        // coveragefile
        CoverageFile::whereHas('builds', function (Builder $query) use ($buildids): void {
            $query->whereIn('build.id', $buildids);
        })
        ->whereDoesntHave('builds', function (Builder $query) use ($buildids): void {
            $query->whereNotIn('build.id', $buildids);
        })->delete();

        // note
        Note::whereHas('builds', function (Builder $query) use ($buildids): void {
            $query->whereIn('id', $buildids);
        })
        ->whereDoesntHave('builds', function (Builder $query) use ($buildids): void {
            $query->whereNotIn('id', $buildids);
        })->delete();

        // buildupdate
        BuildUpdate::whereHas('builds', function (Builder $query) use ($buildids): void {
            $query->whereIn('build.id', $buildids);
        })
        ->whereDoesntHave('builds', function (Builder $query) use ($buildids): void {
            $query->whereNotIn('build.id', $buildids);
        })->delete();

        // image.
        Image::whereHas('tests.build', function (Builder $query) use ($buildids): void {
            $query->whereIn('build.id', $buildids);
        })->whereDoesntHave('tests.build', function (Builder $query) use ($buildids): void {
            $query->whereNotIn('build.id', $buildids);
        })->delete();

        $db = Database::getInstance();
        $buildid_prepare_array = $db->createPreparedArray(count($buildids));

        // Delete tests and testoutputs that are not shared.
        // First find all the tests and testoutputs from builds that are about to be deleted.
        $tests = Test::whereHas('build', function (Builder $query) use ($buildids): void {
            $query->whereIn('build.id', $buildids);
        })->get();

        $all_outputids = $tests->pluck('outputid')->unique()->toArray();

        // Delete un-shared testoutput rows.
        if (!empty($all_outputids)) {
            // Next identify tests from this list that should be preserved
            // because they are shared with builds that are not about to be deleted.
            $all_outputids_prepare_array = $db->createPreparedArray(count($all_outputids));
            $save_test_result = DB::select("
                                    SELECT DISTINCT outputid
                                    FROM build2test
                                    WHERE
                                        outputid IN $all_outputids_prepare_array
                                        AND buildid NOT IN $buildid_prepare_array
                                ", array_merge($all_outputids, $buildids));
            $testoutputs_to_save = [];
            foreach ($save_test_result as $save_test_row) {
                $testoutputs_to_save[] = (int) $save_test_row->outputid;
            }

            // Use array_diff to get the list of tests that should be deleted.
            $testoutputs_to_delete = array_diff($all_outputids, $testoutputs_to_save);
            if (!empty($testoutputs_to_delete)) {
                self::deleteRowsChunked('DELETE FROM testoutput WHERE id IN ', $testoutputs_to_delete);
            }
        }

        // Delete the uploaded files if not shared
        $build2uploadfiles = DB::select("
                                 SELECT a.fileid
                                 FROM build2uploadfile AS a
                                 LEFT JOIN build2uploadfile AS b ON (
                                     a.fileid=b.fileid
                                     AND b.buildid NOT IN $buildid_prepare_array
                                 )
                                 WHERE a.buildid IN $buildid_prepare_array
                                 GROUP BY a.fileid
                                 HAVING count(b.fileid)=0
                             ", array_merge($buildids, $buildids));

        $fileids = [];
        foreach ($build2uploadfiles as $build2uploadfile_array) {
            $fileid = (int) $build2uploadfile_array->fileid;
            $fileids[] = $fileid;

            $sha1sum = UploadFile::findOrFail($fileid)->sha1sum;
            Storage::delete("upload/{$sha1sum}");
        }

        if (count($fileids) > 0) {
            $fileids_prepare_array = $db->createPreparedArray(count($fileids));
            DB::delete("DELETE FROM uploadfile WHERE id IN $fileids_prepare_array", $fileids);
        }

        // Remove any children of these builds.
        // In order to avoid making the list of builds to delete too large
        // we delete them in batches (one batch per parent).
        foreach ($buildids as $parentid) {
            $child_result = DB::select('SELECT id FROM build WHERE parentid=?', [(int) $parentid]);

            $childids = [];
            foreach ($child_result as $child_array) {
                $childids[] = (int) $child_array->id;
            }
            if (!empty($childids)) {
                self::removeBuild($childids);
            }
        }

        // Only delete the buildid at the end so that no other build can get it in the meantime
        DB::delete("DELETE FROM build WHERE id IN $buildid_prepare_array", $buildids);
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

    /**
     * Chunk up DELETE queries into batches of 100.
     */
    private static function deleteRowsChunked(string $query, array $ids): void
    {
        foreach (array_chunk($ids, 100) as $chunk) {
            $chunk_prepared_array = Database::getInstance()->createPreparedArray(count($chunk));
            DB::delete("$query $chunk_prepared_array", $chunk);
            // Sleep for a microsecond to give other processes a chance.
            usleep(1);
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
