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

/**
 * View errors of a particular build, does not support parent builds.
 *
 * GET /viewBuildError.php
 * Required Params:
 * buildid=[integer] The ID of the build
 *
 * Optional Params:
 * type=[integer] (default 0) The type of build errors to view, 0 for errors, 1 for warnings
 * date=[YYYY-mm-dd]
 * onlydeltan=[anything] Only show errors that were resolved by this build (not supported for parent builds)
 * onlydeltap=[anything] Only show new errors that arose from this build
 **/

$noforcelogin = 1;
include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
include_once 'include/common.php';
include 'public/login.php';
include_once 'include/repository.php';
include 'include/version.php';
require_once 'models/build.php';

@$buildid = $_GET['buildid'];
if ($buildid != null) {
    $buildid = pdo_real_escape_numeric($buildid);
}

@$date = $_GET['date'];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

$response = begin_JSON_response();
$response['title'] = 'CDash : View Build Errors';

// Checks
if (!isset($buildid) || !is_numeric($buildid)) {
    $response['error'] = 'Not a valid buildid!';
    echo json_encode($response); // @todo should return a 400
    return;
}

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

$start = microtime_float();

$build_query = "SELECT build.id, build.projectid, build.siteid, build.type,
                       build.name, build.starttime, buildupdate.revision
                FROM build
                LEFT JOIN build2update ON (build2update.buildid = build.id)
                LEFT JOIN buildupdate ON (buildupdate.id = build2update.updateid)
                WHERE build.id = '$buildid'";
$build_array = pdo_fetch_array(pdo_query($build_query));

if (empty($build_array)) {
    $response['error'] = 'This build does not exist. Maybe it has been deleted.';
    echo json_encode($response);
    return;
}

$projectid = $build_array['projectid'];
$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if (pdo_num_rows($project) > 0) {
    $project_array = pdo_fetch_array($project);
    $projectname = $project_array['name'];
}

if (!checkUserPolicy(@$_SESSION['cdash']['loginid'], $project_array['id'], 1)) {
    $response['requirelogin'] = '1';
    echo json_encode($response);
    return;
}

$response['title'] = "CDash : $projectname";
$siteid = $build_array['siteid'];
$buildtype = $build_array['type'];
$buildname = $build_array['name'];
$starttime = $build_array['starttime'];
$revision = $build_array['revision'];

if (isset($_GET['type'])) {
    $type = pdo_real_escape_numeric($_GET['type']);
} else {
    $type = 0;
}

$date = get_dashboard_date_from_build_starttime($build_array['starttime'], $project_array['nightlytime']);
get_dashboard_JSON_by_name($projectname, $date, $response);

$menu = array();
$menu['back'] = 'index.php?project=' . urlencode($projectname) . '&date=' . $date;

$build = new Build();
$build->Id = $buildid;
$previous_buildid = $build->GetPreviousBuildId();
$current_buildid = $build->GetCurrentBuildId();
$next_buildid = $build->GetNextBuildId();

if ($previous_buildid > 0) {
    $menu['previous'] = "viewBuildError.php?type=$type&buildid=$previous_buildid";
} else {
    $menu['noprevious'] = 1;
}

$menu['current'] = "viewBuildError.php?type=$type&buildid=$current_buildid";

if ($next_buildid > 0) {
    $menu['next'] = "viewBuildError.php?type=$type&buildid=$next_buildid";
} else {
    $menu['nonext'] = 1;
}

$response['menu'] = $menu;

// Build
$build_response = array();
$site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
$build_response['site'] = $site_array['name'];
$build_response['siteid'] = $siteid;
$build_response['buildname'] = $build_array['name'];
$build_response['starttime'] =
    date(FMT_DATETIMETZ, strtotime($build_array['starttime'] . 'UTC'));
$build_response['buildid'] = $build_array['id'];
$response['build'] = $build_response;

// Set the error
if ($type == 0) {
    $response['errortypename'] = 'Error';
    $response['nonerrortypename'] = 'Warning';
    $response['nonerrortype'] = 1;
} else {
    $response['errortypename'] = 'Warning';
    $response['nonerrortypename'] = 'Error';
    $response['nonerrortype'] = 0;
}

$response['parentBuild'] = $build->IsParentBuild();
$response['errors'] = array();
$response['numErrors'] = 0;

/**
 * Add a new (marshaled) error to the response.
 * Keeps track of the id necessary for frontend JS, and updates
 * the numErrors response key.
 * @todo id should probably just be a unique id for the builderror?
 * builderror table currently has no integer that serves as a unique identifier.
 **/
function addErrorResponse($data)
{
    global $build, $response;

    $data['id'] = $response['numErrors'];
    $response['numErrors']++;

    $response['errors'][] = $data;
}

if (isset($_GET['onlydeltan'])) {
    // Build error table
    $resolvedBuildErrors = $build->GetResolvedBuildErrors($type);
    if ($resolvedBuildErrors !== false) {
        while ($resolvedBuildError = pdo_fetch_array($resolvedBuildErrors)) {
            addErrorResponse(builderror::marshal($resolvedBuildError, $project_array, $revision));
        }
    }

    // Build failure table
    $resolvedBuildFailures = $build->GetResolvedBuildFailures($type);
    while ($resolvedBuildFailure = pdo_fetch_array($resolvedBuildFailures)) {
        $marshaledResolvedBuildFailure = buildfailure::marshal($resolvedBuildFailure, $project_array, $revision);

        if ($project_array['displaylabels']) {
            get_labels_JSON_from_query_results(
                    "SELECT text FROM label, label2buildfailure
                    WHERE label.id=label2buildfailure.labelid AND
                    label2buildfailure.buildfailureid='" . $resolvedBuildFailure['id']  . "'
                    ORDER BY text ASC", $marshaledResolvedBuildFailure);
        }

        $marshaledResolvedBuildFailure = array_merge($marshaledResolvedBuildFailure, array(
            'stderr' => $resolvedBuildFailure['stderror'],
            'stderrorrows' => min(10, substr_count($resolvedBuildFailure['stderror'], "\n") + 1),
            'stdoutput' => $resolvedBuildFailure['stdoutput'],
            'stdoutputrows' => min(10, substr_count($resolvedBuildFailure['stdoutputrows'], "\n") + 1),
        ));

        addErrorResponse($marshaledResolvedBuildFailure);
    }
} else {
    $extrasql = '';
    if (isset($_GET['onlydeltap'])) {
        $extrasql = " AND newstatus='1'";
    }

    // Build error table
    $buildErrors = $build->GetBuildErrors($type, $extrasql);
    while ($buildError = pdo_fetch_array($buildErrors)) {
        addErrorResponse(builderror::marshal($buildError, $project_array, $revision));
    }

    // Build failure table
    $buildFailures = $build->getBuildFailures($projectid, $type, $extrasql, 'bf.id ASC');
    while ($buildFailure = pdo_fetch_array($buildFailures)) {
        $marshaledBuildFailure = buildfailure::marshal($buildFailure, $project_array, $revision, true);

        if ($project_array['displaylabels']) {
            get_labels_JSON_from_query_results(
                    "SELECT text FROM label, label2buildfailure
                    WHERE label.id=label2buildfailure.labelid AND
                    label2buildfailure.buildfailureid='" . $buildFailure['id']  . "'
                    ORDER BY text ASC", $marshaledBuildFailure);
        }

        addErrorResponse($marshaledBuildFailure);
    }
}

if ($build->IsParentBuild()) {
    $response['numSubprojects'] = count(array_unique(array_map(function ($buildError) {
        return $buildError['subprojectid'];
    }, $response['errors'])));
}

$end = microtime_float();
$response['generationtime'] = round($end - $start, 3);

echo json_encode(cast_data_for_JSON($response));
