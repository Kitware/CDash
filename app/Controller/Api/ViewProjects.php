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

/**
 * API controller for viewProjects.php.
 **/
class ViewProjects extends \CDash\Controller\Api
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
        $this->config = Config::getInstance();
    }

    public function getResponse()
    {
        $start = microtime_float();
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

        $response['title'] = $this->config->get('CDASH_MAININDEX_TITLE');
        $response['subtitle'] = $this->config->get('CDASH_MAININDEX_SUBTITLE');
        $response['googletracker'] = $this->config->get('CDASH_DEFAULT_GOOGLE_ANALYTICS');
        if ($this->config->get('CDASH_NO_REGISTRATION') == 1) {
            $response['noregister'] = 1;
        }

        if (isset($_GET['allprojects']) && $_GET['allprojects'] == 1) {
            $response['allprojects'] = 1;
        } else {
            $response['allprojects'] = 0;
        }
        $showallprojects = $response['allprojects'];
        $response['nprojects'] = get_number_public_projects();

        $projects = get_projects(!$showallprojects);
        $projects_response = array();
        foreach ($projects as $project) {
            $project_response = array();
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

        $end = microtime_float();
        $generation_time = round($end - $start, 2);
        $response['generationtime'] = $generation_time;
        return $response;
    }
}
