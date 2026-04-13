<?php

namespace App\Http\Controllers;

use App\Models\Build as EloquentBuild;
use App\Models\BuildGroup;
use App\Models\Comment;
use App\Models\Project;
use App\Models\UploadFile;
use App\Models\User;
use App\Utils\DatabaseCleanupUtils;
use App\Utils\PageTimer;
use App\Utils\RepositoryUtils;
use App\Utils\TestingDay;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\BuildGroupRule;
use CDash\Model\BuildRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BuildController extends AbstractBuildController
{
    public function build(Request $request, int $build_id): View
    {
        $this->setBuildById($build_id);

        $project = Project::findOrFail((int) $this->project->Id);

        $params = [
            'build-id' => $this->build->Id,
            'repository-type' => $project->cvsviewertype,
            'repository-url' => $project->cvsurl,
            'repository-cmake-project-root' => $project->cmakeprojectroot,
        ];

        $onlyNewErrors = $request->has('onlydeltap');
        $onlyFixedErrors = $request->has('onlydeltan');
        if ($onlyNewErrors || $onlyFixedErrors) {
            $previousBuildId = $this->build->GetPreviousBuildId();
            if ($previousBuildId > 0) {
                $params['previous-build-id'] = $previousBuildId;
            }
            if ($onlyNewErrors) {
                $params['show-new-errors'] = true;
            }
            if ($onlyFixedErrors) {
                $params['show-fixed-errors'] = true;
            }
        }

        return $this->vue('build-build-page', 'Build', $params);
    }

    public function commands(int $build_id): View
    {
        $this->setBuildById($build_id);

        $filters = json_decode(request()->query('filters')) ?? ['all' => []];

        return $this->vue('build-commands-page', 'Commands', [
            'build-id' => $this->build->Id,
            'initial-filters' => $filters,
        ]);
    }

    public function targets(int $build_id): View
    {
        $this->setBuildById($build_id);

        $filters = json_decode(request()->query('filters')) ?? ['all' => []];

        return $this->vue('build-targets-page', 'Targets', [
            'build-id' => $this->build->Id,
            'initial-filters' => $filters,
        ]);
    }

    public function configure(int $build_id): View
    {
        $this->setBuildById($build_id);

        return $this->vue('build-configure', 'Configure', [
            'build-id' => $this->build->Id,
        ]);
    }

    public function notes(int $build_id): View
    {
        $this->setBuildById($build_id);

        return $this->vue('build-notes-page', 'Notes', [
            'build-id' => $this->build->Id,
        ]);
    }

    public function summary(int $build_id): View
    {
        $this->setBuildById($build_id);

        return $this->vue('build-summary', 'Build Summary', [
            'build-id' => $this->build->Id,
        ]);
    }

    public function update(int $build_id): View
    {
        $this->setBuildById($build_id);

        return $this->vue('build-update-page', 'Files Updated', [
            'build-id' => $this->build->Id,
            'repository-type' => $this->project->CvsViewerType,
            'repository-url' => $this->project->CvsUrl,
        ]);
    }

    public function tests(int $build_id): View
    {
        $this->setBuildById($build_id);

        $filters = json_decode(request()->query('filters')) ?? ['all' => []];

        $eloquent_project = Project::findOrFail((int) $this->project->Id);

        return $this->vue('build-tests-page', 'Tests', [
            'build-id' => $this->build->Id,
            'show-test-time-status' => (bool) $eloquent_project->showtesttime,
            'project-name' => $eloquent_project->name,
            'build-time' => Carbon::parse($this->build->StartTime)->toIso8601String(),
            'initial-filters' => $filters,
            'pinned-measurements' => $eloquent_project->pinnedTestMeasurements()->orderBy('position')->pluck('name')->toArray(),
        ]);
    }

    public function coverage(int $build_id): View
    {
        $this->setBuildById($build_id);

        $filters = json_decode(request()->query('filters')) ?? ['all' => []];

        $eloquent_project = Project::findOrFail((int) $this->project->Id);

        return $this->vue('build-coverage-page', 'Coverage', [
            'build-id' => $build_id,
            'initial-filters' => $filters,
            'project-name' => $eloquent_project->name,
            'coverage-percent-cutoff' => $eloquent_project->coveragethreshold,
        ]);
    }

    public function apiBuildSummary(): JsonResponse
    {
        $pageTimer = new PageTimer();

        $this->setBuildById((int) ($_GET['buildid'] ?? -1));

        $date = TestingDay::get($this->project, $this->build->StartTime);

        $response = begin_JSON_response();
        $response['title'] = "{$this->project->Name} - Build Summary";

        $previous_buildid = $this->build->GetPreviousBuildId();
        $current_buildid = $this->build->GetCurrentBuildId();
        $next_buildid = $this->build->GetNextBuildId();

        $menu = [];
        if ($this->build->GetParentId() > 0) {
            $menu['back'] = 'index.php?project=' . urlencode($this->project->Name) . "&parentid={$this->build->GetParentId()}";
        } else {
            $menu['back'] = 'index.php?project=' . urlencode($this->project->Name) . "&date=$date";
        }

        if ($previous_buildid > 0) {
            $menu['previous'] = "/builds/$previous_buildid";

            // Find the last submit date.
            $previous_build = new Build();
            $previous_build->Id = $previous_buildid;
            $previous_build->FillFromId($previous_build->Id);
            $lastsubmitdate = date(FMT_DATETIMETZ, strtotime($previous_build->StartTime . ' UTC'));
        } else {
            $menu['previous'] = false;
            $lastsubmitdate = 0;
        }

        $menu['current'] = "/builds/$current_buildid";

        if ($next_buildid > 0) {
            $menu['next'] = "/builds/$next_buildid";
        } else {
            $menu['next'] = false;
        }

        $response['menu'] = $menu;

        get_dashboard_JSON($this->project->Name, $date, $response);

        // TODO: (williamjallen) verify if this block of code is still necessary
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            $response['user'] = [
                'id' => $user->id,
                'admin' => $user->admin,
            ];
        }

        // Notes added by users.
        $eloquent_build = EloquentBuild::with(['comments', 'comments.user'])->findOrFail((int) $this->build->Id);
        $notes_response = [];
        /**
         * @var Comment $comment
         */
        foreach ($eloquent_build->comments()->with('user')->get() as $comment) {
            $notes_response[] = [
                'user' => $comment->user?->full_name,
                'date' => $comment->timestamp->toString(),
                'status' => match ($comment->status) {
                    Comment::STATUS_NORMAL => '[note]',
                    Comment::STATUS_FIX_IN_PROGRESS => '[fix in progress]',
                    Comment::STATUS_FIXED => '[fixed]',
                    default => '[unknown]',
                },
                'text' => $comment->text,
            ];
        }
        $response['notes'] = $notes_response;

        // Build
        $build_response = [];

        $site_name = $this->build->GetSite()->name;
        $build_response['site'] = $site_name;
        $build_response['sitename_encoded'] = urlencode($site_name);
        $build_response['siteid'] = $this->build->SiteId;

        $build_response['name'] = $this->build->Name;
        $build_response['id'] = $this->build->Id;
        $build_response['stamp'] = $this->build->GetStamp();
        $build_response['time'] = date(FMT_DATETIMETZ, strtotime($this->build->StartTime . ' UTC'));
        $build_response['type'] = $this->build->Type;

        $build_response['note'] = EloquentBuild::findOrFail($this->build->Id)->notes()->count();

        // Find the OS and compiler information
        if ($this->build->GetParentId() > 0) {
            $buildinfo = EloquentBuild::findOrNew($this->build->GetParentId());
        } else {
            $buildinfo = EloquentBuild::findOrNew((int) $this->build->Id);
        }
        $build_response['osname'] = $buildinfo->osname;
        $build_response['osplatform'] = $buildinfo->osplatform;
        $build_response['osrelease'] = $buildinfo->osrelease;
        $build_response['osversion'] = $buildinfo->osversion;
        $build_response['compilername'] = $buildinfo->compilername;
        $build_response['compilerversion'] = $buildinfo->compilerversion;

        $build_response['generator'] = $this->build->Generator;
        $build_response['command'] = $this->build->Command;
        $build_response['starttime'] = date(FMT_DATETIMETZ, strtotime($this->build->StartTime . ' UTC'));
        $build_response['endtime'] = date(FMT_DATETIMETZ, strtotime($this->build->EndTime . ' UTC'));

        $build_response['lastsubmitbuild'] = $previous_buildid;
        $build_response['lastsubmitdate'] = $lastsubmitdate;

        // Add labels to the response
        $build_response['labels'] = $eloquent_build->labels()->pluck('text')->toArray();

        $e_errors = $this->build->GetErrors(['type' => Build::TYPE_ERROR]);
        $e_warnings = $this->build->GetErrors(['type' => Build::TYPE_WARN]);

        $f_errors = $this->build->GetFailures(['type' => Build::TYPE_ERROR]);
        $f_warnings = $this->build->GetFailures(['type' => Build::TYPE_WARN]);

        $nerrors = count($e_errors) + count($f_errors);
        $nwarnings = count($e_warnings) + count($f_warnings);

        $build_response['error'] = $nerrors;

        $build_response['nerrors'] = $nerrors;
        $build_response['nwarnings'] = $nwarnings;

        // Display the build errors

        $errors_response = [];

        foreach ($e_errors as $error_array) {
            $error_response = [];
            $error_response['logline'] = $error_array['logline'];
            $error_response['text'] = $error_array['stdoutput'];
            $error_response['sourcefile'] = $error_array['sourcefile'];
            $error_response['sourceline'] = $error_array['sourceline'];
            $error_response['precontext'] = '';
            $error_response['postcontext'] = '';
            $errors_response[] = $error_response;
        }

        // Display the build failure error

        foreach ($f_errors as $error_array) {
            $error_response = [];
            $error_response['sourcefile'] = $error_array['sourcefile'];
            $error_response['stdoutput'] = $error_array['stdoutput'];
            $error_response['stderror'] = $error_array['stderror'];
            $errors_response[] = $error_response;
        }

        $build_response['errors'] = $errors_response;

        // Display the warnings
        $warnings_response = [];

        foreach ($e_warnings as $error_array) {
            $warning_response = [];
            $warning_response['logline'] = $error_array['logline'];
            $warning_response['text'] = $error_array['stdoutput'];
            $warning_response['sourcefile'] = $error_array['sourcefile'];
            $warning_response['sourceline'] = $error_array['sourceline'];
            $warning_response['precontext'] = '';
            $warning_response['postcontext'] = '';
            $warnings_response[] = $warning_response;
        }

        // Display the build failure warnings

        foreach ($f_warnings as $error_array) {
            $warning_response = [];
            $warning_response['sourcefile'] = $error_array['sourcefile'];
            $warning_response['stdoutput'] = $error_array['stdoutput'];
            $warning_response['stderror'] = $error_array['stderror'];
            $warnings_response[] = $warning_response;
        }

        $build_response['warnings'] = $warnings_response;
        $response['build'] = $build_response;

        // Update
        $update_response = [];
        $update_array = DB::select('
            SELECT *
            FROM
                buildupdate AS u,
                build AS b
            WHERE
                b.updateid = u.id
                AND b.id = ?
        ', [$this->build->Id])[0] ?? [];

        // TODO: (williamjallen) Determine what $buildupdate was supposed to be.  It is currently undefined.
        if (isset($buildupdate)) {
            // show the update only if we have one
            $response['hasupdate'] = true;
            // Checking for locally modify files
            $nerrors = (int) DB::select("
                SELECT count(*) AS c
                FROM
                    updatefile,
                    build
                WHERE
                    updatefile.updateid=build.updateid
                    AND build.id = ?
                    AND author = 'Local User'
            ", [$this->build->Id])[0]->c;

            // Check also if the status is not zero
            if (strlen($update_array->status) > 0 && $update_array->status != '0') {
                $nerrors++;
                $update_response['status'] = $update_array->status;
            }
            $nwarnings = 0;
            $update_response['nerrors'] = $nerrors;
            $update_response['nwarnings'] = $nwarnings;

            $nupdates = (int) DB::select('
                SELECT count(*) AS c
                FROM updatefile, build
                WHERE updatefile.updateid=build.updateid AND build.id=?
            ', [$this->build->Id])[0]->c;
            $update_response['nupdates'] = $nupdates;

            $update_response['command'] = $update_array->command;
            $update_response['type'] = $update_array->type;
            $update_response['starttime'] = date(FMT_DATETIMETZ, strtotime($update_array->starttime . ' UTC'));
            $update_response['endtime'] = date(FMT_DATETIMETZ, strtotime($update_array->endtime . ' UTC'));
        } else {
            $response['hasupdate'] = false;
            $update_response['nerrors'] = 0;
            $update_response['nwarnings'] = 0;
        }
        $response['update'] = $update_response;

        // Configure
        $configure_response = [];
        $configure_array = DB::select('
            SELECT *
            FROM configure c
            JOIN build2configure b2c ON b2c.configureid=c.id
            WHERE b2c.buildid=?
        ', [$this->build->Id])[0] ?? [];
        if ($configure_array !== []) {
            $response['hasconfigure'] = true;
            $nerrors = 0;
            if ($configure_array->status != 0) {
                $nerrors = 1;
            }

            $configure_response['nerrors'] = $nerrors;
            $configure_response['nwarnings'] = $configure_array->warnings;

            $configure_response['status'] = $configure_array->status;
            $configure_response['command'] = $configure_array->command;
            $configure_response['output'] = $configure_array->log;
            $configure_response['starttime'] = date(FMT_DATETIMETZ, strtotime($configure_array->starttime . ' UTC'));
            $configure_response['endtime'] = date(FMT_DATETIMETZ, strtotime($configure_array->endtime . ' UTC'));
            $response['configure'] = $configure_response;
        } else {
            $response['hasconfigure'] = false;
        }

        // Test
        $test_response = [];
        $nerrors = 0;
        $nwarnings = 0;
        $test_response['nerrors'] = $nerrors;
        $test_response['nwarnings'] = $nwarnings;

        $test_response['npassed'] = (int) DB::select("
            SELECT count(1) AS c
            FROM build2test
            WHERE buildid=? AND status='passed'
        ", [$this->build->Id])[0]->c;

        $test_response['nnotrun'] = (int) DB::select("
            SELECT count(1) AS c
            FROM build2test
            WHERE buildid=? AND status='notrun'
        ", [$this->build->Id])[0]->c;

        $test_response['nfailed'] = (int) DB::select("
            SELECT count(1) AS c
            FROM build2test
            WHERE buildid=? AND status='failed'
        ", [$this->build->Id])[0]->c;

        $response['test'] = $test_response;

        // Coverage
        $response['hascoverage'] = false;
        $coverage_array = DB::select('SELECT * FROM coveragesummary WHERE buildid=?', [$this->build->Id])[0] ?? [];
        if ($coverage_array !== []) {
            $total_lines = (int) $coverage_array->loctested + (int) $coverage_array->locuntested;

            $coverage_percent = $total_lines > 0 ? round(($coverage_array->loctested / $total_lines) * 100, 2) : 0;
            $response['coverage'] = $coverage_percent;
            $response['hascoverage'] = true;
        }

        // Previous build
        if ($previous_buildid > 0 && isset($previous_build)) {
            $previous_build_update = EloquentBuild::findOrFail($previous_buildid)->updateStep;

            $response['previousbuild'] = [
                'buildid' => $previous_buildid,
                // Update
                'nupdateerrors' => $previous_build_update->errors ?? 0,
                'nupdatewarnings' => $previous_build_update->warnings ?? 0,
                // Configure
                'nconfigureerrors' => $previous_build->GetNumberOfConfigureErrors(),
                'nconfigurewarnings' => $previous_build->GetNumberOfConfigureWarnings(),
                // Build
                'nerrors' => $previous_build->GetNumberOfErrors(),
                'nwarnings' => $previous_build->GetNumberOfWarnings(),
                // Test
                'ntestfailed' => $previous_build->GetNumberOfFailedTests(),
                'ntestnotrun' => $previous_build->GetNumberOfNotRunTests(),
            ];
        }

        // Next build
        if ($next_buildid > 0) {
            $next_build = new Build();
            $next_build->Id = $next_buildid;
            $next_build->FillFromId($next_build->Id);
            $next_build_update = EloquentBuild::findOrFail($next_buildid)->updateStep;

            $response['nextbuild'] = [
                'buildid' => $next_buildid,
                // Update
                'nupdateerrors' => $next_build_update->errors ?? 0,
                'nupdatewarnings' => $next_build_update->warnings ?? 0,
                // Configure
                'nconfigureerrors' => $next_build->GetNumberOfConfigureErrors(),
                'nconfigurewarnings' => $next_build->GetNumberOfConfigureWarnings(),
                // Build
                'nerrors' => $next_build->GetNumberOfErrors(),
                'nwarnings' => $next_build->GetNumberOfWarnings(),
                // Test
                'ntestfailed' => $next_build->GetNumberOfFailedTests(),
                'ntestnotrun' => $next_build->GetNumberOfNotRunTests(),
            ];
        }

        // Check if this project uses a supported bug tracker.
        $generate_issue_link = false;
        $new_issue_url = '';
        switch ($this->project->BugTrackerType) {
            case 'Buganizer':
            case 'JIRA':
            case 'GitHub':
                $generate_issue_link = true;
                break;
        }
        if ($generate_issue_link) {
            $new_issue_url = RepositoryUtils::generate_bugtracker_new_issue_link($this->build, $this->project);
            $response['bugtracker'] = $this->project->BugTrackerType;
        }
        $response['newissueurl'] = $new_issue_url;

        // Check if this build is related to any others.
        $build_relationship = new BuildRelationship();
        $relationships = $build_relationship->GetRelationships($this->build);
        $response['relationships_to'] = $relationships['to'];
        $response['relationships_from'] = $relationships['from'];
        $response['hasrelationships'] = !empty($response['relationships_to']) || !empty($response['relationships_from']);

        $pageTimer->end($response);
        return response()->json(cast_data_for_JSON($response));
    }

    public function files(int $build_id): View
    {
        $this->setBuildById($build_id);
        return $this->vue('build-files-page', 'Uploads', [
            'build-id' => $build_id,
        ]);
    }

    public function build_file(int $build_id, int $file_id): StreamedResponse
    {
        set_time_limit(0);
        $this->setBuildById($build_id);

        /** @var ?UploadFile $file */
        $file = EloquentBuild::findOrFail($build_id)->uploadedFiles()->find($file_id);

        // Validate that the file is associated with the build.
        if ($file === null) {
            abort(404, 'File not found');
        }

        // The code below satisfies the following requirements:
        // 1) Render text and images in browser (as opposed to forcing a download).
        // 2) Download other files to the proper filename (not a numeric identifier).
        // 3) Support downloading files that are larger than the PHP memory_limit.
        $fp = Storage::readStream("upload/{$file->sha1sum}");
        if ($fp === null) {
            abort(404, 'File not found');
        }

        $filename = $file->filename;
        $headers = [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => "inline/attachment; filename={$filename}",
        ];
        return response()->streamDownload(function () use ($fp): void {
            while (!feof($fp)) {
                echo fread($fp, 1024);
            }
            fclose($fp);
        }, $filename, $headers, 'inline');
    }

    public function manageBuildGroup(): View
    {
        $this->setProjectById(request()->integer('projectid'));
        return $this->angular_view('manageBuildGroup', 'Manage Build Groups');
    }

    public function viewBuildGroup(): View
    {
        $this->setProjectByName(request()->input('project', ''));
        return $this->angular_view('index');
    }

    public function apiBuildUpdateGraph(): JsonResponse
    {
        $this->setBuildById((int) ($_GET['buildid'] ?? -1));

        // Find previous submissions from this build.
        $query_result = DB::select('
            SELECT
                b.id,
                b.starttime,
                bu.nfiles
            FROM build as b
            JOIN buildupdate AS bu ON bu.id = b.updateid
            WHERE
                b.siteid = ?
                AND b.type = ?
                AND b.name = ?
                AND b.projectid = ?
                AND b.starttime <= ?
            ORDER BY b.starttime ASC
        ', [
            $this->build->SiteId,
            $this->build->Type,
            $this->build->Name,
            $this->build->ProjectId,
            $this->build->StartTime,
        ]);

        $response = [];
        $response['data'] = [];
        $response['buildids'] = [];

        foreach ($query_result as $row) {
            $t = strtotime($row->starttime) * 1000; // flot expects milliseconds
            $response['data'][] = [$t, $row->nfiles];
            $response['buildids'][$t] = $row->id;
        }

        return response()->json(cast_data_for_JSON($response));
    }

    public function apiGetPreviousBuilds(): JsonResponse
    {
        $this->setBuildById((int) ($_GET['buildid'] ?? -1));

        // Take subproject into account, such that if there is one, then the
        // previous builds must be associated with the same subproject.
        $subproj_criteria = '';
        $query_params = [];
        if ($this->build->SubProjectId > 0) {
            $subproj_criteria = 'AND b.subprojectid = ?';
            $query_params[] = $this->build->SubProjectId;
        }

        // Get details about previous builds.
        // Currently just grabbing the info used for the graphs and charts
        // on /build/.
        $query_result = DB::select("
                            SELECT
                                b.id,
                                nfiles,
                                configureerrors,
                                configurewarnings,
                                buildwarnings,
                                builderrors,
                                testfailed,
                                b.starttime,
                                b.endtime
                            FROM build AS b
                            LEFT JOIN buildupdate AS bu ON (b.updateid=bu.id)
                            WHERE
                                siteid = ?
                                AND b.type = ?
                                AND name = ?
                                AND projectid = ?
                                AND b.starttime <= ?
                                $subproj_criteria
                            ORDER BY starttime DESC
                            LIMIT 50
                        ", array_merge([
            $this->build->SiteId,
            $this->build->Type,
            $this->build->Name,
            $this->build->ProjectId,
            $this->build->StartTime,
        ], $query_params));

        $builds_response = [];
        foreach ($query_result as $previous_build_row) {
            $builds_response[] = [
                'id' => $previous_build_row->id,
                'nfiles' => $previous_build_row->nfiles ?? 0,
                'configurewarnings' => $previous_build_row->configurewarnings,
                'configureerrors' => $previous_build_row->configureerrors,
                'buildwarnings' => $previous_build_row->buildwarnings,
                'builderrors' => $previous_build_row->builderrors,
                'starttime' => $previous_build_row->starttime,
                'timestamp' => strtotime($previous_build_row->starttime) * 1000, // Milliseconds since epoch.
                'testfailed' => $previous_build_row->testfailed,
                'time' => strtotime($previous_build_row->endtime) - strtotime($previous_build_row->starttime),
            ];
        }

        return response()->json(cast_data_for_JSON([
            'builds' => $builds_response,
        ]));
    }

    /**
     * Lookup whether or not this build is expected.
     * This works only for the most recent dashboard (and future).
     */
    public function apiBuildExpected(): JsonResponse
    {
        $this->setBuildById((int) ($_GET['buildid'] ?? -1));
        $rule = new BuildGroupRule($this->build);
        return response()->json([
            'expected' => $rule->GetExpected(),
        ]);
    }

    public function apiRelateBuilds(): JsonResponse
    {
        $this->setProjectByName(request()->string('project') ?? '');

        if (!request()->has('buildid')) {
            abort(400, '"buildid" parameter required.');
        }
        if (!request()->has('relatedid')) {
            abort(400, '"relatedid" parameter required.');
        }

        $buildid = (int) request()->input('buildid');
        $relatedid = (int) request()->input('relatedid');

        $build = new Build();
        $build->Id = $buildid;
        $relatedbuild = new Build();
        $relatedbuild->Id = $relatedid;
        $buildRelationship = new BuildRelationship();
        $buildRelationship->Build = $build;
        $buildRelationship->RelatedBuild = $relatedbuild;
        $buildRelationship->Project = $this->project;

        switch (request()->method()) {
            case 'GET':
                return $this->apiRelateBuildsGet($buildRelationship);
            case 'POST':
                return $this->apiRelateBuildsPost($buildRelationship);
            case 'DELETE':
                return $this->apiRelateBuildsDelete($buildRelationship);
            default:
                abort(500, 'Unhandled method: ' . request()->method());
        }
    }

    private function apiRelateBuildsGet(BuildRelationship $buildRelationship): JsonResponse
    {
        if ($buildRelationship->Exists()) {
            $buildRelationship->Fill();
            return response()->json($buildRelationship->marshal());
        }
        abort(404, "No relationship exists between Builds {$buildRelationship->Build->Id} and {$buildRelationship->RelatedBuild->Id}");
    }

    private function apiRelateBuildsPost(BuildRelationship $buildRelationship): JsonResponse
    {
        // Create or update the relationship between these two builds.
        if (!request()->has('relationship')) {
            abort(400, '"relationship" parameter required.');
        }
        $relationship = request()->input('relationship');
        $buildRelationship->Relationship = $relationship;
        $exit_status = 200;
        if (!$buildRelationship->Exists()) {
            $exit_status = 201;
        }
        if (!$buildRelationship->Save($error_msg)) {
            if ($error_msg) {
                abort(400, $error_msg);
            } else {
                abort(500, 'Error saving relationship');
            }
        }
        return response()->json($buildRelationship->marshal(), $exit_status);
    }

    private function apiRelateBuildsDelete(BuildRelationship $buildRelationship): JsonResponse
    {
        if (can_administrate_project($this->project->Id)) {
            if ($buildRelationship->Exists()) {
                if (!$buildRelationship->Delete($error_msg)) {
                    if ($error_msg) {
                        abort(400, $error_msg);
                    } else {
                        abort(500, 'Error deleting relationship');
                    }
                }
            }
            abort(204);
        }
        return response()->json();
    }

    public function restApi(): JsonResponse
    {
        $this->setBuildById((int) request()->input('buildid', -1));

        switch (request()->method()) {
            case 'GET':
                return $this->restApiGet();
            case 'POST':
                Gate::authorize('edit-project', $this->project);
                return $this->restApiPost();
            case 'DELETE':
                Gate::authorize('edit-project', $this->project);
                return $this->restApiDelete();
            default:
                abort(500);
        }
    }

    private function restApiGet(): JsonResponse
    {
        $pdo = Database::getInstance()->getPdo();
        $response = [];

        // Are we looking for what went wrong with this build?
        if (request()->has('getproblems')) {
            $response['hasErrors'] = false;
            $response['hasFailingTests'] = false;

            // Details about this build that will be used in SQL queries below.
            $query_params = [
                ':siteid' => $this->build->SiteId,
                ':type' => $this->build->Type,
                ':name' => $this->build->Name,
                ':projectid' => $this->build->ProjectId,
                ':starttime' => $this->build->StartTime,
            ];

            // Prepared statement to find the oldest submission for this build.
            // We do this here because it is potentially used multiple times below.
            $oldest_build_stmt = $pdo->prepare(
                'SELECT starttime FROM build
            WHERE siteid = :siteid AND type = :type AND
                  name = :name AND projectid = :projectid AND
                  starttime <= :starttime
            ORDER BY starttime ASC LIMIT 1');
            $first_submit = null;

            // Check if this build has errors.
            $buildHasErrors = $this->build->BuildErrorCount > 0;
            if ($buildHasErrors) {
                $response['hasErrors'] = true;
                // Find the last occurrence of this build that had no errors.
                $no_errors_stmt = $pdo->prepare(
                    'SELECT starttime FROM build
                WHERE siteid = :siteid AND type = :type AND name = :name AND
                      projectid = :projectid AND starttime <= :starttime AND
                      parentid < 1 AND builderrors < 1
                ORDER BY starttime DESC LIMIT 1');
                pdo_execute($no_errors_stmt, $query_params);
                $last_good_submit = $no_errors_stmt->fetchColumn();
                if ($last_good_submit !== false) {
                    $gmtdate = strtotime($last_good_submit . ' UTC');
                } else {
                    // Find the oldest submission for this build.
                    pdo_execute($oldest_build_stmt, $query_params);
                    $first_submit = $oldest_build_stmt->fetchColumn();
                    $gmtdate = strtotime($first_submit . ' UTC');
                }
                $response['daysWithErrors'] =
                    round((strtotime($this->build->StartTime) - $gmtdate) / (3600 * 24));
                $response['failingSince'] = date(FMT_DATETIMETZ, $gmtdate);
                $response['failingDate'] = substr($response['failingSince'], 0, 10);
            }

            // Check if this build has failed tests.
            $buildHasFailingTests = $this->build->TestFailedCount > 0;
            if ($buildHasFailingTests) {
                $response['hasFailingTests'] = true;
                // Find the last occurrence of this build that had no test failures.
                $no_fails_stmt = $pdo->prepare(
                    'SELECT starttime FROM build
                WHERE siteid = :siteid AND type = :type AND
                        name = :name AND projectid = :projectid AND
                        starttime <= :starttime AND parentid < 1 AND
                        testfailed < 1
                ORDER BY starttime DESC LIMIT 1');
                pdo_execute($no_fails_stmt, $query_params);
                $last_good_submit = $no_fails_stmt->fetchColumn();
                if ($last_good_submit !== false) {
                    $gmtdate = strtotime($last_good_submit . ' UTC');
                } else {
                    // Find the oldest submission for this build.
                    if (null === $first_submit) {
                        pdo_execute($oldest_build_stmt, $query_params);
                        $first_submit = $oldest_build_stmt->fetchColumn();
                    }
                    $gmtdate = strtotime($first_submit . ' UTC');
                }
                $response['daysWithFailingTests'] =
                    round((strtotime($this->build->StartTime) - $gmtdate) / (3600 * 24));
                $response['testsFailingSince'] = date(FMT_DATETIMETZ, $gmtdate);
                $response['testsFailingDate'] =
                    substr($response['testsFailingSince'], 0, 10);
            }
            return response()->json(cast_data_for_JSON($response));
        }
        return response()->json();
    }

    private function restApiPost(): JsonResponse
    {
        $buildgrouprule = new BuildGroupRule($this->build);

        // Should we change whether or not this build is expected?
        if (request()->has('expected') && request()->has('groupid')) {
            $buildgrouprule->Expected = request()->input('expected');
            $buildgrouprule->GroupId = request()->input('groupid');
            $buildgrouprule->SetExpected();
        }

        // Should we move this build to a different group?
        if (request()->has('expected') && request()->has('newgroupid')) {
            $expected = request()->input('expected');
            $newgroupid = request()->input('newgroupid');

            $eloquent_build = EloquentBuild::findOrFail((int) $this->build->Id);

            if (BuildGroup::findOrFail((int) $newgroupid)->project()->isNot($eloquent_build->project)) {
                abort(403, 'Requested build group is not associated with this project.');
            }

            // Remove the build from its previous group.
            $eloquent_build->buildGroups()->detach();
            // Insert it into the new group.
            $eloquent_build->buildGroups()->attach((int) $newgroupid);

            // Mark any previous buildgroup rule as finished as of this time.
            $now = gmdate(FMT_DATETIME);
            $buildgrouprule->SoftDeleteExpiredRules($now);

            // Create the rule for the newly assigned buildgroup.
            $buildgrouprule->GroupId = $newgroupid;
            $buildgrouprule->Expected = $expected;
            $buildgrouprule->StartTime = $now;
            $buildgrouprule->Save();
        }

        // Should we change the 'done' setting for this build?
        if (request()->has('done')) {
            $this->build->MarkAsDone((bool) request()->input('done'));
        }
        return response()->json();
    }

    private function restApiDelete(): JsonResponse
    {
        Log::info("Build #{$this->build->Id} removed manually.");
        DatabaseCleanupUtils::removeBuild($this->build->Id);
        return response()->json();
    }
}
