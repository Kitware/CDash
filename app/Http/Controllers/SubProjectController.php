<?php

namespace App\Http\Controllers;

use CDash\Database;
use CDash\Model\Project;
use CDash\Model\SubProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SubProjectController extends AbstractProjectController
{
    public function dependencies(): View|RedirectResponse
    {
        if (!isset($_GET['project'])) {
            abort(400, 'You must specify a project to access this resource.');
        }
        $this->setProjectByName($_GET['project']);

        @$date = $_GET['date'];
        if ($date != null) {
            $date = htmlspecialchars(pdo_real_escape_string($date));
        }

        $svnurl = make_cdash_url(htmlentities($this->project->CvsUrl));
        $homeurl = make_cdash_url(htmlentities($this->project->HomeUrl));
        $bugurl = make_cdash_url(htmlentities($this->project->BugTrackerUrl));
        $googletracker = htmlentities($this->project->GoogleTracker);
        $docurl = make_cdash_url(htmlentities($this->project->DocumentationUrl));
        $xml = begin_XML_for_XSLT();

        list($previousdate, $currentstarttime, $nextdate) = get_dates($date, $this->project->NightlyTime);

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
              <projectid>' . $this->project->Id . '</projectid>
              <projectname>' . $this->project->Name . '</projectname>
              <projectname_encoded>' . urlencode($this->project->Name) . '</projectname_encoded>
              <previousdate>' . $previousdate . '</previousdate>
              <projectpublic>' . $this->project->Public . '</projectpublic>
              <nextdate>' . $nextdate . '</nextdate>';

        if (empty($this->project->HomeUrl)) {
            $xml .= '<home>index.php?project=' . urlencode($this->project->Name) . '</home>';
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

        $subprojectids = $this->project->GetSubProjects();

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
            'project' => $this->project,
            'title' => 'SubProject Dependencies'
        ]);
    }

    public function dependenciesGraph(): View|RedirectResponse
    {
        if (!isset($_GET['project'])) {
            abort(400, 'You must specify a project to access this resource.');
        }
        $this->setProjectByName($_GET['project']);

        @$date = $_GET['date'];
        if ($date != null) {
            $date = htmlspecialchars(pdo_real_escape_string($date));
        }

        $svnurl = make_cdash_url(htmlentities($this->project->CvsUrl));
        $homeurl = make_cdash_url(htmlentities($this->project->HomeUrl));
        $bugurl = make_cdash_url(htmlentities($this->project->BugTrackerUrl));
        $googletracker = htmlentities($this->project->GoogleTracker);
        $docurl = make_cdash_url(htmlentities($this->project->DocumentationUrl));

        $xml = begin_XML_for_XSLT();

        list($previousdate, $currentstarttime, $nextdate) = get_dates($date, $this->project->NightlyTime);

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
              <projectid>' . $this->project->Id . '</projectid>
              <projectname>' . $this->project->Name . '</projectname>
              <projectname_encoded>' . urlencode($this->project->Name) . '</projectname_encoded>
              <previousdate>' . $previousdate . '</previousdate>
              <projectpublic>' . $this->project->Public . '</projectpublic>
              <nextdate>' . $nextdate . '</nextdate>';

        if (empty($this->project->HomeUrl)) {
            $xml .= '<home>index.php?project=' . urlencode($this->project->Name) . '</home>';
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
            'project' => $this->project,
            'title' => 'SubProject Dependencies Graph'
        ]);
    }
}
