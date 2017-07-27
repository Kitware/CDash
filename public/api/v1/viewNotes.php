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
$build = get_request_build();
$buildid = get_request_build_id();

@$date = $_GET['date'];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

$response = [];
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

$project_array = pdo_fetch_array(pdo_query("SELECT * FROM project WHERE id='{$build->ProjectId}'"));
$projectname = $project_array['name'];
$response = begin_JSON_response();
$response['title'] = "CDash : $projectname";

$date = get_dashboard_date_from_build_starttime($build->StartTime, $project_array['nightlytime']);
get_dashboard_JSON_by_name($projectname, $date, $response);

// Menu
$menu = array();
if ($build->GetParentId() > 0) {
    $menu['back'] = 'index.php?project=' . urlencode($projectname) . "&parentid={$build->GetParentId()}";
} else {
    $menu['back'] = 'index.php?project=' . urlencode($projectname) . '&date=' . $date;
}

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
$site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='{$build->SiteId}'"));
$response['build'] = Build::MarshalResponseArray($build, ['site' => $site_array['name']]);

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
