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

use CDash\Config;
use CDash\Database;
use CDash\Model\Banner;
use CDash\Model\Project;

/**
 * API controller for viewProjects.php.
 **/
class ViewProjects extends \CDash\Controller\Api
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
        $this->config = Config::getInstance();
        $this->showAllProjects = false;
        $this->activeProjectDays = 7;
        $this->user = null;
        if (\Auth::check()) {
            $this->user = \Auth::user();
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

        $response['title'] = 'CDash';
        $response['subtitle'] = 'Projects';
        $response['googletracker'] = config('cdash.default_google_analytics');
        if ($this->config->get('CDASH_NO_REGISTRATION') == 1) {
            $response['noregister'] = 1;
        }

        $response['showoldtoggle'] = true;
        $this->activeProjectDays = config('cdash.active_project_days');
        if ($this->activeProjectDays == 0) {
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
            if ($project['numsubprojects'] == 0) {
                $project_response['link'] = "index.php?project=$name_encoded";
            } else {
                $project_response['link'] = "viewSubProjects.php?project=$name_encoded";
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

        $this->pageTimer->end($response);
        return $response;
    }

    /**
     * Populate $this->projectids as an array of IDs for projects
     * that are visible to the user.
     **/
    public function getVisibleProjects()
    {
        $this->projectids = [];
        if (is_null($this->user)) {
            $this->projectids = \DB::table('project')
                ->where('public', Project::ACCESS_PUBLIC)
                ->pluck('id');
        } elseif ($this->user->admin) {
            $this->projectids = \DB::table('project')
                ->pluck('id');
        } else {
            $this->projectids = \DB::table('project')
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
        $project_rows = \DB::table('project')
            ->whereIn('id', $this->projectids)
            ->orderBy('name')
            ->get();
        foreach ($project_rows as $project_row) {
            $project = [];
            $project['id'] = $project_row->id;
            $project['name'] = $project_row->name;
            $project['description'] = $project_row->description;
            $projectid = $project['id'];

            $nsubproj = \DB::table('subproject')
                ->where('projectid', $projectid)
                ->where('endtime', '1980-01-01 00:00:00')
                ->count();
            $project['numsubprojects'] = $nsubproj;

            $project['last_build'] = 'NA';
            $latest_build_row = \DB::table('build')
                ->where('projectid', $projectid)
                ->orderBy('submittime', 'desc')
                ->first();
            if ($latest_build_row) {
                $project['last_build'] = $latest_build_row->submittime;
            }

            // Display if the project is considered active or not
            $dayssincelastsubmission = $this->activeProjectDays + 1;
            if ($project['last_build'] != 'NA') {
                $dayssincelastsubmission = (time() - strtotime($project['last_build'])) / 86400;
            }
            $project['dayssincelastsubmission'] = $dayssincelastsubmission;

            if ($project['last_build'] != 'NA' && $project['dayssincelastsubmission'] <= $this->activeProjectDays) {
                // Get the number of builds in the past 7 days
                $submittime_UTCDate = gmdate(FMT_DATETIME, time() - 604800);
                $project['nbuilds'] = \DB::table('build')
                    ->where('projectid', $projectid)
                    ->where('starttime', '>', $submittime_UTCDate)
                    ->count();
            }

            if ($this->showAllProjects || $project['dayssincelastsubmission'] <= $this->activeProjectDays) {
                $projects[] = $project;
            }
        }
        return $projects;
    }
}
