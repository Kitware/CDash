<?php

declare(strict_types=1);

namespace App\Utils;

use App\Models\Build;
use App\Models\BuildGroup;
use CDash\Database;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $start = microtime(true);
        self::removeBuildChunked($buildids);
        $end = microtime(true);
        $duration = round($end - $start, 2);
        $num_builds = count($buildids);
        Log::info("Removed {$num_builds} builds for project #{$projectid} in {$duration} seconds");
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
                throw new \InvalidArgumentException('Invalid Build ID');
            }
            $buildids[] = intval($b);
        }

        $db = Database::getInstance();
        $buildid_prepare_array = $db->createPreparedArray(count($buildids));

        // Remove the buildfailureargument
        $buildfailureids = [];
        $buildfailure = DB::select("SELECT id FROM buildfailure WHERE buildid IN $buildid_prepare_array", $buildids);
        foreach ($buildfailure as $buildfailure_array) {
            $buildfailureids[] = intval($buildfailure_array->id);
        }
        if (count($buildfailureids) > 0) {
            $buildfailure_prepare_array = $db->createPreparedArray(count($buildfailureids));
            DB::delete("DELETE FROM buildfailure2argument WHERE buildfailureid IN $buildfailure_prepare_array", $buildfailureids);
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
                self::removeBuildChunked($childids);
            }
        }

        // Only delete the buildid at the end so that no other build can get it in the meantime
        DB::delete("DELETE FROM build WHERE id IN $buildid_prepare_array", $buildids);
    }

    /**
     * Call removeBuild() one at a time.
     * @param array<int>|int $buildids
     */
    public static function removeBuildChunked($buildids): void
    {
        if (!is_array($buildids)) {
            self::removeBuild($buildids);
        }
        foreach ($buildids as $buildid) {
            self::removeBuild($buildid);
            usleep(1);
        }
    }
}
