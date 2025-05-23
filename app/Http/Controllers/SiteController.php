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

        return $this->view('site.site-statistics', 'Site Statistics')
            ->with('sites', $sites);
    }

    public function viewSite(Site $site): View
    {
        return $this->vue('sites-id-page', $site->name, [
            'site-id' => $site->id,
            'user-id' => auth()->user()?->id,
        ]);
    }
}
