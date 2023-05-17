<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/
namespace CDash\Controller\Api;

use App\Models\User;
use CDash\Config;
use CDash\Database;
use CDash\Model\Banner;
use CDash\Model\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * TODO: (williamjallen) move all of this logic over to app/Http/Controllers/ViewProjectsController.php
 *
 * API controller for viewProjects.php.
 **/
class ViewProjects extends \CDash\Controller\Api
{
    private $projectids = [];
    private int $activeProjectDays = 7;
    private bool $showAllProjects = false;
    private ?User $user = null;

    public function __construct(Database $db)
    {
        parent::__construct($db);
        if (Auth::check()) {
            $this->user = Auth::user();
        }
    }

    public function getResponse()
    {
        $response = begin_JSON_response();

        $Banner = new Banner;
        $Banner->SetProjectId(0);
        $text = $Banner->GetText();
        if ($text !== false) {
            $response['banner'] = $text;
        }

        $response['hostname'] = $_SERVER['SERVER_NAME'];
        $response['date'] = date('r');

        // Check if the database is up to date.
        if ($this->db->query('SELECT authenticatesubmissions FROM project LIMIT 1') === false) {
            $response['upgradewarning'] = 1;
        }

        $response['title'] = 'Projects';
        $response['subtitle'] = 'Projects';
        $response['googletracker'] = config('cdash.default_google_analytics');
        if (Config::getInstance()->get('CDASH_NO_REGISTRATION') == 1) {
            $response['noregister'] = 1;
        }

        $response['showoldtoggle'] = true;
        $this->activeProjectDays = (int) config('cdash.active_project_days');
        if ($this->activeProjectDays === 0) {
            $this->showAllProjects = true;
            $response['showoldtoggle'] = false;
        } elseif (isset($_GET['allprojects']) && $_GET['allprojects'] == 1) {
            $this->showAllProjects = true;
        }

        $this->getVisibleProjects();
        $response['nprojects'] = count($this->projectids);

        $projects = $this->getProjects();
        if (count($projects) === 0) {
            // Show all projects if none were found with any recent activity.
            $this->showAllProjects = true;
            $projects = $this->getProjects();
            $response['showoldtoggle'] = false;
        }
        $response['allprojects'] = $this->showAllProjects;

        $projects_response = [];
        foreach ($projects as $project) {
            $project_response = [];
            $project_response['name'] = $project['name'];
            $name_encoded = urlencode($project['name']);
            $project_response['name_encoded'] = $name_encoded;
            $project_response['description'] = $project['description'];
            if ($project['numsubprojects'] > 0 && $project['viewsubprojectslink']) {
                $project_response['link'] = "viewSubProjects.php?project=$name_encoded";
            } else {
                $project_response['link'] = "index.php?project=$name_encoded";
            }

            if ($project['last_build'] == 'NA') {
                $project_response['lastbuild'] = 'NA';
            } else {
                $lastbuild = strtotime($project['last_build'] . 'UTC');
                $project_response['lastbuild'] = date(FMT_DATETIMEDISPLAY, $lastbuild);
                $project_response['lastbuilddate'] = date(FMT_DATE, $lastbuild);
                $project_response['lastbuild_elapsed'] =
                    time_difference(time() - $lastbuild, false, 'ago');
                $project_response['lastbuilddatefull'] = $lastbuild;
            }

            if (!isset($project['nbuilds']) || $project['nbuilds'] == 0) {
                $project_response['activity'] = 'none';
            } elseif ($project['nbuilds'] < 20) {
                // 2 builds day

                $project_response['activity'] = 'low';
            } elseif ($project['nbuilds'] < 70) {
                // 10 builds a day

                $project_response['activity'] = 'medium';
            } elseif ($project['nbuilds'] >= 70) {
                $project_response['activity'] = 'high';
            }

            $projects_response[] = $project_response;
        }
        $response['projects'] = $projects_response;

        $response['enable_registration'] = config("auth.user_registration_form_enabled");
        $this->pageTimer->end($response);
        return $response;
    }

    /**
     * Populate $this->projectids as an array of IDs for projects
     * that are visible to the user.
     **/
    public function getVisibleProjects()
    {
        if (is_null($this->user)) {
            $this->projectids = DB::table('project')
                ->where('public', Project::ACCESS_PUBLIC)
                ->pluck('id');
        } elseif ($this->user->IsAdmin()) {
            $this->projectids = DB::table('project')
                ->pluck('id');
        } else {
            $this->projectids = DB::table('project')
                ->leftJoin('user2project', 'project.id', '=', 'user2project.projectid')
                ->where('user2project.userid', $this->user->id)
                ->orWhere('project.public', Project::ACCESS_PUBLIC)
                ->orWhere('project.public', Project::ACCESS_PROTECTED)
                ->distinct()
                ->pluck('id');
        }
    }

    /** return an array of public projects */
    public function getProjects()
    {
        $projects = [];
        $project_rows = DB::table('project')
            ->whereIn('id', $this->projectids)
            ->orderBy('name')
            ->get();

        $query_result = DB::table('build')
            ->select('projectid', DB::raw('MAX(submittime) as submittime'))
            ->whereIn('projectid', $this->projectids)
            ->groupBy('projectid')
            ->get();
        // Transform the result into something we can use more effectively
        $latest_build_rows = [];
        foreach ($query_result as $row) {
            $latest_build_rows[(int)$row->projectid] = $row->submittime;
        }

        $query_result = DB::table('subproject')
            ->select('projectid', DB::raw('COUNT(*) as c'))
            ->whereIn('projectid', $this->projectids)
            ->where('endtime', '1980-01-01 00:00:00')
            ->groupBy('projectid')
            ->get();
        // Transform the result into something we can use more effectively
        $nsubproj = [];
        foreach ($query_result as $row) {
            $nsubproj[(int)$row->projectid] = $row->c;
        }

        // Get the number of builds in the past 7 days by project
        $submittime_UTCDate = gmdate(FMT_DATETIME, time() - 604800);
        $query_result = DB::table('build')
            ->select('projectid', DB::raw('COUNT(*) as c'))
            ->whereIn('projectid', $this->projectids)
            ->where('starttime', '>', $submittime_UTCDate)
            ->groupBy('projectid')
            ->get();
        $nbuilds = [];
        foreach ($query_result as $row) {
            $nbuilds[(int)$row->projectid] = $row->c;
        }

        foreach ($project_rows as $project_row) {
            $project = [];
            $project['id'] = (int) $project_row->id;
            $project['name'] = $project_row->name;
            $project['description'] = $project_row->description;
            $project['viewsubprojectslink'] = $project_row->viewsubprojectslink;
            $projectid = $project['id'];

            $project['numsubprojects'] = $nsubproj[$projectid] ?? 0;

            $project['last_build'] = 'NA';
            if (array_key_exists($projectid, $latest_build_rows)) {
                $project['last_build'] = $latest_build_rows[$projectid];
            }

            // Display if the project is considered active or not
            $dayssincelastsubmission = $this->activeProjectDays + 1;
            if ($project['last_build'] != 'NA') {
                $dayssincelastsubmission = (time() - strtotime($project['last_build'])) / 86400;
            }
            $project['dayssincelastsubmission'] = $dayssincelastsubmission;

            if ($project['last_build'] != 'NA' && $project['dayssincelastsubmission'] <= $this->activeProjectDays) {
                $project['nbuilds'] = $nbuilds[$projectid] ?? 0;
            }

            if ($this->showAllProjects || $project['dayssincelastsubmission'] <= $this->activeProjectDays) {
                $projects[] = $project;
            }
        }
        return $projects;
    }
}
