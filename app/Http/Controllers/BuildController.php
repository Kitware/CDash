<?php

namespace App\Http\Controllers;

use App\Models\Build as EloquentBuild;
use App\Models\Comment;
use App\Models\UploadFile;
use App\Models\User;
use App\Utils\DatabaseCleanupUtils;
use App\Utils\PageTimer;
use App\Utils\RepositoryUtils;
use App\Utils\TestingDay;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\BuildConfigure;
use CDash\Model\BuildError;
use CDash\Model\BuildFailure;
use CDash\Model\BuildGroupRule;
use CDash\Model\BuildRelationship;
use CDash\Model\BuildUpdate;
use CDash\Model\Label;
use CDash\ServiceContainer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BuildController extends AbstractBuildController
{
    public function targets(int $build_id): View
    {
        $this->setBuildById($build_id);

        $filters = json_decode(request()->get('filters')) ?? ['all' => []];

        return $this->vue('build-targets-page', 'Targets', [
            'build-id' => $this->build->Id,
            'initial-filters' => $filters,
        ]);
    }

    // Render the build configure page.
    public function configure(int $build_id): View
    {
        return $this->renderBuildPage($build_id, 'configure');
    }

    // Render the build notes page.
    public function notes(int $build_id): View
    {
        $this->setBuildById($build_id);

        return $this->vue('build-notes-page', 'Notes', [
            'build-id' => $this->build->Id,
        ]);
    }

    // Render the build summary page.
    public function summary(int $build_id): View
    {
        return $this->renderBuildPage($build_id, 'summary', 'Build Summary');
    }

    public function update(int $build_id): View
    {
        return $this->renderBuildPage($build_id, 'update', 'Files Updated');
    }

    public function tests(int $build_id): View
    {
        $this->setBuildById($build_id);

        $filters = json_decode(request()->get('filters')) ?? ['all' => []];

        return $this->vue('build-tests-page', 'Tests', [
            'build-id' => $this->build->Id,
            'initial-filters' => $filters,
        ]);
    }

    protected function renderBuildPage(int $build_id, string $page_name, string $page_title = ''): View
    {
        $this->setBuildById($build_id);
        if ($page_title === '') {
            $page_title = ucfirst($page_name);
        }
        return $this->view("build.{$page_name}", $page_title);
    }

    public function apiBuildSummary(): JsonResponse
    {
        $pageTimer = new PageTimer();

        $this->setBuildById(intval($_GET['buildid'] ?? -1));

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
        // TODO: Make this more efficient by getting all of the labels right away instead of the labelids
        $labelids = $this->build->GetLabels();
        $labels = [];
        foreach ($labelids as $labelid) {
            $label = new Label();
            $label->Id = $labelid;
            $labels[] = $label->GetText();
        }
        $build_response['labels'] = $labels;

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
            $error_response['text'] = $error_array['text'];
            $error_response['sourcefile'] = $error_array['sourcefile'];
            $error_response['sourceline'] = $error_array['sourceline'];
            $error_response['precontext'] = $error_array['precontext'];
            $error_response['postcontext'] = $error_array['postcontext'];
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
            $warning_response['text'] = $error_array['text'];
            $warning_response['sourcefile'] = $error_array['sourcefile'];
            $warning_response['sourceline'] = $error_array['sourceline'];
            $warning_response['precontext'] = $error_array['precontext'];
            $warning_response['postcontext'] = $error_array['postcontext'];
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
                build2update AS b2u
            WHERE
                b2u.updateid = u.id
                AND b2u.buildid = ?
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
                    build2update AS b2u
                WHERE
                    updatefile.updateid=b2u.updateid
                    AND b2u.buildid = ?
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
                FROM updatefile, build2update AS b2u
                WHERE updatefile.updateid=b2u.updateid AND b2u.buildid=?
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
            $previous_build_update = EloquentBuild::findOrFail($previous_buildid)->updates()->first();

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
            $next_build_update = EloquentBuild::findOrFail($next_buildid)->updates()->first();

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

    /**
     * TODO: (williamjallen) this function contains legacy XSL templating and should be converted
     *       to a proper Blade template with Laravel-based DB queries eventually.  This contents
     *       this function are originally from buildOverview.php and have been copied (almost) as-is.
     */
    public function buildOverview(): View|RedirectResponse
    {
        $this->setProjectByName(htmlspecialchars($_GET['project'] ?? ''));

        $date = htmlspecialchars($_GET['date'] ?? '');

        // We select the builds
        $currentstarttime = get_dates($date, $this->project->NightlyTime)[1];

        // Return the available groups
        $selected_group = intval($_POST['groupSelection'] ?? 0);

        // Check the builds
        $beginning_timestamp = $currentstarttime;
        $end_timestamp = $currentstarttime + 3600 * 24;

        $beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
        $end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

        $groupSelectionSQL = '';
        $params = [];
        if ($selected_group > 0) {
            $groupSelectionSQL = ' AND b2g.groupid=? ';
            $params[] = $selected_group;
        }

        $builds = DB::select("
              SELECT
                  s.name AS sitename,
                  b.name AS buildname,
                  be.type,
                  be.sourcefile,
                  be.sourceline,
                  be.text
              FROM
                  build AS b,
                  builderror as be,
                  site AS s,
                  build2group AS b2g
              WHERE
                  b.starttime<?
                  AND b.starttime>?
                  AND b.projectid=?
                  AND be.buildid=b.id
                  AND s.id=b.siteid
                  AND b2g.buildid=b.id
                  $groupSelectionSQL
              ORDER BY
                  be.sourcefile ASC,
                  be.type ASC,
                  be.sourceline ASC
          ", array_merge([$end_UTCDate, $beginning_UTCDate, $this->project->Id], $params));

        $sourcefiles = [];

        // NOTE: Query results are already ordered by sourcefile...
        foreach ($builds as $build_array) {
            $filename = $build_array->sourcefile;

            if (!isset($sourcefiles[$filename])) {
                $sourcefiles[$filename] = [
                    'name' => $filename,
                    'errors' => [],
                    'warnings' => [],
                ];
            }

            $type = (int) $build_array->type === 0 ? 'errors' : 'warnings';
            $sourcefiles[$filename][$type][] = [
                'line' => (int) $build_array->sourceline,
                'sitename' => $build_array->sitename,
                'buildname' => $build_array->buildname,
                'text' => $build_array->text,
            ];
        }

        return $this->view('build.overview', 'Build Overview')
            ->with('selected_group', $selected_group)
            ->with('sourcefiles', $sourcefiles)
            ->with('startdate', date('l, F d Y H:i:s', $currentstarttime));
    }

    public function viewUpdatePageContent(): JsonResponse
    {
        $pageTimer = new PageTimer();

        $this->setBuildById(intval($_GET['buildid'] ?? -1));

        $date = TestingDay::get($this->project, $this->build->StartTime);
        $response = begin_JSON_response();
        get_dashboard_JSON($this->project->Name, $date, $response);

        // Menu
        $menu_response = [];
        $menu_response['back'] = "index.php?project={$this->project->Name}&date=$date";

        $previous_buildid = $this->build->GetPreviousBuildId();
        $current_buildid = $this->build->GetCurrentBuildId();
        $next_buildid = $this->build->GetNextBuildId();

        if ($previous_buildid > 0) {
            $menu_response['previous'] = "/builds/$previous_buildid/update";
        } else {
            $menu_response['previous'] = false;
        }

        if ($current_buildid > 0) {
            $menu_response['current'] = "/builds/$current_buildid/update";
        } else {
            $menu_response['current'] = false;
        }

        if ($next_buildid > 0) {
            $menu_response['next'] = "/builds/$next_buildid/update";
        } else {
            $menu_response['next'] = false;
        }
        $response['menu'] = $menu_response;

        // Build
        $site = $this->build->GetSite();

        $build_response = [];
        $build_response['site'] = $site->name;
        $build_response['siteid'] = $site->id;
        $build_response['buildname'] = $this->build->Name;
        $build_response['buildid'] = $this->build->Id;
        $build_response['buildtime'] = date('D, d M Y H:i:s T', strtotime($this->build->StartTime . ' UTC'));
        $response['build'] = $build_response;

        // Update
        $update = new BuildUpdate();
        $update->BuildId = $this->build->Id;
        $update->FillFromBuildId();

        $update_response = [];
        if (strlen($update->Status) > 0 && $update->Status != '0') {
            $update_response['status'] = $update->Status;
        } else {
            $update_response['status'] = ''; // empty status
        }
        $update_response['revision'] = $update->Revision;
        $update_response['priorrevision'] = $update->PriorRevision;
        $update_response['path'] = $update->Path;
        $update_response['revisionurl'] =
            RepositoryUtils::get_revision_url($this->project->Id, $update->Revision, $update->PriorRevision);
        $update_response['revisiondiff'] =
            RepositoryUtils::get_revision_url($this->project->Id, $update->PriorRevision, ''); // no prior prior revision...
        $response['update'] = $update_response;

        $directoryarray = [];
        $updatearray1 = [];
        // Create an array so we can sort it
        foreach ($update->GetFiles() as $update_file) {
            $file = [];
            $file['filename'] = $update_file->Filename;
            $file['author'] = $update_file->Author;
            $file['status'] = $update_file->Status;
            $file['email'] = '';

            $file['log'] = $update_file->Log;
            $file['revision'] = $update_file->Revision;
            $updatearray1[] = $file;
            $directoryarray[] = substr($update_file->Filename, 0, strrpos($update_file->Filename, '/'));
        }

        $directoryarray = array_unique($directoryarray);

        usort($directoryarray, fn ($a, $b) => $a > $b ? 1 : 0);
        usort($updatearray1, function ($a, $b) {
            // Extract directory
            $filenamea = $a['filename'];
            $filenameb = $b['filename'];
            return $filenamea > $filenameb ? 1 : 0;
        });

        $updatearray = [];

        foreach ($directoryarray as $directory) {
            foreach ($updatearray1 as $update) {
                $filename = $update['filename'];
                if (substr($filename, 0, strrpos($filename, '/')) == $directory) {
                    $updatearray[] = $update;
                }
            }
        }

        // These variables represent a list of directories that contain a list of files.
        $updated_files = [];
        $modified_files = [];
        $conflicting_files = [];

        $num_updated_files = 0;
        $num_modified_files = 0;
        $num_conflicting_files = 0;

        foreach ($updatearray as $file) {
            $filename = $file['filename'];
            $filename = str_replace('\\', '/', $filename);
            $directory = substr($filename, 0, strrpos($filename, '/'));

            $pos = strrpos($filename, '/');
            if ($pos !== false) {
                $filename = substr($filename, $pos + 1);
            }

            $log = $file['log'];
            $status = $file['status'];
            $revision = $file['revision'];
            $log = str_replace("\r", ' ', $log);
            $log = str_replace("\n", ' ', $log);
            // Do this twice so that <something> ends up as
            // &amp;lt;something&amp;gt; because it gets sent to a
            // javascript function not just displayed as html
            $log = XMLStrFormat($log); // Apparently no need to do this twice anymore
            $log = XMLStrFormat($log);

            $log = trim($log);

            $file['log'] = $log;
            $file['filename'] = $filename;
            $file['bugid'] = '0';
            $file['bugpos'] = '0';
            // This field is redundant because of the way our data is organized.
            unset($file['status']);

            if ($status === 'UPDATED') {
                $diff_url = RepositoryUtils::get_diff_url($this->project->Id, $this->project->CvsUrl, $directory, $filename, $revision);
                $diff_url = XMLStrFormat($diff_url);
                $file['diffurl'] = $diff_url;
                $this->add_file($file, $directory, $updated_files);
                $num_updated_files++;
            } elseif ($status === 'MODIFIED') {
                $diff_url = RepositoryUtils::get_diff_url($this->project->Id, $this->project->CvsUrl, $directory, $filename);
                $diff_url = XMLStrFormat($diff_url);
                $file['diffurl'] = $diff_url;
                $this->add_file($file, $directory, $modified_files);
                $num_modified_files++;
            } else {
                // CONFLICTED
                $diff_url = RepositoryUtils::get_diff_url($this->project->Id, $this->project->CvsUrl, $directory, $filename);
                $diff_url = XMLStrFormat($diff_url);
                $file['diffurl'] = $diff_url;
                $this->add_file($file, $directory, $conflicting_files);
                $num_conflicting_files++;
            }
        }

        $update_groups = [
            [
                'description' => "{$this->project->Name} Updated Files ($num_updated_files)",
                'directories' => $updated_files,
            ],
            [
                'description' => "Modified Files ($num_modified_files)",
                'directories' => $modified_files,
            ],
            [
                'description' => "Conflicting Files ($num_conflicting_files)",
                'directories' => $conflicting_files,
            ],
        ];
        $response['updategroups'] = $update_groups;

        $pageTimer->end($response);
        return response()->json(cast_data_for_JSON($response));
    }

    private function add_file($file, string $directory, array &$list_of_files): void
    {
        $idx = array_search($directory, array_column($list_of_files, 'name'));
        if ($idx === false) {
            $d = [];
            $d['name'] = $directory;
            $d['files'] = [$file];
            $list_of_files[] = $d;
        } else {
            $list_of_files[$idx]['files'][] = $file;
        }
    }

    public function files(int $build_id): View
    {
        $this->setBuildById($build_id);
        return $this->vue('build-files-page', 'Uploads', [
            'build-id' => $build_id,
        ], true);
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

    public function ajaxBuildNote(): View
    {
        $this->setBuildById(intval($_GET['buildid'] ?? -1));
        Gate::authorize('edit-project', $this->project);

        $notes = DB::select('SELECT * FROM comments WHERE buildid=? ORDER BY timestamp ASC', [$this->build->Id]);
        foreach ($notes as $note) {
            /** @var User $user */
            $user = User::where('id', intval($note->userid))->first();
            $note->user = $user;
        }

        return $this->view('build.note', 'Notes')
            ->with('notes', $notes);
    }

    public function apiViewBuildError(): JsonResponse
    {
        $pageTimer = new PageTimer();

        if (!isset($_GET['buildid']) || !is_numeric($_GET['buildid'])) {
            abort(400, 'Invalid buildid!');
        }
        $this->setBuildById((int) $_GET['buildid']);

        $eloquentBuild = EloquentBuild::findOrFail((int) $this->build->Id);

        $service = ServiceContainer::getInstance();

        $response = begin_JSON_response();
        $response['title'] = $this->project->Name;

        $type = intval($_GET['type'] ?? 0);

        $date = TestingDay::get($this->project, $this->build->StartTime);
        get_dashboard_JSON($this->project->Name, $date, $response);

        $menu = [];
        if ($this->build->GetParentId() > 0) {
            $menu['back'] = 'index.php?project=' . urlencode($this->project->Name) . "&parentid={$this->build->GetParentId()}";
        } else {
            $menu['back'] = 'index.php?project=' . urlencode($this->project->Name) . '&date=' . $date;
        }

        $previous_buildid = $this->build->GetPreviousBuildId();
        $current_buildid = $this->build->GetCurrentBuildId();
        $next_buildid = $this->build->GetNextBuildId();

        $menu['previous'] = $previous_buildid > 0 ? "viewBuildError.php?type=$type&buildid=$previous_buildid" : false;
        $menu['current'] = "viewBuildError.php?type=$type&buildid=$current_buildid";
        $menu['next'] = $next_buildid > 0 ? "viewBuildError.php?type=$type&buildid=$next_buildid" : false;

        $response['menu'] = $menu;

        // Site
        $extra_build_fields = [
            'site' => $this->build->GetSite()->name,
        ];

        // Update
        if ($eloquentBuild->updates()->exists()) {
            $revision = $eloquentBuild->updates()->firstOrFail()->revision;
            $extra_build_fields['revision'] = $revision;
        } else {
            $revision = null;
        }

        // Build
        $response['build'] = Build::MarshalResponseArray($this->build, $extra_build_fields);

        $buildfailure = $service->get(BuildFailure::class);

        // Set the error
        if ($type === 0) {
            $response['errortypename'] = 'Error';
            $response['nonerrortypename'] = 'Warning';
            $response['nonerrortype'] = 1;
        } else {
            $response['errortypename'] = 'Warning';
            $response['nonerrortypename'] = 'Error';
            $response['nonerrortype'] = 0;
        }

        $response['parentBuild'] = $this->build->IsParentBuild();
        $response['errors'] = [];
        $response['numErrors'] = 0;

        if (isset($_GET['onlydeltan'])) {
            // Build error table
            $resolvedBuildErrors = $this->build->GetResolvedBuildErrors($type);
            if ($resolvedBuildErrors !== false) {
                while ($resolvedBuildError = $resolvedBuildErrors->fetch()) {
                    $this->addErrorResponse(BuildError::marshal($resolvedBuildError, $this->project, $revision), $response);
                }
            }

            // Build failure table
            $resolvedBuildFailures = $this->build->GetResolvedBuildFailures($type);
            while ($resolvedBuildFailure = $resolvedBuildFailures->fetch()) {
                $marshaledResolvedBuildFailure = BuildFailure::marshal($resolvedBuildFailure, $this->project, $revision, false, $buildfailure);

                if ($this->project->DisplayLabels) {
                    get_labels_JSON_from_query_results('
                        SELECT text
                        FROM label, label2buildfailure
                        WHERE
                            label.id=label2buildfailure.labelid
                            AND label2buildfailure.buildfailureid=?
                        ORDER BY text ASC
                    ', [intval($resolvedBuildFailure['id'])], $marshaledResolvedBuildFailure);
                }

                $marshaledResolvedBuildFailure = array_merge($marshaledResolvedBuildFailure, [
                    'stderr' => $resolvedBuildFailure['stderror'],
                    'stderrorrows' => min(10, substr_count($resolvedBuildFailure['stderror'], "\n") + 1),
                    'stdoutput' => $resolvedBuildFailure['stdoutput'],
                    'stdoutputrows' => min(10, substr_count($resolvedBuildFailure['stdoutput'], "\n") + 1),
                ]);

                $this->addErrorResponse($marshaledResolvedBuildFailure, $response);
            }
        } else {
            $filter_error_properties = ['type' => $type];

            if (isset($_GET['onlydeltap'])) {
                $filter_error_properties['newstatus'] = Build::STATUS_NEW;
            }

            // Build error table
            $buildErrors = $this->build->GetErrors($filter_error_properties);

            foreach ($buildErrors as $error) {
                $this->addErrorResponse(BuildError::marshal($error, $this->project, $revision), $response);
            }

            // Build failure table
            $buildFailures = $this->build->GetFailures(['type' => $type]);

            foreach ($buildFailures as $fail) {
                $failure = BuildFailure::marshal($fail, $this->project, $revision, true, $buildfailure);

                if ($this->project->DisplayLabels) {
                    /** @var Label $label */
                    $label = $service->get(Label::class);
                    $label->BuildFailureId = $fail['id'];
                    $rows = $label->GetTextFromBuildFailure();
                    if ($rows && count($rows) > 0) {
                        $failure['labels'] = [];
                        foreach ($rows as $row) {
                            $failure['labels'][] = $row->text;
                        }
                    }
                }
                $this->addErrorResponse($failure, $response);
            }
        }

        if ($this->build->IsParentBuild()) {
            $response['numSubprojects'] = count(array_unique(array_map(fn ($buildError) => $buildError['subprojectid'], $response['errors'])));
        }

        $pageTimer->end($response);
        return response()->json(cast_data_for_JSON($response));
    }

    public function manageBuildGroup(): View
    {
        $this->setProjectById(request()->integer('projectid'));
        return $this->angular_view('manageBuildGroup', 'Manage Build Groups');
    }

    public function viewBuildError(): View
    {
        $this->setBuildById(request()->integer('buildid'));
        return $this->angular_view('viewBuildError', 'Build Errors');
    }

    public function viewBuildGroup(): View
    {
        $this->setProjectByName(request()->input('project'));
        return $this->angular_view('index');
    }

    /**
     * Add a new (marshaled) error to the response.
     * Keeps track of the id necessary for frontend JS, and updates
     * the numErrors response key.
     *
     * @todo id should probably just be a unique id for the builderror?
     * builderror table currently has no integer that serves as a unique identifier.
     *
     * @param array<string,mixed> $response
     **/
    private function addErrorResponse(mixed $data, array &$response): void
    {
        $data['id'] = $response['numErrors'];
        $response['numErrors']++;

        $response['errors'][] = $data;
    }

    public function apiViewConfigure(): JsonResponse
    {
        $pageTimer = new PageTimer();

        if (!isset($_GET['buildid']) || !is_numeric($_GET['buildid'])) {
            abort(400, 'Invalid buildid!');
        }
        $this->setBuildById((int) $_GET['buildid']);

        $response = begin_JSON_response();

        $date = TestingDay::get($this->project, $this->build->StartTime);
        get_dashboard_JSON($this->project->Name, $date, $response);
        $response['title'] = "{$this->project->Name} - Configure";

        // Menu
        $menu_response = [];
        if ($this->build->GetParentId() > 0) {
            $menu_response['back'] = 'index.php?project=' . urlencode($this->project->Name) . "&parentid={$this->build->GetParentId()}";
        } else {
            $menu_response['back'] = 'index.php?project=' . urlencode($this->project->Name) . '&date=' . $date;
        }

        $previous_buildid = $this->build->GetPreviousBuildId();
        $next_buildid = $this->build->GetNextBuildId();
        $current_buildid = $this->build->GetCurrentBuildId();

        $menu_response['previous'] = $previous_buildid > 0 ? "/builds/$previous_buildid/configure" : false;
        $menu_response['current'] = "/builds/$current_buildid/configure";
        $menu_response['next'] = $next_buildid > 0 ? "/builds/$next_buildid/configure" : false;

        $response['menu'] = $menu_response;

        // Configure
        $configures_response = [];
        $configures = $this->build->GetConfigures();
        $has_subprojects = 0;
        while ($configure = $configures->fetch()) {
            if (isset($configure['subprojectid'])) {
                $has_subprojects = 1;
            }
            $configures_response[] = BuildConfigure::marshal($configure);
        }
        $response['configures'] = $configures_response;

        // Build
        $site = $this->build->GetSite();
        $response['build'] = [
            'site' => $site->name,
            'siteid' => $site->id,
            'buildname' => $this->build->Name,
            'buildid' => $this->build->Id,
            'buildstarttime' => $this->build->StartTime,
            'hassubprojects' => $has_subprojects,
        ];

        $pageTimer->end($response);
        return response()->json(cast_data_for_JSON($response));
    }

    public function apiBuildUpdateGraph(): JsonResponse
    {
        $this->setBuildById(intval($_GET['buildid'] ?? -1));

        // Find previous submissions from this build.
        $query_result = DB::select('
            SELECT
                b.id,
                b.starttime,
                bu.nfiles
            FROM build as b
            JOIN build2update AS b2u ON b2u.buildid = b.id
            JOIN buildupdate AS bu ON bu.id = b2u.updateid
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
        $this->setBuildById(intval($_GET['buildid'] ?? -1));

        // Take subproject into account, such that if there is one, then the
        // previous builds must be associated with the same subproject.
        $subproj_table = '';
        $subproj_criteria = '';
        $query_params = [];
        if ($this->build->SubProjectId > 0) {
            $subproj_table = 'INNER JOIN subproject2build AS sp2b ON (b.id=sp2b.buildid)';
            $subproj_criteria = 'AND sp2b.subprojectid = ?';
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
                            LEFT JOIN build2update AS b2u ON (b2u.buildid=b.id)
                            LEFT JOIN buildupdate AS bu ON (b2u.updateid=bu.id)
                            $subproj_table
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
        $this->setBuildById(intval($_GET['buildid'] ?? -1));
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
                    if (is_null($first_submit)) {
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

            // Remove the build from its previous group.
            DB::delete('DELETE FROM build2group WHERE buildid = ?', [$this->build->Id]);

            // Insert it into the new group.
            DB::insert('
                INSERT INTO build2group(groupid, buildid)
                VALUES (?, ?)
            ', [$newgroupid, $this->build->Id]);

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
