<?php

namespace App\Http\Controllers;

use App\Models\SubProject as EloquentSubProject;
use App\Models\SubProjectGroup as EloquentSubProjectGroup;
use App\Models\User;
use App\Services\ProjectService;
use App\Utils\PageTimer;
use CDash\Model\Project;
use CDash\Model\SubProject;
use CDash\Model\SubProjectGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class SubProjectController extends AbstractProjectController
{
    public function viewSubProjects(): View
    {
        $this->setProjectByName(request()->string('project'));
        return $this->angular_view('viewSubProjects', 'SubProjects');
    }

    public function manageSubProject(): View
    {
        $this->setProjectById(request()->integer('projectid'));
        return $this->angular_view('manageSubProject', 'Manage SubProjects');
    }

    public function apiManageSubProject(): JsonResponse
    {
        $pageTimer = new PageTimer();

        $response = begin_JSON_response();
        $response['menutitle'] = 'CDash';
        $response['menusubtitle'] = 'SubProjects';
        $response['title'] = 'Manage SubProjects';
        $response['hidenav'] = 1;

        /** @var User $user */
        $user = Auth::user();

        // List the available projects that this user has admin rights to.
        $projectid = (int) ($_GET['projectid'] ?? 0);

        $sql = 'SELECT id, name FROM project';
        $params = [];
        if (!$user->admin) {
            $sql .= ' WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid = ? AND role > 0)';
            $params[] = (int) Auth::id();
        }

        $projects = DB::select($sql, $params);
        $availableprojects = [];
        foreach ($projects as $project_array) {
            $availableproject = [
                'id' => $project_array->id,
                'name' => $project_array->name,
            ];
            if ((int) $project_array->id === $projectid) {
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
        foreach (ProjectService::getSubProjects((int) $this->project->Id) as $subproject) {
            $subprojects_response[] = [
                'id' => $subproject->id,
                'name' => $subproject->name,
                'group' => $subproject->groupid,
            ];
        }
        $response['subprojects'] = $subprojects_response;

        $groups = [];
        foreach (ProjectService::getSubProjectGroups((int) $this->project->Id) as $subProjectGroup) {
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

    public function apiSubProject(Request $request): JsonResponse|Response
    {
        if (!$request->has('projectid')) {
            abort(400, 'projectid not specified.');
        }
        $projectid = $request->integer('projectid');

        // Make sure the user has access to this page.
        $project = new Project();
        $project->Id = $projectid;
        if (!Gate::allows('edit-project', $project)) {
            abort(403, "You don't have the permissions to access this page ($projectid)");
        }

        // Route based on what type of request this is.
        return match ($request->method()) {
            'DELETE' => self::apiSubProjectDelete($request),
            'POST' => self::apiSubProjectPost($request, $projectid),
            'PUT' => self::apiSubProjectPut($request, $projectid),
            default => self::apiSubProjectGet($request, $projectid),
        };
    }

    private static function apiSubProjectGet(Request $request, int $projectid): JsonResponse
    {
        $subprojectid = self::getSubProjectIdFromRequest($request);

        $pageTimer = new PageTimer();
        $response = begin_JSON_response();
        $response['projectid'] = $projectid;
        $response['subprojectid'] = $subprojectid;

        $SubProject = new SubProject();
        $SubProject->SetId($subprojectid);
        $response['name'] = $SubProject->GetName();
        $response['group'] = $SubProject->GetGroupId();

        $subprojects = EloquentSubProject::where('projectid', $projectid)
            ->where('endtime', '1980-01-01 00:00:00')
            ->get(['id', 'name']);

        $dependencies = $SubProject->GetDependencies();
        $dependencies_response = [];
        $available_dependencies_response = [];

        foreach ($subprojects as $subproject) {
            if ($subproject->id === $subprojectid) {
                continue;
            }
            $subproject_response = [
                'id' => $subproject->id,
                'name' => $subproject->name,
            ];
            if (in_array($subproject->id, $dependencies, true)) {
                $dependencies_response[] = $subproject_response;
            } else {
                $available_dependencies_response[] = $subproject_response;
            }
        }

        $response['dependencies'] = $dependencies_response;
        $response['available_dependencies'] = $available_dependencies_response;

        $pageTimer->end($response);
        return response()->json(cast_data_for_JSON($response));
    }

    private static function apiSubProjectDelete(Request $request): Response
    {
        if ($request->has('groupid')) {
            // Delete subproject group.
            $Group = new SubProjectGroup();
            $Group->SetId($request->integer('groupid'));
            $Group->Delete();
        }

        return response()->noContent();
    }

    private static function apiSubProjectPost(Request $request, int $projectid): JsonResponse|Response
    {
        $response = response()->noContent();

        if ($request->has('newgroup')) {
            // Create a new group
            $Group = new SubProjectGroup();
            $Group->SetProjectId($projectid);
            $Group->SetName(htmlspecialchars($request->string('newgroup')->toString()));
            if ($request->has('isdefault')) {
                $Group->SetIsDefault($request->input('isdefault') === 'true' ? 1 : 0);
            }
            $Group->SetCoverageThreshold($request->integer('threshold'));
            $Group->Save();

            // Respond with a JSON representation of this new group
            $response = response()->json(cast_data_for_JSON([
                'id' => $Group->GetId(),
                'name' => $Group->GetName(),
                'is_default' => $Group->GetIsDefault(),
                'coverage_threshold' => $Group->GetCoverageThreshold(),
            ]));
        }

        if ($request->has('newLayout')) {
            // Update the order of the SubProject groups.
            foreach (array_keys((array) $request->input('newLayout')) as $index) {
                // TODO: (williamjallen) refactor this to execute a constant number of queries
                EloquentSubProjectGroup::findOrFail($request->integer("newLayout.{$index}.id"))
                    ->update([
                        'position' => $request->integer("newLayout.{$index}.position"),
                    ]);
            }
        }

        return $response;
    }

    private static function apiSubProjectPut(Request $request, int $projectid): Response
    {
        if ($request->has('threshold')) {
            // Modify an existing subproject group.
            $Group = new SubProjectGroup();
            $Group->SetProjectId($projectid);
            $Group->SetId($request->integer('groupid'));
            $Group->SetName($request->string('name')->toString());
            $Group->SetCoverageThreshold($request->integer('threshold'));
            $Group->SetIsDefault($request->input('is_default') === 'true' ? 1 : 0);
            $Group->Save();

            return response()->noContent();
        }

        $SubProject = new SubProject();
        $SubProject->SetId(self::getSubProjectIdFromRequest($request));

        if ($request->has('groupname')) {
            // Change which group a subproject belongs to.
            $SubProject->SetGroup($request->string('groupname')->toString());
            $SubProject->Save();
        }

        return response()->noContent();
    }

    private static function getSubProjectIdFromRequest(Request $request): int
    {
        if (!$request->has('subprojectid')) {
            abort(400, 'subprojectid not specified.');
        }
        return $request->integer('subprojectid');
    }

    public function dependenciesGraph(Request $request, string $project): View
    {
        $this->setProjectByName($project);

        return $this->vue('sub-project-dependencies-page', 'SubProject Dependencies', [
            'project-name' => $this->project->Name,
            'date' => $request->string('date'),
        ]);
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
            $last_start_timestamp = ProjectService::getLastStartTimestamp((int) $this->project->Id);
            $date = strlen($last_start_timestamp) > 0 ? $last_start_timestamp : null;
            $date_specified = false;
        }

        // Gather up the data for a SubProjects dashboard.

        $response = begin_JSON_response();

        $response['title'] = $this->project->Name;
        $response['showcalendar'] = 1;

        $banners = [];
        if ($this->project->Banner !== null && strlen($this->project->Banner) > 0) {
            $banners[] = $this->project->Banner;
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
        $response['linkdate'] = $date;

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
        $project_last_submission = ProjectService::getLastStartTimestamp((int) $this->project->Id);
        if (strlen($project_last_submission) === 0) {
            $project_response['starttime'] = 'NA';
        } else {
            $project_response['starttime'] = $project_last_submission;
        }
        $response['project'] = $project_response;

        // Look for the subproject
        $subprojects = ProjectService::getSubProjects((int) $this->project->Id);
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

    public function apiDependenciesGraph(): JsonResponse
    {
        $this->setProjectByName(htmlspecialchars($_GET['project'] ?? ''));

        $date = isset($_GET['date']) ? Carbon::parse($_GET['date']) : null;

        $subprojects = ProjectService::getSubProjects((int) $this->project->Id);

        $subproject_groups = [];
        $groups = ProjectService::getSubProjectGroups((int) $this->project->Id);
        foreach ($groups as $group) {
            $subproject_groups[$group->GetId()] = $group;
        }

        $result = []; // array to store the all the result
        /** @var EloquentSubProject $subproject */
        foreach ($subprojects as $subproject) {
            $subarray = [
                'name' => $subproject->name,
                'id' => $subproject->id,
                'depends' => [],
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
        return response()->json([
            'dependencies' => $result,
        ]);
    }
}
