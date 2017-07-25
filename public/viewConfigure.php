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

$noforcelogin = 1;
include dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';
include 'public/login.php';
include_once 'include/common.php';
include 'include/version.php';
require_once 'models/build.php';
require_once 'models/buildconfigure.php';

@$buildid = $_GET['buildid'];
if ($buildid != null) {
    $buildid = pdo_real_escape_numeric($buildid);
}

@$date = $_GET['date'];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

// Checks
if (!isset($buildid) || !is_numeric($buildid)) {
    echo 'Not a valid buildid!';
    return;
}

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

$build_array = pdo_fetch_array(pdo_query("SELECT * FROM build WHERE id='$buildid'"));
$projectid = $build_array['projectid'];
if (!isset($projectid) || $projectid == 0) {
    echo "This build doesn't exist. Maybe it has been deleted.";
    exit();
}

checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid);

$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if (pdo_num_rows($project) > 0) {
    $project_array = pdo_fetch_array($project);
    $projectname = $project_array['name'];
}

$xml = begin_XML_for_XSLT();
$xml .= '<title>CDash : ' . $projectname . '</title>';

$date = get_dashboard_date_from_build_starttime($build_array['starttime'], $project_array['nightlytime']);
$xml .= get_cdash_dashboard_xml_by_name($projectname, $date);

$siteid = $build_array['siteid'];
$buildtype = $build_array['type'];
$buildname = $build_array['name'];
$starttime = $build_array['starttime'];

// Menu
$xml .= '<menu>';
$xml .= add_XML_value('back', 'index.php?project=' . urlencode($projectname) . '&date=' . $date);

$build = new Build();
$build->Id = $buildid;
$previous_buildid = $build->GetPreviousBuildId();
$next_buildid = $build->GetNextBuildId();
$current_buildid = $build->GetCurrentBuildId();

if ($previous_buildid > 0) {
    $xml .= add_XML_value('previous', "viewConfigure.php?buildid=$previous_buildid");
} else {
    $xml .= add_XML_value('noprevious', '1');
}

$xml .= add_XML_value('current', "viewConfigure.php?buildid=$current_buildid");

if ($next_buildid > 0) {
    $xml .= add_XML_value('next', "viewConfigure.php?buildid=$next_buildid");
} else {
    $xml .= add_XML_value('nonext', '1');
}
$xml .= '</menu>';


$configures_response = array();
$configures = $build->GetConfigures();
$has_subprojects = 0;
while ($configure = pdo_fetch_array($configures)) {
    if (isset($configure['subprojectid'])) {
        $has_subprojects = 1;
    }
    $configures_response[] = buildconfigure::marshal($configure);
}

// Build
$xml .= '<build>';
$site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
$xml .= add_XML_value('site', $site_array['name']);
$xml .= add_XML_value('siteid', $siteid);
$xml .= add_XML_value('buildname', $build_array['name']);
$xml .= add_XML_value('buildid', $build_array['id']);
$xml .= add_XML_value('hassubprojects', $has_subprojects);
$xml .= '</build>';


$xml .= '<configures>';
foreach ($configures_response as $configure) {
    $xml .= '<configure>';
    foreach ($configure as $key => $val) {
        $xml .= add_XML_value($key, $val);
    }
    $xml .= '</configure>';
}
$xml .= '</configures>';
$xml .= '</cdash>';

// Now doing the xslt transition
generate_XSLT($xml, 'viewConfigure');
