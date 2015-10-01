<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include_once("api_setpath.php");
$noforcelogin = 1;
include("cdash/config.php");
require_once("cdash/pdo.php");
include('login.php');
include_once("cdash/common.php");
include("cdash/version.php");

@$buildid = $_GET["buildid"];
if ($buildid != null) {
    $buildid = pdo_real_escape_numeric($buildid);
}
@$date = $_GET["date"];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

$response = array();

// Checks
if (!isset($buildid) || !is_numeric($buildid)) {
    $response['error'] = "Not a valid buildid!";
    echo json_encode($response);
    return;
}

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

$build_array = pdo_fetch_array(pdo_query("SELECT * FROM build WHERE id='$buildid'"));
$projectid = $build_array["projectid"];
if (!isset($projectid) || $projectid==0) {
    $response['error'] = "This build doesn't exist. Maybe it has been deleted.";
    echo json_encode($response);
    return;
}

if (!checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid, 1)) {
    $response['requirelogin'] = 1;
    echo json_encode($response);
    return;
}

$siteid = $build_array["siteid"];
$buildtype = $build_array["type"];
$buildname = $build_array["name"];
$starttime = $build_array["starttime"];

$project_array = pdo_fetch_array(pdo_query("SELECT * FROM project WHERE id='$projectid'"));
$projectname = $project_array["name"];

$response = begin_JSON_response();
$response['title'] = "CDash : $projectname";

$date = get_dashboard_date_from_build_starttime($build_array["starttime"], $project_array["nightlytime"]);
get_dashboard_JSON_by_name($projectname, $date, $response);

// Menu
$menu = array();
$menu['back'] = "index.php?project=".urlencode($projectname)."&date=".$date;
$previousbuildid = get_previous_buildid($projectid, $siteid, $buildtype, $buildname, $starttime);
if ($previousbuildid>0) {
    $menu['previous'] = "viewNotes.php?buildid=".$previousbuildid;
} else {
    $menu['noprevious'] = "1";
}
$menu['current'] = "viewNotes.php?buildid=".get_last_buildid($projectid, $siteid, $buildtype, $buildname, $starttime);
$nextbuildid = get_next_buildid($projectid, $siteid, $buildtype, $buildname, $starttime);
if ($nextbuildid>0) {
    $menu['next'] = "viewNotes.php?buildid=".$nextbuildid;
} else {
    $menu['nonext'] = "1";
}
$response['menu'] = $menu;

// Build
$build = array();
$site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
$build['site'] = $site_array["name"];
$build['siteid'] = $siteid;
$build['buildname'] = $build_array['name'];
$build['buildid'] = $build_array['id'];
$build['stamp'] = $build_array['stamp'];
$response['build'] = $build;

// Notes
$notes = array();
$build2note = pdo_query("SELECT noteid,time FROM build2note WHERE buildid='$buildid'");
while ($build2note_array = pdo_fetch_array($build2note)) {
    $noteid = $build2note_array["noteid"];
    $note_array = pdo_fetch_array(pdo_query("SELECT * FROM note WHERE id='$noteid'"));

    $note = array();
    $note['name'] = $note_array["name"];
    $note['text'] = $note_array["text"];
    $note['time'] = $build2note_array["time"];

    $notes[] = $note;
}
$response['notes'] = $notes;

echo json_encode($response);
