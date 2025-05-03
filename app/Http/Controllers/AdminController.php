<?php

namespace App\Http\Controllers;

use App\Utils\DatabaseCleanupUtils;
use CDash\Model\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

require_once 'include/api_common.php';
require_once 'include/ctestparser.php';

final class AdminController extends AbstractController
{
    public function removeBuilds(): View|RedirectResponse
    {
        @set_time_limit(0);

        $projectid = intval($_GET['projectid'] ?? 0);

        $alert = '';

        // get date info here
        @$dayTo = intval($_POST['dayFrom']);
        if (empty($dayTo)) {
            $time = strtotime('2000-01-01 00:00:00');

            if ($projectid > 0) {
                // find the first and last builds
                $starttime = DB::select('
                    SELECT starttime
                    FROM build
                    WHERE projectid=?
                    ORDER BY starttime ASC
                    LIMIT 1
                ', [$projectid]);
                if (count($starttime) === 1) {
                    $time = strtotime($starttime[0]->starttime);
                }
            }
            $dayFrom = date('d', $time);
            $monthFrom = date('m', $time);
            $yearFrom = date('Y', $time);
            $dayTo = date('d');
            $yearTo = date('Y');
            $monthTo = date('m');
        } else {
            $dayFrom = intval($_POST['dayFrom']);
            $monthFrom = intval($_POST['monthFrom']);
            $yearFrom = intval($_POST['yearFrom']);
            $dayTo = intval($_POST['dayTo']);
            $monthTo = intval($_POST['monthTo']);
            $yearTo = intval($_POST['yearTo']);
        }

        // List the available projects
        $available_projects = [];
        $projects = DB::select('SELECT id, name FROM project');
        foreach ($projects as $projects_array) {
            $available_project = new Project();
            $available_project->Id = (int) $projects_array->id;
            $available_project->Name = $projects_array->name;
            $available_projects[] = $available_project;
        }

        // Delete the builds
        if (isset($_POST['Submit'])) {
            if (config('database.default') === 'pgsql') {
                $timestamp_sql = "CAST(CONCAT(?, '-', ?, '-', ?, ' 00:00:00') AS timestamp)";
            } else {
                $timestamp_sql = "TIMESTAMP(CONCAT(?, '-', ?, '-', ?, ' 00:00:00'))";
            }

            $build = DB::select("
                         SELECT id
                         FROM build
                         WHERE
                             projectid = ?
                             AND parentid IN (0, -1)
                             AND starttime <= $timestamp_sql
                             AND starttime >= $timestamp_sql
                         ORDER BY starttime ASC
                     ", [
                $projectid,
                $yearTo,
                $monthTo,
                $dayTo,
                $yearFrom,
                $monthFrom,
                $dayFrom,
            ]);

            $builds = [];
            foreach ($build as $build_array) {
                $builds[] = (int) $build_array->id;
            }

            DatabaseCleanupUtils::removeBuildChunked($builds);
            $alert = 'Removed ' . count($builds) . ' builds.';
        }

        return $this->view('admin.remove-builds', 'Remove Builds')
            ->with('alert', $alert)
            ->with('selected_projectid', $projectid)
            ->with('available_projects', $available_projects)
            ->with('monthFrom', $monthFrom)
            ->with('dayFrom', $dayFrom)
            ->with('yearFrom', $yearFrom)
            ->with('monthTo', $monthTo)
            ->with('dayTo', $dayTo)
            ->with('yearTo', $yearTo);
    }
}
