<?php

declare(strict_types=1);

namespace App\Utils;

use App\Models\Build;
use App\Models\BuildGroup;
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
        remove_build_chunked($buildids);
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
        remove_build_chunked($buildids);
    }
}
