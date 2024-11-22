<?php

declare(strict_types=1);

namespace App\Utils;

use App\Models\Build;
use App\Models\BuildGroup;
use CDash\Database;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class DatabaseCleanupUtils
{
    /** Remove builds by their group-specific auto-remove timeframe setting */
    public static function removeBuildsGroupwise(int $projectid, int $maxbuilds, bool $force = false): void
    {
        if (!$force && ! (bool) config('cdash.autoremove_builds')) {
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
        echo '  -- ' . $s . "\n";
        self::removeBuildChunked($buildids);
    }

    /** Remove the first builds that are at the beginning of the queue */
    public static function removeFirstBuilds(int $projectid, int $days, int $maxbuilds, bool $force = false, bool $echo = true): void
    {
        @set_time_limit(0);
        $remove_builds = config('cdash.autoremove_builds');

        if (!$force && ! (bool) $remove_builds) {
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
        if ($echo) {
            echo '  -- ' . $s . "\n"; // for "interactive" command line feedback
        }
        self::removeBuildChunked($buildids);
    }

    /**
     * Remove all related inserts for a given build or any build in an array of builds
     * @param array<int>|int $buildid
     * @throws \InvalidArgumentException
     */
    public static function removeBuild($buildid) : void
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
            $buildids[] = intval($b);
        }

        $db = Database::getInstance();
        $buildid_prepare_array = $db->createPreparedArray(count($buildids));

        // Delete buildfailuredetails that are only used by builds that are being
        // deleted.
        DB::delete("
            DELETE FROM buildfailuredetails WHERE id IN (
                SELECT a.detailsid
                FROM buildfailure AS a
                LEFT JOIN buildfailure AS b ON (
                    a.detailsid=b.detailsid
                    AND b.buildid NOT IN $buildid_prepare_array
                )
                WHERE a.buildid IN $buildid_prepare_array
                GROUP BY a.detailsid
                HAVING count(b.detailsid)=0
            )
        ", array_merge($buildids, $buildids));

        // Delete the configure if not shared.
        $build2configure = DB::select("
                               SELECT a.configureid
                               FROM build2configure AS a
                               LEFT JOIN build2configure AS b ON (
                                   a.configureid=b.configureid
                                   AND b.buildid NOT IN $buildid_prepare_array
                               )
                               WHERE a.buildid IN $buildid_prepare_array
                               GROUP BY a.configureid
                               HAVING count(b.configureid)=0
                           ", array_merge($buildids, $buildids));

        $configureids = [];
        foreach ($build2configure as $build2configure_array) {
            // It is safe to delete this configure because it is only used
            // by builds that are being deleted.
            $configureids[] = intval($build2configure_array->configureid);
        }
        if (count($configureids) > 0) {
            $configureids_prepare_array = $db->createPreparedArray(count($configureids));
            DB::delete("DELETE FROM configure WHERE id IN $configureids_prepare_array", $configureids);
        }

        // coverage files are kept unless they are shared
        DB::delete("
            DELETE FROM coveragefile
            WHERE id IN (
                SELECT f1.id
                FROM (
                    SELECT a.fileid AS id, COUNT(DISTINCT a.buildid) AS c
                    FROM coverage a
                    WHERE a.buildid IN $buildid_prepare_array
                    GROUP BY a.fileid
                 ) AS f1
                INNER JOIN (
                    SELECT b.fileid AS id, COUNT(DISTINCT b.buildid) AS c
                    FROM coverage b
                    INNER JOIN (
                        SELECT fileid
                        FROM coverage
                        WHERE buildid IN $buildid_prepare_array
                    ) AS d ON b.fileid = d.fileid
                    GROUP BY b.fileid
                ) AS f2 ON (f1.id = f2.id)
                WHERE f1.c = f2.c
            )
        ", array_merge($buildids, $buildids));

        // Delete the note if not shared
        DB::delete("
            DELETE FROM note WHERE id IN (
                SELECT f1.id
                FROM (
                    SELECT a.noteid AS id, COUNT(DISTINCT a.buildid) AS c
                    FROM build2note a
                    WHERE a.buildid IN $buildid_prepare_array
                    GROUP BY a.noteid
                 ) AS f1
                INNER JOIN (
                    SELECT b.noteid AS id, COUNT(DISTINCT b.buildid) AS c
                    FROM build2note b
                    INNER JOIN (
                        SELECT noteid
                        FROM build2note
                        WHERE buildid IN $buildid_prepare_array
                    ) AS d ON b.noteid = d.noteid
                    GROUP BY b.noteid
                ) AS f2 ON (f1.id = f2.id)
                WHERE f1.c = f2.c
            )
        ", array_merge($buildids, $buildids));

        // Delete the update if not shared
        $build2update = DB::select("
                            SELECT a.updateid
                            FROM build2update AS a
                            LEFT JOIN build2update AS b ON (
                                a.updateid=b.updateid
                                AND b.buildid NOT IN $buildid_prepare_array
                            )
                            WHERE a.buildid IN $buildid_prepare_array
                            GROUP BY a.updateid
                            HAVING count(b.updateid)=0
                        ", array_merge($buildids, $buildids));

        $updateids = [];
        foreach ($build2update as $build2update_array) {
            // Update is not shared we delete
            $updateids[] = intval($build2update_array->updateid);
        }

        if (count($updateids) > 0) {
            $updateids_prepare_array = $db->createPreparedArray(count($updateids));
            DB::delete("DELETE FROM buildupdate WHERE id IN $updateids_prepare_array", $updateids);
        }

        // Delete tests and testoutputs that are not shared.
        // First find all the tests and testoutputs from builds that are about to be deleted.
        $b2t_result = DB::select("
                          SELECT DISTINCT outputid
                          FROM build2test
                          WHERE buildid IN $buildid_prepare_array
                      ", $buildids);

        $all_outputids = [];
        foreach ($b2t_result as $b2t_row) {
            $all_outputids[] = intval($b2t_row->outputid);
        }

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
                $testoutputs_to_save[] = intval($save_test_row->outputid);
            }

            // Use array_diff to get the list of tests that should be deleted.
            $testoutputs_to_delete = array_diff($all_outputids, $testoutputs_to_save);
            if (!empty($testoutputs_to_delete)) {
                self::deleteRowsChunked('DELETE FROM testoutput WHERE id IN ', $testoutputs_to_delete);

                $testoutputs_to_delete_prepare_array = $db->createPreparedArray(count($testoutputs_to_delete));
                // Check if the images for the test are not shared
                $test2image = DB::select("
                                  SELECT a.imgid
                                  FROM test2image AS a
                                  LEFT JOIN test2image AS b ON (
                                      a.imgid=b.imgid
                                      AND b.outputid NOT IN $testoutputs_to_delete_prepare_array
                                  )
                                  WHERE a.outputid IN $testoutputs_to_delete_prepare_array
                                  GROUP BY a.imgid
                                  HAVING count(b.imgid)=0
                              ", array_merge($testoutputs_to_delete, $testoutputs_to_delete));

                $imgids = [];
                foreach ($test2image as $test2image_array) {
                    $imgids[] = intval($test2image_array->imgid);
                }

                if (count($imgids) > 0) {
                    $imgids_prepare_array = $db->createPreparedArray(count($imgids));
                    DB::delete("DELETE FROM image WHERE id IN $imgids_prepare_array", $imgids);
                }
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
            $fileid = intval($build2uploadfile_array->fileid);
            $fileids[] = $fileid;
            unlink_uploaded_file($fileid);
        }

        if (count($fileids) > 0) {
            $fileids_prepare_array = $db->createPreparedArray(count($fileids));
            DB::delete("DELETE FROM uploadfile WHERE id IN $fileids_prepare_array", $fileids);
        }

        // Remove any children of these builds.
        // In order to avoid making the list of builds to delete too large
        // we delete them in batches (one batch per parent).
        foreach ($buildids as $parentid) {
            $child_result = DB::select('SELECT id FROM build WHERE parentid=?', [intval($parentid)]);

            $childids = [];
            foreach ($child_result as $child_array) {
                $childids[] = intval($child_array->id);
            }
            if (!empty($childids)) {
                self::removeBuild($childids);
            }
        }

        // Only delete the buildid at the end so that no other build can get it in the meantime
        DB::delete("DELETE FROM build WHERE id IN $buildid_prepare_array", $buildids);

        add_last_sql_error('remove_build');
    }

    /**
     * Call removeBuild() in batches of 100.
     * @param array<int>|int $buildids
     */
    public static function removeBuildChunked($buildids): void
    {
        if (!is_array($buildids)) {
            self::removeBuild($buildid);
        }
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

        $start = intval($start);
        $max = intval($max);

        $total = $max - $start;
        if ($total < 1) {
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
                    $next_report = $next_report + 10;
                }
            }
        }
        Log::info("{$num_deleted} rows deleted from `{$table}`");
    }
}
