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

namespace CDash\Api\v1\ViewSubProjects;

require_once 'include/api_common.php';
require_once 'include/common.php';

use App\Services\PageTimer;

use App\Models\Banner;
use CDash\Model\Project;
use CDash\Model\SubProject;

@set_time_limit(0);

@$projectname = $_GET['project'];
$projectname = htmlspecialchars(pdo_real_escape_string($projectname));
$projectid = get_project_id($projectname);
$Project = new Project();
$Project->Id = $projectid;
$Project->Fill();

if (isset($_GET['date'])) {
    $date = htmlspecialchars(pdo_real_escape_string($_GET['date']));
    $date_specified = true;
} else {
    $last_start_timestamp = $Project->GetLastSubmission();
    $date = strlen($last_start_timestamp) > 0 ? $last_start_timestamp : null;
    $date_specified = false;
}

// Gather up the data for a SubProjects dashboard.

$pageTimer = new PageTimer();

$projectid = $Project->Id;

if (!can_access_project($projectid)) {
    return;
}

$response = begin_JSON_response();

$response['title'] = $Project->Name;
$response['showcalendar'] = 1;

$banners = array();
$global_banner = Banner::find(0);
if ($global_banner !== null && strlen($global_banner->text) > 0) {
    $banners[] = $global_banner->text;
}
$project_banner = Banner::find($projectid);
if ($project_banner !== null && strlen($project_banner->text) > 0) {
    $banners[] = $project_banner->text;
}
$response['banners'] = $banners;

if (config('cdash.show_last_submission')) {
    $response['showlastsubmission'] = 1;
}

list($previousdate, $currentstarttime, $nextdate) = get_dates($date, $Project->NightlyTime);

// Main dashboard section
get_dashboard_JSON($Project->GetName(), $date, $response);
$projectname_encoded = urlencode($Project->Name);
if ($currentstarttime > time()) {
    $response['error'] = 'CDash cannot predict the future (yet)';
    echo json_encode($response);
    return;
}

$linkparams = 'project=' . urlencode($Project->Name);
if (!empty($date)) {
    $linkparams .= "&date=$date";
}
$response['linkparams'] = $linkparams;

// Menu definition
$menu_response = array();
$menu_response['subprojects'] = 1;
$menu_response['previous'] = "viewSubProjects.php?project=$projectname_encoded&date=$previousdate";
$menu_response['current'] = "viewSubProjects.php?project=$projectname_encoded";
if (!has_next_date($date, $currentstarttime)) {
    $menu_response['nonext'] = 1;
} else {
    $menu_response['next'] = "viewSubProjects.php?project=$projectname_encoded&date=$nextdate";
}
$response['menu'] = $menu_response;

$beginning_UTCDate = gmdate(FMT_DATETIME, $currentstarttime);
$end_UTCDate = gmdate(FMT_DATETIME, $currentstarttime + 3600 * 24);

// Get some information about the project
$project_response = array();
$project_response['nbuilderror'] = $Project->GetNumberOfErrorBuilds($beginning_UTCDate, $end_UTCDate);
$project_response['nbuildwarning'] = $Project->GetNumberOfWarningBuilds($beginning_UTCDate, $end_UTCDate);
$project_response['nbuildpass'] = $Project->GetNumberOfPassingBuilds($beginning_UTCDate, $end_UTCDate);
$project_response['nconfigureerror'] = $Project->GetNumberOfErrorConfigures($beginning_UTCDate, $end_UTCDate);
$project_response['nconfigurewarning'] = $Project->GetNumberOfWarningConfigures($beginning_UTCDate, $end_UTCDate);
$project_response['nconfigurepass'] = $Project->GetNumberOfPassingConfigures($beginning_UTCDate, $end_UTCDate);
$project_response['ntestpass'] = $Project->GetNumberOfPassingTests($beginning_UTCDate, $end_UTCDate);
$project_response['ntestfail'] = $Project->GetNumberOfFailingTests($beginning_UTCDate, $end_UTCDate);
$project_response['ntestnotrun'] = $Project->GetNumberOfNotRunTests($beginning_UTCDate, $end_UTCDate);
$project_last_submission = $Project->GetLastSubmission();
if (strlen($project_last_submission) == 0) {
    $project_response['starttime'] = 'NA';
} else {
    $project_response['starttime'] = $project_last_submission;
}
$response['project'] = $project_response;

// Look for the subproject
$subprojectids = $Project->GetSubProjects();
$subprojProp = array();
foreach ($subprojectids as $subprojectid) {
    $SubProject = new SubProject();
    $SubProject->SetId($subprojectid);
    $subprojProp[$subprojectid] = array('name' => $SubProject->GetName());
}

// If all of the dates are the same, we can get the results in bulk.  Otherwise, we must query every
// subproject separately.
if ($date_specified) {
    $testSubProj = new SubProject();
    $testSubProj->SetProjectId($projectid);
    $result = $testSubProj->CommonBuildQuery($beginning_UTCDate, $end_UTCDate, true);
    if ($result !== false) {
        foreach ($result as $row) {
            $subprojProp[$row['subprojectid']]['nbuilderror'] = (int) $row['nbuilderrors'];
            $subprojProp[$row['subprojectid']]['nbuildwarning'] = (int) $row['nbuildwarnings'];
            $subprojProp[$row['subprojectid']]['nbuildpass'] = (int) $row['npassingbuilds'];
            $subprojProp[$row['subprojectid']]['nconfigureerror'] = (int) $row['nconfigureerrors'];
            $subprojProp[$row['subprojectid']]['nconfigurewarning'] = (int) $row['nconfigurewarnings'];
            $subprojProp[$row['subprojectid']]['nconfigurepass'] = (int) $row['npassingconfigures'];
            $subprojProp[$row['subprojectid']]['ntestpass'] = (int) $row['ntestspassed'];
            $subprojProp[$row['subprojectid']]['ntestfail'] = (int) $row['ntestsfailed'];
            $subprojProp[$row['subprojectid']]['ntestnotrun'] = (int) $row['ntestsnotrun'];
        }
    }
}

$reportArray = array('nbuilderror', 'nbuildwarning', 'nbuildpass',
    'nconfigureerror', 'nconfigurewarning', 'nconfigurepass',
    'ntestpass', 'ntestfail', 'ntestnotrun');
$subprojects_response = array();

foreach ($subprojectids as $subprojectid) {
    $SubProject = new SubProject();
    $SubProject->SetId($subprojectid);
    $subproject_response = array();
    $subproject_response['name'] = $SubProject->GetName();
    $subproject_response['name_encoded'] = urlencode($SubProject->GetName());

    $last_submission_start_timestamp = $SubProject->GetLastSubmission();
    if (!$date_specified) {
        $currentstarttime = get_dates($last_submission_start_timestamp, $Project->NightlyTime)[1];
        $beginning_UTCDate = gmdate(FMT_DATETIME, $currentstarttime);
        $end_UTCDate = gmdate(FMT_DATETIME, $currentstarttime + 3600 * 24);

        $result = $SubProject->CommonBuildQuery($beginning_UTCDate, $end_UTCDate, false);

        $subprojProp[$subprojectid]['nconfigureerror'] = (int) $result['nconfigureerrors'];
        $subprojProp[$subprojectid]['nconfigurewarning'] = (int) $result['nconfigurewarnings'];
        $subprojProp[$subprojectid]['nconfigurepass'] = (int) $result['npassingconfigures'];
        $subprojProp[$subprojectid]['nbuilderror'] = (int) $result['nbuilderrors'];
        $subprojProp[$subprojectid]['nbuildwarning'] = (int) $result['nbuildwarnings'];
        $subprojProp[$subprojectid]['nbuildpass'] = (int) $result['npassingbuilds'];
        $subprojProp[$subprojectid]['ntestnotrun'] = (int) $result['ntestsnotrun'];
        $subprojProp[$subprojectid]['ntestfail'] = (int) $result['ntestsfailed'];
        $subprojProp[$subprojectid]['ntestpass'] = (int) $result['ntestspassed'];
    }

    foreach ($reportArray as $reportnum) {
        $reportval = array_key_exists($reportnum, $subprojProp[$subprojectid]) ?
            $subprojProp[$subprojectid][$reportnum] : 0;
        $subproject_response[$reportnum] = $reportval;
    }

    if ($last_submission_start_timestamp === '' || $last_submission_start_timestamp === false) {
        $subproject_response['starttime'] = 'NA';
    } else {
        $subproject_response['starttime'] = $last_submission_start_timestamp;
    }
    $subprojects_response[] = $subproject_response;
}
$response['subprojects'] = $subprojects_response;

$pageTimer->end($response);
echo json_encode(cast_data_for_JSON($response));
