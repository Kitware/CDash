<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class SiteController extends AbstractController
{
    public function siteStatistics(): View|RedirectResponse
    {
        if (config('database.default') === 'pgsql') {
            $sites = DB::select("
                         SELECT
                             siteid,
                             sitename,
                             SUM(elapsed) AS busytime
                         FROM (
                             SELECT
                                 site.id AS siteid,
                                 site.name AS sitename,
                                 project.name AS projectname,
                                 build.name AS buildname,
                                 build.type,
                                 AVG(submittime - buildupdate.starttime) AS elapsed
                             FROM
                                 build,
                                 build2update,
                                 buildupdate,
                                 project,
                                 site
                             WHERE
                                 submittime > NOW() - interval '168 hours'
                                 AND build2update.buildid = build.id
                                 AND buildupdate.id = build2update.updateid
                                 AND site.id = build.siteid
                                 AND build.projectid = project.id
                             GROUP BY
                                 sitename,
                                 projectname,
                                 buildname,
                                 build.type,
                                 site.id
                             ORDER BY elapsed DESC
                         ) AS summary
                         GROUP BY
                             sitename,
                             summary.siteid
                         ORDER BY busytime DESC
                     ");
        } else {
            $sites = DB::select('
                         SELECT
                             siteid,
                             sitename,
                             SEC_TO_TIME(SUM(elapsed)) AS busytime
                         FROM (
                             SELECT
                                 site.id AS siteid,
                                 site.name AS sitename,
                                 project.name AS projectname,
                                 build.name AS buildname,
                                 build.type,
                                 AVG(TIME_TO_SEC(TIMEDIFF(submittime, buildupdate.starttime))) AS elapsed
                             FROM
                                 build,
                                 build2update,
                                 buildupdate,
                                 project,
                                 site
                             WHERE
                                 submittime > TIMESTAMPADD(HOUR, -168, NOW())
                                 AND build2update.buildid = build.id
                                 AND buildupdate.id = build2update.updateid
                                 AND site.id = build.siteid
                                 AND build.projectid = project.id
                             GROUP BY
                                 sitename,
                                 projectname,
                                 buildname,
                                 build.type
                             ORDER BY elapsed DESC
                         ) AS summary
                         GROUP BY sitename
                         ORDER BY busytime DESC
                     ');
        }

        return $this->view('site.site-statistics')
            ->with('sites', $sites);
    }

    public function viewSite(Site $site): View
    {
        return $this->view('site.view-site', $site->name)
            ->with('site', $site);
    }
}
