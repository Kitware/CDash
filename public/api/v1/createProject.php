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

use CDash\ServiceContainer;

include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
$noforcelogin = 1;
include 'public/login.php';
require_once 'include/common.php';
require_once 'include/pdo.php';
require_once 'include/version.php';

use CDash\Model\Project;
use CDash\Model\User;

$start = microtime_float();
$service = ServiceContainer::getInstance();

$response = array();
if (!$session_OK) {
    $response['requirelogin'] = 1;
    echo json_encode($response);
    return;
}

if (!isset($_SESSION['cdash']) || !isset($_SESSION['cdash']['loginid'])) {
    $response['requirelogin'] = 1;
    echo json_encode($response);
    return;
}

$userid = $_SESSION['cdash']['loginid'];
if (!isset($userid) || !is_numeric($userid)) {
    $response['requirelogin'] = 1;
    echo json_encode($response);
    return;
}

$Project = $service->create(Project::class);
$projectid = null;
if (isset($_GET['projectid'])) {
    $projectid = pdo_real_escape_numeric($_GET['projectid']);

    // Make sure projectid is valid if one was specified.
    $Project->Id = $projectid;
    if (!$Project->Exists()) {
        $response['error'] = 'This project does not exist.';
        echo json_encode($response);
        return;
    }
}

$User = $service->create(User::class);

$User->Id = $userid;
$role = $Project->GetUserRole($userid);

// Check if the user has the necessary permissions.
$userHasAccess = false;
if (!is_null($projectid)) {
    // Can they edit this project?
    if ($User->IsAdmin() || $role > 1) {
        $userHasAccess = true;
    }
} else {
    // Can they create a new project?
    if ($User->IsAdmin() ||
        (isset($_SESSION['cdash']['user_can_create_project']) &&
         $_SESSION['cdash']['user_can_create_project'] == 1)) {
        $userHasAccess = true;
    }
}
if (!$userHasAccess) {
    $response['error'] = 'You do not have permission to access this page.';
    echo json_encode($response);
    return;
}

$response = begin_JSON_response();
if ($projectid > 0) {
    get_dashboard_JSON($Project->GetName(), null, $response);
}
$response['hidenav'] = 1;
$menu = array();
$menu['back'] = 'user.php';
$response['menu'] = $menu;
$response['manageclient'] =  $CDASH_MANAGE_CLIENTS;

$nRepositories = 0;
$repositories_response = array();

if (!is_null($projectid)) {
    $response['title'] = 'CDash - Edit Project';
    $response['edit'] = 1;
} else {
    $response['title'] = 'CDash - New Project';
    $response['edit'] = 0;
    $response['noproject'] = 1;
}

// List the available projects
$sql = 'SELECT id,name FROM project';
if (!$User->IsAdmin()) {
    $sql .= " WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid='$userid' AND role>0)";
}
$sql .= ' ORDER by name ASC';
$projects = pdo_query($sql);
$available_projects = array();
while ($projects_array = pdo_fetch_array($projects)) {
    $available_project = array();
    $available_project['id'] = $projects_array['id'];
    $available_project['name'] = $projects_array['name'];
    if ($projects_array['id'] == $projectid) {
        $available_project['selected'] = 1;
    }
    $available_projects[] = $available_project;
}
$response['availableprojects'] = $available_projects;

$project_response = array();
if ($projectid > 0) {
    $Project->Fill();
    $project_response = $Project->ConvertToJSON();

    // Get the spam list
    $spambuilds = $Project->GetBlockedBuilds();
    $blocked_builds = array();
    foreach ($spambuilds as $spambuild) {
        $blocked_builds[] = $spambuild;
    }
    $project_response['blockedbuilds'] = $blocked_builds;

    $repositories = $Project->GetRepositories();
    foreach ($repositories as $repository) {
        $repository_response = array();
        $repository_response['url'] = $repository['url'];
        $repository_response['username'] = $repository['username'];
        $repository_response['password'] = $repository['password'];
        $repository_response['branch'] = $repository['branch'];
        $repositories_response[] = $repository_response;
        $nRepositories++;
    }
} else {
    // Initialize some variables for project creation.
    global $CDASH_DEFAULT_AUTHENTICATE_SUBMISSIONS;
    $project_response['AuthenticateSubmissions'] = $CDASH_DEFAULT_AUTHENTICATE_SUBMISSIONS;
    $project_response['AutoremoveMaxBuilds'] = 500;
    $project_response['AutoremoveTimeframe'] = 60;
    $project_response['CoverageThreshold'] = 70;
    $project_response['EmailBrokenSubmission'] = 1;
    $project_response['EmailMaxChars'] = 255;
    $project_response['EmailMaxItems'] = 5;
    $project_response['NightlyTime'] = '01:00:00 UTC';
    $project_response['ShowCoverageCode'] = 1;
    $project_response['TestTimeMaxStatus'] = 3;
    $project_response['TestTimeStd'] = 4.0;
    $project_response['TestTimeStdThreshold'] = 1.0;
    $project_response['UploadQuota'] = 1;
}

// Make sure we have at least one repository.
if ($nRepositories == 0) {
    $repository_response = array();
    $repository_response['id'] = $nRepositories;
    $repository_response['url'] = '';
    $repository_response['branch'] = '';
    $repository_response['username'] = '';
    $repository_response['password'] = '';
    $repositories_response[] = $repository_response;
}
$project_response['repositories'] = $repositories_response;
$response['project'] = $project_response;

// Add the different types of Version Control System (VCS) viewers.
if (strlen($Project->CvsViewerType) == 0) {
    $Project->CvsViewerType = 'github';
}

function AddVCSViewer($name, $description, $currentViewer, &$response)
{
    $viewer = array();
    $viewer['value'] = $name;
    $viewer['description'] = $description;
    if ($name == $currentViewer) {
        $response['selectedViewer'] = $viewer;
    }
    return $viewer;
}

// Put the repository viewers in alphabetical order.
$viewers = array();
$viewers[] = AddVCSViewer('cgit', 'CGit', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('cvstrac', 'CVSTrac', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('fisheye', 'Fisheye', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('github', 'GitHub', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('gitlab', 'GitLab', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('gitorious', 'Gitorious', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('gitweb', 'GitWeb', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('gitweb2', 'GitWeb2', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('hgweb', 'Hgweb', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('phab_git', 'Phabricator (Git)', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('stash', 'Atlassian Stash', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('loggerhead', 'Loggerhead', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('p4web', 'P4Web', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('redmine', 'Redmine', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('allura', 'SourceForge Allura', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('trac', 'Trac', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('viewcvs', 'ViewCVS', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('viewvc', 'ViewVC', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('viewvc_1_1', 'ViewVC1.1', $Project->CvsViewerType, $response);
$viewers[] = AddVCSViewer('websvn', 'WebSVN', $Project->CvsViewerType, $response);
$response['vcsviewers'] = $viewers;

$end = microtime_float();
$response['generationtime'] = round($end - $start, 3);
echo json_encode(cast_data_for_JSON($response));
