<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\User;
use App\Services\PageTimer;
use CDash\Model\SubProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class SubProjectController extends AbstractProjectController
{
    public function viewSubProjects(): Response
    {
        return response()->angular_view('viewSubProjects');
    }

    public function manageSubProject(): Response
    {
        return response()->angular_view('manageSubProject');
    }


    public function apiManageSubProject(): JsonResponse
    {
        $pageTimer = new PageTimer();

        $response = begin_JSON_response();
        $response['backurl'] = 'user.php';
        $response['menutitle'] = 'CDash';
        $response['menusubtitle'] = 'SubProjects';
        $response['title'] = 'Manage SubProjects';
        $response['hidenav'] = 1;

        /** @var User $user */
        $user = Auth::user();

        // List the available projects that this user has admin rights to.
        $projectid = intval($_GET['projectid'] ?? 0);


        $sql = 'SELECT id, name FROM project';
        $params = [];
        if (!$user->IsAdmin()) {
            $sql .= " WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid = ? AND role > 0)";
            $params[] = intval(Auth::id());
        }

        $projects = DB::select($sql, $params);
        $availableprojects = [];
        foreach ($projects as $project_array) {
            $availableproject = [
                'id' => $project_array->id,
                'name' => $project_array->name,
            ];
            if (intval($project_array->id) === $projectid) {
                $availableproject['selected'] = '1';
            }
            $availableprojects[] = $availableproject;
        }
        $response['availableprojects'] = $availableprojects;

        if ($projectid < 1) {
            $response['error'] = 'Please select a project to continue.';
            return response()->json($response);
        }
        $this->setProjectById($projectid);
        Gate::authorize('edit-project', $this->project);

        $response['projectid'] = $projectid;

        get_dashboard_JSON($this->project->GetName(), null, $response);

        $response['threshold'] = $this->project->GetCoverageThreshold();

        $subprojects_response = []; // JSON for subprojects
        foreach ($this->project->GetSubProjects() as $subproject) {
            $subprojects_response[] = [
                'id' => $subproject->id,
                'name' => $subproject->name,
                'group' => $subproject->groupid,
            ];
        }
        $response['subprojects'] = $subprojects_response;

        $groups = [];
        foreach ($this->project->GetSubProjectGroups() as $subProjectGroup) {
            $group = [
                'id' => $subProjectGroup->GetId(),
                'name' => $subProjectGroup->GetName(),
                'position' => $subProjectGroup->GetPosition(),
                'coverage_threshold' => $subProjectGroup->GetCoverageThreshold(),
            ];
            $groups[] = $group;
            if ($subProjectGroup->GetIsDefault() > 0) {
                $response['default_group_id'] = $group['id'];
            }
        }
        $response['groups'] = $groups;

        $pageTimer->end($response);
        return response()->json(cast_data_for_JSON($response));
    }

    public function dependenciesGraph(): View|RedirectResponse
    {
        $this->setProjectByName($_GET['project'] ?? '');

        return view('project.subproject-dependencies')
            ->with('project', $this->project);
    }

    public function apiViewSubProjects(): JsonResponse
    {
        $pageTimer = new PageTimer();

        @set_time_limit(0);

        $this->setProjectByName(htmlspecialchars($_GET['project'] ?? ''));

        if (isset($_GET['date'])) {
            $date = htmlspecialchars(pdo_real_escape_string($_GET['date']));
            $date_specified = true;
        } else {
            $last_start_timestamp = $this->project->GetLastSubmission();
            $date = strlen($last_start_timestamp) > 0 ? $last_start_timestamp : null;
            $date_specified = false;
        }

        // Gather up the data for a SubProjects dashboard.

        $response = begin_JSON_response();

        $response['title'] = $this->project->Name;
        $response['showcalendar'] = 1;

        $banners = [];
        $global_banner = Banner::find(0);
        if ($global_banner !== null && strlen($global_banner->text) > 0) {
            $banners[] = $global_banner->text;
        }
        $project_banner = Banner::find($this->project->Id);
        if ($project_banner !== null && strlen($project_banner->text) > 0) {
            $banners[] = $project_banner->text;
        }
        $response['banners'] = $banners;

        if (config('cdash.show_last_submission')) {
            $response['showlastsubmission'] = 1;
        }

        [$previousdate, $currentstarttime, $nextdate] = get_dates($date, $this->project->NightlyTime);

        // Main dashboard section
        get_dashboard_JSON($this->project->GetName(), $date, $response);
        $projectname_encoded = urlencode($this->project->Name);
        if ($currentstarttime > time()) {
            abort(400, 'CDash cannot predict the future (yet)');
        }

        $linkparams = 'project=' . urlencode($this->project->Name);
        if (!empty($date)) {
            $linkparams .= "&date=$date";
        }
        $response['linkparams'] = $linkparams;

        // Menu definition
        $menu_response = [];
        $menu_response['subprojects'] = 1;
        $menu_response['previous'] = "viewSubProjects.php?project=$projectname_encoded&date=$previousdate";
        $menu_response['current'] = "viewSubProjects.php?project=$projectname_encoded";
        if (!has_next_date($date, $currentstarttime)) {
            $menu_response['nonext'] = 1;
        } else {
            $menu_response['next'] = "viewSubProjects.php?project=$projectname_encoded&date=$nextdate";
        }
        $response['menu'] = $menu_response;

        $beginning_UTCDate = gmdate(FMT_DATETIME, $currentstarttime);
        $end_UTCDate = gmdate(FMT_DATETIME, $currentstarttime + 3600 * 24);

        // Get some information about the project
        $project_response = [];
        $project_response['nbuilderror'] = $this->project->GetNumberOfErrorBuilds($beginning_UTCDate, $end_UTCDate);
        $project_response['nbuildwarning'] = $this->project->GetNumberOfWarningBuilds($beginning_UTCDate, $end_UTCDate);
        $project_response['nbuildpass'] = $this->project->GetNumberOfPassingBuilds($beginning_UTCDate, $end_UTCDate);
        $project_response['nconfigureerror'] = $this->project->GetNumberOfErrorConfigures($beginning_UTCDate, $end_UTCDate);
        $project_response['nconfigurewarning'] = $this->project->GetNumberOfWarningConfigures($beginning_UTCDate, $end_UTCDate);
        $project_response['nconfigurepass'] = $this->project->GetNumberOfPassingConfigures($beginning_UTCDate, $end_UTCDate);
        $project_response['ntestpass'] = $this->project->GetNumberOfPassingTests($beginning_UTCDate, $end_UTCDate);
        $project_response['ntestfail'] = $this->project->GetNumberOfFailingTests($beginning_UTCDate, $end_UTCDate);
        $project_response['ntestnotrun'] = $this->project->GetNumberOfNotRunTests($beginning_UTCDate, $end_UTCDate);
        $project_last_submission = $this->project->GetLastSubmission();
        if (strlen($project_last_submission) === 0) {
            $project_response['starttime'] = 'NA';
        } else {
            $project_response['starttime'] = $project_last_submission;
        }
        $response['project'] = $project_response;

        // Look for the subproject
        $subprojects = $this->project->GetSubProjects();
        $subprojProp = [];
        foreach ($subprojects as $subproject) {
            $subprojProp[$subproject->id] = ['name' => $subproject->name];
        }

        // If all of the dates are the same, we can get the results in bulk.  Otherwise, we must query every
        // subproject separately.
        if ($date_specified) {
            $testSubProj = new SubProject();
            $testSubProj->SetProjectId($this->project->Id);
            $result = $testSubProj->CommonBuildQuery($beginning_UTCDate, $end_UTCDate, true);
            if ($result !== false) {
                foreach ($result as $row) {
                    $subprojProp[$row['subprojectid']]['nbuilderror'] = (int) $row['nbuilderrors'];
                    $subprojProp[$row['subprojectid']]['nbuildwarning'] = (int) $row['nbuildwarnings'];
                    $subprojProp[$row['subprojectid']]['nbuildpass'] = (int) $row['npassingbuilds'];
                    $subprojProp[$row['subprojectid']]['nconfigureerror'] = (int) $row['nconfigureerrors'];
                    $subprojProp[$row['subprojectid']]['nconfigurewarning'] = (int) $row['nconfigurewarnings'];
                    $subprojProp[$row['subprojectid']]['nconfigurepass'] = (int) $row['npassingconfigures'];
                    $subprojProp[$row['subprojectid']]['ntestpass'] = (int) $row['ntestspassed'];
                    $subprojProp[$row['subprojectid']]['ntestfail'] = (int) $row['ntestsfailed'];
                    $subprojProp[$row['subprojectid']]['ntestnotrun'] = (int) $row['ntestsnotrun'];
                }
            }
        }

        $reportArray = ['nbuilderror', 'nbuildwarning', 'nbuildpass',
            'nconfigureerror', 'nconfigurewarning', 'nconfigurepass',
            'ntestpass', 'ntestfail', 'ntestnotrun'];
        $subprojects_response = [];

        foreach ($subprojects as $subproject) {
            $subproject_response = [];
            $subproject_response['name'] = $subproject->name;
            $subproject_response['name_encoded'] = urlencode($subproject->name);

            // TODO: Replace this with something in the Eloquent SubProject model...
            $legacy_subproject_model = new SubProject();
            $legacy_subproject_model->SetId($subproject->id);

            $last_submission_start_timestamp = $legacy_subproject_model->GetLastSubmission();
            if (!$date_specified) {
                $currentstarttime = get_dates($last_submission_start_timestamp, $this->project->NightlyTime)[1];
                $beginning_UTCDate = gmdate(FMT_DATETIME, $currentstarttime);
                $end_UTCDate = gmdate(FMT_DATETIME, $currentstarttime + 3600 * 24);

                $result = $legacy_subproject_model->CommonBuildQuery($beginning_UTCDate, $end_UTCDate, false);

                $subprojProp[$subproject->id]['nconfigureerror'] = (int) $result['nconfigureerrors'];
                $subprojProp[$subproject->id]['nconfigurewarning'] = (int) $result['nconfigurewarnings'];
                $subprojProp[$subproject->id]['nconfigurepass'] = (int) $result['npassingconfigures'];
                $subprojProp[$subproject->id]['nbuilderror'] = (int) $result['nbuilderrors'];
                $subprojProp[$subproject->id]['nbuildwarning'] = (int) $result['nbuildwarnings'];
                $subprojProp[$subproject->id]['nbuildpass'] = (int) $result['npassingbuilds'];
                $subprojProp[$subproject->id]['ntestnotrun'] = (int) $result['ntestsnotrun'];
                $subprojProp[$subproject->id]['ntestfail'] = (int) $result['ntestsfailed'];
                $subprojProp[$subproject->id]['ntestpass'] = (int) $result['ntestspassed'];
            }

            foreach ($reportArray as $reportnum) {
                $reportval = array_key_exists($reportnum, $subprojProp[$subproject->id]) ?
                    $subprojProp[$subproject->id][$reportnum] : 0;
                $subproject_response[$reportnum] = $reportval;
            }

            if ($last_submission_start_timestamp === '' || $last_submission_start_timestamp === false) {
                $subproject_response['starttime'] = 'NA';
            } else {
                $subproject_response['starttime'] = $last_submission_start_timestamp;
            }
            $subprojects_response[] = $subproject_response;
        }
        $response['subprojects'] = $subprojects_response;

        $pageTimer->end($response);
        return response()->json(cast_data_for_JSON($response));
    }

    public function ajaxDependenciesGraph(): JsonResponse
    {
        $this->setProjectByName(htmlspecialchars($_GET['project'] ?? ''));

        $date = isset($_GET['date']) ? Carbon::parse($_GET['date']) : null;

        $subprojects = $this->project->GetSubProjects();

        $subproject_groups = [];
        $groups = $this->project->GetSubProjectGroups();
        foreach ($groups as $group) {
            $subproject_groups[$group->GetId()] = $group;
        }

        $result = []; # array to store the all the result
        /** @var \App\Models\SubProject $subproject */
        foreach ($subprojects as $subproject) {
            $subarray = [
                'name' => $subproject->name,
                'id' => $subproject->id,
            ];

            if ($subproject->groupid > 0) {
                $subarray['group'] = $subproject_groups[$subproject->groupid]->GetName();
            }

            /** @var array<string> $dependencies */
            $dependencies = $subproject->children($date)->pluck('name')->toArray();
            if (count($dependencies) > 0) {
                $subarray['depends'] = $dependencies;
            }
            $result[] = $subarray;
        }
        return response()->json(cast_data_for_JSON($result));
    }
}
