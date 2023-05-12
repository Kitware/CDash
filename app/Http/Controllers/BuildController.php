<?php
namespace App\Http\Controllers;

require_once 'include/common.php';
require_once 'include/defines.php';

use App\Services\TestingDay;
use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Project;
use CDash\Model\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BuildController extends ProjectController
{
    protected $build;

    public function __construct()
    {
        parent::__construct();
        $this->build = null;
        $this->date = date(FMT_DATETIME);
    }

    // Fetch data used by all build-specific pages in CDash.
    protected function setup($build_id = null): void
    {
        if (!$build_id) {
            abort(404);
        }

        $this->build = new Build();
        $this->build->Id = $build_id;
        $this->build->FillFromId($build_id);
        if (!$this->build->Exists()) {
            abort(404);
        }

        parent::setup($this->build->GetProject());
        if (!is_null($this->project)) {
            $this->date = TestingDay::get($this->project, $this->build->StartTime);
        }
    }

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

    protected function renderBuildPage($build_id = null, $page_name, $page_title = '')
    {
        $this->setup($build_id);
        if (!$this->authOk) {
            return $this->redirectToLogin();
        }
        if (!$page_title) {
            $page_title = ucfirst($page_name);
        }
        return view("build.{$page_name}")
            ->with('build', json_encode($this->build))
            ->with('cdashCss', $this->cdashCss)
            ->with('date', json_encode($this->date))
            ->with('logo', json_encode($this->logo))
            ->with('project', $this->project)
            ->with('projectname', json_encode($this->project->Name))
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

        $date = htmlspecialchars($_GET['date'] ?? '');

        $xml = begin_XML_for_XSLT();
        $xml .= get_cdash_dashboard_xml_by_name($projectname, $date);

        $db = Database::getInstance();

        // Get some information about the specified project
        $project_array = $db->executePreparedSingleRow('SELECT id, nightlytime FROM project WHERE name = ?', [$projectname]);
        if (empty($project_array)) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => "project $projectname not found"
            ]);
        }

        $project = new Project();
        $project->Id = intval($project_array['id']);
        $project->Fill();

        $policy = checkUserPolicy(intval($project_array['id']));
        if ($policy !== true) {
            return $policy;
        }

        $projectid = intval($project_array['id']);
        $nightlytime = $project_array['nightlytime'];

        // We select the builds
        list($previousdate, $currentstarttime, $nextdate, $today) = get_dates($date, $nightlytime);
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

        $buildgroup = $db->executePrepared('SELECT id, name FROM buildgroup WHERE projectid=?', [$projectid]);
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
          ", array_merge([$end_UTCDate, $beginning_UTCDate, $projectid], $params));

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
            'project' => $project,
            'title' => 'Build Overview'
        ]);
    }

    public function buildProperties(): View
    {
        return view('build.properties');
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

        $buildid = intval($_GET['buildid']);
        $Build = new Build();
        $Build->Id = $buildid;
        $Build->FillFromId($buildid);
        $Site = new Site();
        $Site->Id = $Build->SiteId;

        $db = Database::getInstance();
        $build_array = $db->executePreparedSingleRow('SELECT projectid FROM build WHERE id=?', [$buildid]);
        if (!isset($build_array['projectid'])) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => 'Build does not exist. Maybe it has been deleted.',
                'title' => 'View Files'
            ]);
        }
        $projectid = $build_array['projectid'];

        $policy = checkUserPolicy($projectid);
        if ($policy !== true) {
            return $policy;
        }

        @$date = $_GET['date'];
        if ($date != null) {
            $date = htmlspecialchars($date);
        }

        $xml = begin_XML_for_XSLT();
        $xml .= get_cdash_dashboard_xml_by_name(get_project_name($projectid), $date);
        $xml .= add_XML_value('title', 'CDash - Uploaded files');
        $xml .= add_XML_value('menutitle', 'CDash');
        $xml .= add_XML_value('menusubtitle', 'Uploaded files');

        $xml .= '<hostname>' . $_SERVER['SERVER_NAME'] . '</hostname>';
        $xml .= '<date>' . date('r') . '</date>';
        $xml .= '<backurl>index.php</backurl>';

        $xml .= "<buildid>$buildid</buildid>";
        $xml .= '<buildname>' . $Build->Name . '</buildname>';
        $xml .= '<buildstarttime>' . $Build->StartTime . '</buildstarttime>';
        $xml .= '<siteid>' . $Site->Id . '</siteid>';
        $xml .= '<sitename>' . $Site->GetName() . '</sitename>';

        $uploadFilesOrURLs = $Build->GetUploadedFilesOrUrls();

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
}
