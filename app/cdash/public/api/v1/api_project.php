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

include_once 'api.php';

use CDash\Config;
use CDash\Model\Project;

class ProjectAPI extends CDashAPI
{
    private $config;
    public function __construct()
    {
        $this->config = Config::getInstance();
    }

    /** Return the list of all public projects */
    private function ListProjects()
    {
        include_once 'include/common.php';
        $query = pdo_query('SELECT id,name FROM project WHERE public=1 ORDER BY name ASC');
        while ($query_array = pdo_fetch_array($query)) {
            $project['id'] = $query_array['id'];
            $project['name'] = $query_array['name'];
            $projects[] = $project;
        }
        return $projects;
    }

    /**
     * Authenticate to the web API as a project admin
     * @param project the name of the project
     * @param key the web API key for that project
     */
    public function Authenticate()
    {
        include_once 'include/common.php';
        if (!isset($this->Parameters['project'])) {
            return array('status' => false, 'message' => 'You must specify a project parameter.');
        }
        $projectid = get_project_id($this->Parameters['project']);
        if (!is_numeric($projectid) || $projectid <= 0) {
            return array('status' => false, 'message' => 'Project not found.');
        }
        if (!isset($this->Parameters['key']) || $this->Parameters['key'] == '') {
            return array('status' => false, 'message' => 'You must specify a key parameter.');
        }

        $key = $this->Parameters['key'];
        $query = pdo_query("SELECT webapikey FROM project WHERE id=$projectid");
        if (pdo_num_rows($query) == 0) {
            return array('status' => false, 'message' => 'Invalid projectid.');
        }
        $row = pdo_fetch_array($query);
        $realKey = $row['webapikey'];

        if ($key != $realKey) {
            return array('status' => false, 'message' => 'Incorrect API key passed.');
        }
        $token = create_web_api_token($projectid);
        return array('status' => true, 'token' => $token);
    }

    /**
     * List all files for a given project
     * @param project the name of the project
     * @param key the web API key for that project
     * @param [match] regular expression that files must match
     * @param [mostrecent] include this if you only want the most recent match
     */
    public function ListFiles()
    {
        include_once 'include/common.php';

        if (!isset($this->Parameters['project'])) {
            return array('status' => false, 'message' => 'You must specify a project parameter.');
        }
        $projectid = get_project_id($this->Parameters['project']);
        if (!is_numeric($projectid) || $projectid <= 0) {
            return array('status' => false, 'message' => 'Project not found.');
        }

        $Project = new Project();
        $Project->Id = $projectid;
        $files = $Project->GetUploadedFilesOrUrls();

        if (!$files) {
            return array('status' => false, 'message' => 'Error in Project::GetUploadedFilesOrUrls');
        }
        $filteredList = array();
        foreach ($files as $file) {
            if ($file['isurl']) {
                continue; // skip if filename is a URL
            }
            if (isset($this->Parameters['match']) && !preg_match('/' . $this->Parameters['match'] . '/', $file['filename'])) {
                continue; //skip if it doesn't match regex
            }
            $filteredList[] = array_merge($file, array('url' => $this->config->get('CDASH_DOWNLOAD_RELATIVE_URL') . '/' . $file['sha1sum'] . '/' . $file['filename']));

            if (isset($this->Parameters['mostrecent'])) {
                break; //user requested only the most recent file
            }
        }
        return array('status' => true, 'files' => $filteredList);
    }

    /** Run function */
    public function Run()
    {
        switch ($this->Parameters['task']) {
            case 'list':
                return $this->ListProjects();
            case 'login':
                return $this->Authenticate();
            case 'files':
                return $this->ListFiles();
        }
    }
}
