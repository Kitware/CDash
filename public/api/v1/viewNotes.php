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

include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
require_once 'include/api_common.php';
include 'include/version.php';
require_once 'models/build.php';

$start = microtime_float();

@$buildid = $_GET['buildid'];
if ($buildid != null) {
    $buildid = pdo_real_escape_numeric($buildid);
}
@$date = $_GET['date'];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

$response = begin_JSON_response();

// Checks
if (!isset($buildid) || !is_numeric($buildid)) {
    $response['error'] = 'Not a valid buildid!';
    echo json_encode($response);
    return;
}

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

$build_array = pdo_fetch_array(pdo_query("SELECT * FROM build WHERE id='$buildid'"));
$projectid = $build_array['projectid'];
if (!isset($projectid) || $projectid == 0) {
    $response['error'] = "This build doesn't exist. Maybe it has been deleted.";
    echo json_encode($response);
    return;
}

if (!can_access_project($projectid)) {
    return;
}

$siteid = $build_array['siteid'];
$buildtype = $build_array['type'];
$buildname = $build_array['name'];
$starttime = $build_array['starttime'];

$project_array = pdo_fetch_array(pdo_query("SELECT * FROM project WHERE id='$projectid'"));
$projectname = $project_array['name'];
$response['title'] = "CDash : $projectname";

$date = get_dashboard_date_from_build_starttime($build_array['starttime'], $project_array['nightlytime']);
get_dashboard_JSON_by_name($projectname, $date, $response);

// Menu
$menu = array();
$menu['back'] = 'index.php?project=' . urlencode($projectname) . '&date=' . $date;

$build = new Build();
$build->Id = $buildid;
$previous_buildid = $build->GetPreviousBuildId();
$current_buildid = $build->GetCurrentBuildId();
$next_buildid = $build->GetNextBuildId();

if ($previous_buildid > 0) {
    $menu['previous'] = "viewNotes.php?buildid=$previous_buildid";
} else {
    $menu['noprevious'] = '1';
}

$menu['current'] = "viewNotes.php?buildid=$current_buildid";

if ($next_buildid > 0) {
    $menu['next'] = "viewNotes.php?buildid=$next_buildid";
} else {
    $menu['nonext'] = '1';
}

$response['menu'] = $menu;

// Build
$build_response = array();
$site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
$build_response['site'] = $site_array['name'];
$build_response['siteid'] = $siteid;
$build_response['buildname'] = $build_array['name'];
$build_response['buildid'] = $build_array['id'];
$build_response['stamp'] = $build_array['stamp'];
$response['build'] = $build_response;

// Notes
$notes = array();
$build2note = pdo_query("SELECT noteid,time FROM build2note WHERE buildid='$buildid'");
while ($build2note_array = pdo_fetch_array($build2note)) {
    $noteid = $build2note_array['noteid'];
    $note_array = pdo_fetch_array(pdo_query("SELECT * FROM note WHERE id='$noteid'"));

    $note = array();
    $note['name'] = $note_array['name'];
    $note['text'] = $note_array['text'];
    $note['time'] = $build2note_array['time'];

    $notes[] = $note;
}
$response['notes'] = $notes;

$end = microtime_float();
$generation_time = round($end - $start, 2);
$response['generationtime'] = $generation_time;

echo json_encode(cast_data_for_JSON($response));
