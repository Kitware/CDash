<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PageTimer;
use App\Services\TestingDay;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\BuildConfigure;
use CDash\Model\BuildError;
use CDash\Model\BuildFailure;
use CDash\Model\BuildUpdate;
use CDash\Model\Label;
use CDash\ServiceContainer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use PDO;

class BuildController extends AbstractBuildController
{
    // Render the build configure page.
    public function configure($build_id = null)
    {
        return $this->renderBuildPage($build_id, 'configure');
    }

    // Render the build notes page.
    public function notes($build_id = null)
    {
        return $this->renderBuildPage($build_id, 'notes');
    }

    // Render the build summary page.
    public function summary($build_id = null)
    {
        return $this->renderBuildPage($build_id, 'summary', 'Build Summary');
    }

    protected function renderBuildPage(int $build_id, string $page_name, string $page_title = '')
    {
        $this->setBuildById($build_id);
        if ($page_title === '') {
            $page_title = ucfirst($page_name);
        }
        return view("build.{$page_name}")
            ->with('build', json_encode($this->build))
            ->with('project', $this->project)
            ->with('title', $page_title);
    }

    /**
     * TODO: (williamjallen) this function contains legacy XSL templating and should be converted
     *       to a proper Blade template with Laravel-based DB queries eventually.  This contents
     *       this function are originally from buildOverview.php and have been copied (almost) as-is.
     */
    public function buildOverview(): View|RedirectResponse
    {
        $projectname = htmlspecialchars($_GET['project'] ?? '');

        if (strlen($projectname) === 0) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => 'Project not specified'
            ]);
        }

        $this->setProjectByName($projectname);

        $date = htmlspecialchars($_GET['date'] ?? '');

        $xml = begin_XML_for_XSLT();
        $xml .= get_cdash_dashboard_xml_by_name($projectname, $date);

        $db = Database::getInstance();

        // We select the builds
        list($previousdate, $currentstarttime, $nextdate, $today) = get_dates($date, $this->project->NightlyTime);
        $xml .= '<menu>';
        $xml .= add_XML_value('previous', 'buildOverview.php?project=' . urlencode($projectname) . '&date=' . $previousdate);
        if (has_next_date($date, $currentstarttime)) {
            $xml .= add_XML_value('next', 'buildOverview.php?project=' . urlencode($projectname) . '&date=' . $nextdate);
        } else {
            $xml .= add_XML_value('nonext', '1');
        }
        $xml .= add_XML_value('current', 'buildOverview.php?project=' . urlencode($projectname) . '&date=');

        $xml .= add_XML_value('back', 'index.php?project=' . urlencode($projectname) . '&date=' . $today);
        $xml .= '</menu>';

        // Return the available groups
        $groupSelection = $_POST['groupSelection'] ?? 0;
        $groupSelection = intval($groupSelection);

        $buildgroup = $db->executePrepared('SELECT id, name FROM buildgroup WHERE projectid=?', [$this->project->Id]);
        foreach ($buildgroup as $buildgroup_array) {
            $xml .= '<group>';
            $xml .= add_XML_value('id', $buildgroup_array['id']);
            $xml .= add_XML_value('name', $buildgroup_array['name']);
            if ($groupSelection === intval($buildgroup_array['id'])) {
                $xml .= add_XML_value('selected', '1');
            }
            $xml .= '</group>';
        }

        // Check the builds
        $beginning_timestamp = $currentstarttime;
        $end_timestamp = $currentstarttime + 3600 * 24;

        $beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
        $end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

        $groupSelectionSQL = '';
        $params = [];
        if ($groupSelection > 0) {
            $groupSelectionSQL = " AND b2g.groupid=? ";
            $params[] = $groupSelection;
        }

        $builds = $db->executePrepared("
              SELECT
                  s.name,
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

        echo pdo_error();

        if (count($builds) === 0) {
            $xml .= '<message>No warnings or errors today!</message>';
        }

        $current_file = 'ThisIsMyFirstFile';
        foreach ($builds as $build_array) {
            if ($build_array['sourcefile'] != $current_file) {
                if ($current_file != 'ThisIsMyFirstFile') {
                    $xml .= '</sourcefile>';
                }
                $xml .= '<sourcefile>';
                $xml .= '<name>' . $build_array['sourcefile'] . '</name>';
                $current_file = $build_array['sourcefile'];
            }

            if (intval($build_array['type']) === 0) {
                $xml .= '<error>';
            } else {
                $xml .= '<warning>';
            }
            $xml .= '<line>' . $build_array['sourceline'] . '</line>';
            $textarray = explode("\n", $build_array['text']);
            foreach ($textarray as $text) {
                if (strlen($text) > 0) {
                    $xml .= add_XML_value('text', $text);
                }
            }
            $xml .= '<sitename>' . $build_array['name'] . '</sitename>';
            $xml .= '<buildname>' . $build_array['buildname'] . '</buildname>';
            if ($build_array['type'] == 0) {
                $xml .= '</error>';
            } else {
                $xml .= '</warning>';
            }
        }

        if (count($builds) > 0) {
            $xml .= '</sourcefile>';
        }
        $xml .= '</cdash>';

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/buildOverview', true),
            'project' => $this->project,
            'title' => 'Build Overview'
        ]);
    }

    public function viewFiles(): View|RedirectResponse
    {
        if (!isset($_GET['buildid'])) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => 'Build id not set',
                'title' => 'View Files'
            ]);
        }

        $this->setBuildById((int) $_GET['buildid']);

        $Site = $this->build->GetSite();

        @$date = $_GET['date'];
        if ($date != null) {
            $date = htmlspecialchars($date);
        }

        $xml = begin_XML_for_XSLT();
        $xml .= get_cdash_dashboard_xml_by_name($this->project->Name, $date);
        $xml .= add_XML_value('title', 'CDash - Uploaded files');
        $xml .= add_XML_value('menutitle', 'CDash');
        $xml .= add_XML_value('menusubtitle', 'Uploaded files');

        $xml .= '<hostname>' . $_SERVER['SERVER_NAME'] . '</hostname>';
        $xml .= '<date>' . date('r') . '</date>';
        $xml .= '<backurl>index.php</backurl>';

        $xml .= '<buildid>' . $this->build->Id . '</buildid>';
        $xml .= '<buildname>' . $this->build->Name . '</buildname>';
        $xml .= '<buildstarttime>' . $this->build->StartTime . '</buildstarttime>';
        $xml .= '<siteid>' . $Site->id . '</siteid>';
        $xml .= '<sitename>' . $Site->name . '</sitename>';

        $uploadFilesOrURLs = $this->build->GetUploadedFilesOrUrls();

        foreach ($uploadFilesOrURLs as $uploadFileOrURL) {
            if (!$uploadFileOrURL->IsUrl) {
                $xml .= '<uploadfile>';
                $xml .= '<id>' . $uploadFileOrURL->Id . '</id>';
                $xml .= '<href>upload/' . $uploadFileOrURL->Sha1Sum . '/' . $uploadFileOrURL->Filename . '</href>';
                $xml .= '<sha1sum>' . $uploadFileOrURL->Sha1Sum . '</sha1sum>';
                $xml .= '<filename>' . $uploadFileOrURL->Filename . '</filename>';
                $xml .= '<filesize>' . $uploadFileOrURL->Filesize . '</filesize>';

                $filesize = $uploadFileOrURL->Filesize;
                $ext = 'b';
                if ($filesize > 1024) {
                    $filesize /= 1024;
                    $ext = 'Kb';
                }
                if ($filesize > 1024) {
                    $filesize /= 1024;
                    $ext = 'Mb';
                }
                if ($filesize > 1024) {
                    $filesize /= 1024;
                    $ext = 'Gb';
                }
                if ($filesize > 1024) {
                    $filesize /= 1024;
                    $ext = 'Tb';
                }

                $xml .= '<filesizedisplay>' . round($filesize) . ' ' . $ext . '</filesizedisplay>';
                $xml .= '<isurl>' . $uploadFileOrURL->IsUrl . '</isurl>';
                $xml .= '</uploadfile>';
            } else {
                $xml .= '<uploadurl>';
                $xml .= '<id>' . $uploadFileOrURL->Id . '</id>';
                $xml .= '<filename>' . htmlspecialchars($uploadFileOrURL->Filename) . '</filename>';
                $xml .= '</uploadurl>';
            }
        }

        $xml .= '</cdash>';

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/viewFiles', true),
            'title' => 'View Files'
        ]);
    }

    public function ajaxBuildNote(): View
    {
        $this->setBuildById(intval($_GET['buildid'] ?? -1));
        Gate::authorize('edit-project', $this->project);

        $notes = DB::select('SELECT * FROM buildnote WHERE buildid=? ORDER BY timestamp ASC', [$this->build->Id]);
        foreach ($notes as $note) {
            /** @var User $user */
            $user = User::where('id', intval($note->userid))->first();
            $note->user = $user;
        }

        return view('build.note')
            ->with('notes', $notes);
    }

    public function apiViewBuildError(): JsonResponse
    {
        $pageTimer = new PageTimer();

        if (!isset($_GET['buildid']) || !is_numeric($_GET['buildid'])) {
            abort(400, 'Invalid buildid!');
        }
        $this->setBuildById((int) $_GET['buildid']);

        $service = ServiceContainer::getInstance();

        $response = begin_JSON_response();
        $response['title'] = $this->project->Name;

        $type = intval($_GET['type'] ?? 0);

        $date = TestingDay::get($this->project, $this->build->StartTime);
        get_dashboard_JSON_by_name($this->project->Name, $date, $response);

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
            'site' => $this->build->GetSite()->name
        ];

        // Update
        $update = $service->get(BuildUpdate::class);
        $update->BuildId = $this->build->Id;
        $build_update = $update->GetUpdateForBuild();
        if (is_array($build_update)) {
            $revision = $build_update['revision'];
            $extra_build_fields['revision'] = $revision;
        } else {
            $revision = null;
        }

        // Build
        $response['build'] = Build::MarshalResponseArray($this->build, $extra_build_fields);

        $builderror = $service->get(BuildError::class);
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
                    $this->addErrorResponse(BuildError::marshal($resolvedBuildError, $this->project, $revision, $builderror), $response);
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

                $marshaledResolvedBuildFailure = array_merge($marshaledResolvedBuildFailure, array(
                    'stderr' => $resolvedBuildFailure['stderror'],
                    'stderrorrows' => min(10, substr_count($resolvedBuildFailure['stderror'], "\n") + 1),
                    'stdoutput' => $resolvedBuildFailure['stdoutput'],
                    'stdoutputrows' => min(10, substr_count($resolvedBuildFailure['stdoutputrows'], "\n") + 1),
                ));

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
                $this->addErrorResponse(BuildError::marshal($error, $this->project, $revision, $builderror), $response);
            }

            // Build failure table
            $buildFailures = $this->build->GetFailures(['type' => $type]);

            foreach ($buildFailures as $fail) {
                $failure = BuildFailure::marshal($fail, $this->project, $revision, true, $buildfailure);

                if ($this->project->DisplayLabels) {
                    /** @var Label $label */
                    $label = $service->get(Label::class);
                    $label->BuildFailureId = $fail['id'];
                    $rows = $label->GetTextFromBuildFailure(PDO::FETCH_OBJ);
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
            $response['numSubprojects'] = count(array_unique(array_map(function ($buildError) {
                return $buildError['subprojectid'];
            }, $response['errors'])));
        }

        $pageTimer->end($response);
        return response()->json(cast_data_for_JSON($response));
    }

    public function manageBuildGroup(): Response
    {
        return response()->angular_view('manageBuildGroup');
    }

    public function viewBuildError(): Response
    {
        return response()->angular_view('viewBuildError');
    }

    public function viewBuildGroup(): Response
    {
        return response()->angular_view('index');
    }

    /**
     * Add a new (marshaled) error to the response.
     * Keeps track of the id necessary for frontend JS, and updates
     * the numErrors response key.
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

        $menu_response['previous'] = $previous_buildid > 0 ? "/build/$previous_buildid/configure" : false;
        $menu_response['current'] = "/build/$current_buildid/configure";
        $menu_response['next'] = $next_buildid > 0 ? "/build/$next_buildid/configure" : false;

        $response['menu'] = $menu_response;

        // Configure
        $configures_response = [];
        $configures = $this->build->GetConfigures();
        $has_subprojects = 0;
        while ($configure = $configures->fetch()) {
            if (isset($configure['subprojectid'])) {
                $has_subprojects = 1;
            }
            $configures_response[] = buildconfigure::marshal($configure);
        }
        $response['configures'] = $configures_response;

        // Build
        $site = $this->build->GetSite();
        $build_response = [];
        $build_response['site'] = $site->name;
        $build_response['siteid'] = $site->id;
        $build_response['buildname'] = $this->build->Name;
        $build_response['buildid'] = $this->build->Id;
        $build_response['hassubprojects'] = $has_subprojects;
        $response['build'] = $build_response;

        $pageTimer->end($response);
        return response()->json(cast_data_for_JSON($response));
    }
}
