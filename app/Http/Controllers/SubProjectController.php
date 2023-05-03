<?php

namespace App\Http\Controllers;

use CDash\Database;
use CDash\Model\Project;
use CDash\Model\SubProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SubProjectController extends AbstractController
{
    public function dependencies(): View|RedirectResponse
    {
        @$projectname = $_GET['project'];
        if ($projectname != null) {
            $projectname = htmlspecialchars(pdo_real_escape_string($projectname));
        }

        @$date = $_GET['date'];
        if ($date != null) {
            $date = htmlspecialchars(pdo_real_escape_string($date));
        }

        $projectid = get_project_id($projectname);

        if ($projectid == 0) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => 'Not a valid projectid!'
            ]);
        }

        $db = Database::getInstance();
        $project_array = $db->executePreparedSingleRow('SELECT * FROM project WHERE id=?', [$projectid]);
        if (!empty($project_array)) {
            $svnurl = make_cdash_url(htmlentities($project_array['cvsurl']));
            $homeurl = make_cdash_url(htmlentities($project_array['homeurl']));
            $bugurl = make_cdash_url(htmlentities($project_array['bugtrackerurl']));
            $googletracker = htmlentities($project_array['googletracker']);
            $docurl = make_cdash_url(htmlentities($project_array['documentationurl']));
            $projectpublic = $project_array['public'];
            $projectname = $project_array['name'];
        } else {
            $projectname = 'NA';
        }

        $policy = checkUserPolicy($project_array['id']);
        if ($policy !== true) {
            return $policy;
        }

        $xml = begin_XML_for_XSLT();

        list($previousdate, $currentstarttime, $nextdate) = get_dates($date, $project_array['nightlytime']);

        // Main dashboard section
        $xml .=
             '<dashboard>
              <datetime>' . date('l, F d Y H:i:s T', time()) . '</datetime>
              <date>' . $date . '</date>
              <unixtimestamp>' . $currentstarttime . '</unixtimestamp>
              <svn>' . $svnurl . '</svn>
              <bugtracker>' . $bugurl . '</bugtracker>
              <googletracker>' . $googletracker . '</googletracker>
              <documentation>' . $docurl . '</documentation>
              <projectid>' . $projectid . '</projectid>
              <projectname>' . $projectname . '</projectname>
              <projectname_encoded>' . urlencode($projectname) . '</projectname_encoded>
              <previousdate>' . $previousdate . '</previousdate>
              <projectpublic>' . $projectpublic . '</projectpublic>
              <nextdate>' . $nextdate . '</nextdate>';

        if (empty($project_array['homeurl'])) {
            $xml .= '<home>index.php?project=' . urlencode($projectname) . '</home>';
        } else {
            $xml .= '<home>' . $homeurl . '</home>';
        }
        if ($currentstarttime > time()) {
            $xml .= '<future>1</future>';
        } else {
            $xml .= '<future>0</future>';
        }
        $xml .= '</dashboard>';

        // Menu definition
        $xml .= '<menu>';
        if (!isset($date) || strlen($date) < 8 || date(FMT_DATE, $currentstarttime) == date(FMT_DATE)) {
            $xml .= add_XML_value('nonext', '1');
        }
        $xml .= '</menu>';

        $Project = new Project();
        $Project->Id = $projectid;
        $Project->Fill();
        $subprojectids = $Project->GetSubProjects();

        sort($subprojectids);

        $row = 0;
        foreach ($subprojectids as $subprojectid) {
            $xml .= '<subproject>';
            $SubProject = new SubProject();
            $SubProject->SetId($subprojectid);

            if ($row == 0) {
                $xml .= add_XML_value('bgcolor', '#EEEEEE');
                $row = 1;
            } else {
                $xml .= add_XML_value('bgcolor', '#DDDDDD');
                $row = 0;
            }

            $xml .= add_XML_value('name', $SubProject->GetName());
            $xml .= add_XML_value('name_encoded', urlencode($SubProject->GetName()));

            $dependencies = $SubProject->GetDependencies($date);
            foreach ($subprojectids as $subprojectid2) {
                $xml .= '<dependency>';
                if (in_array($subprojectid2, $dependencies) || $subprojectid == $subprojectid2) {
                    $xml .= add_XML_value('id', $subprojectid);
                }
                $xml .= '</dependency>';
            }
            $xml .= '</subproject>';
        }
        $xml .= '</cdash>';

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/viewSubProjectDependencies', true),
            'project' => $Project,
            'title' => 'SubProject Dependencies'
        ]);
    }

    public function dependenciesGraph(): View|RedirectResponse
    {
        @$projectname = $_GET['project'];
        if ($projectname != null) {
            $projectname = htmlspecialchars(pdo_real_escape_string($projectname));
        }

        @$date = $_GET['date'];
        if ($date != null) {
            $date = htmlspecialchars(pdo_real_escape_string($date));
        }

        $projectid = get_project_id($projectname);

        if ($projectid === 0) {
            return view('cdash', [
                'xsl' => true,
                'xsl_content' => 'Not a valid projectid!'
            ]);
        }

        $db = Database::getInstance();
        $project_array = $db->executePreparedSingleRow('SELECT * FROM project WHERE id=?', [$projectid]);
        if (!empty($project_array)) {
            $svnurl = make_cdash_url(htmlentities($project_array['cvsurl']));
            $homeurl = make_cdash_url(htmlentities($project_array['homeurl']));
            $bugurl = make_cdash_url(htmlentities($project_array['bugtrackerurl']));
            $googletracker = htmlentities($project_array['googletracker']);
            $docurl = make_cdash_url(htmlentities($project_array['documentationurl']));
            $projectpublic = $project_array['public'];
            $projectname = $project_array['name'];
        } else {
            $projectname = 'NA';
        }

        $policy = checkUserPolicy($project_array['id']);
        if ($policy !== true) {
            return $policy;
        }

        $project = new Project();
        $project->Id = $projectid;
        $project->Fill();

        $xml = begin_XML_for_XSLT();

        list($previousdate, $currentstarttime, $nextdate) = get_dates($date, $project_array['nightlytime']);

        // Main dashboard section
        $xml .=
             '<dashboard>
              <datetime>' . date('l, F d Y H:i:s T', time()) . '</datetime>
              <date>' . $date . '</date>
              <unixtimestamp>' . $currentstarttime . '</unixtimestamp>
              <svn>' . $svnurl . '</svn>
              <bugtracker>' . $bugurl . '</bugtracker>
              <googletracker>' . $googletracker . '</googletracker>
              <documentation>' . $docurl . '</documentation>
              <projectid>' . $projectid . '</projectid>
              <projectname>' . $projectname . '</projectname>
              <projectname_encoded>' . urlencode($projectname) . '</projectname_encoded>
              <previousdate>' . $previousdate . '</previousdate>
              <projectpublic>' . $projectpublic . '</projectpublic>
              <nextdate>' . $nextdate . '</nextdate>';

        if (empty($project_array['homeurl'])) {
            $xml .= '<home>index.php?project=' . urlencode($projectname) . '</home>';
        } else {
            $xml .= '<home>' . $homeurl . '</home>';
        }

        if ($currentstarttime > time()) {
            $xml .= '<future>1</future>';
        } else {
            $xml .= '<future>0</future>';
        }
        $xml .= '</dashboard>';

        // Menu definition
        $xml .= '<menu>';
        if (!isset($date) || strlen($date) < 8 || date(FMT_DATE, $currentstarttime) == date(FMT_DATE)) {
            $xml .= add_XML_value('nonext', '1');
        }
        $xml .= '</menu>';

        $xml .= '</cdash>';

        return view('cdash', [
            'xsl' => true,
            'xsl_content' => generate_XSLT($xml, base_path() . '/app/cdash/public/viewSubProjectDependenciesGraph', true),
            'project' => $project,
            'title' => 'SubProject Dependencies Graph'
        ]);
    }
}
