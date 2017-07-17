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

require_once dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';
$noforcelogin = 1;
require_once 'public/login.php';
require_once 'include/common.php';
require_once 'include/version.php';
require_once 'models/build.php';
require_once 'models/buildconfigure.php';
require_once 'models/project.php';
require_once 'models/site.php';

@$buildid = $_GET['buildid'];
if ($buildid != null) {
    $buildid = pdo_real_escape_numeric($buildid);
}

// Checks
if (!isset($buildid) || !is_numeric($buildid)) {
    echo 'Not a valid buildid!';
    return;
}

$buildid = $_GET['buildid'];
$build = new Build();
$build->Id = $buildid;
if (!$build->Exists()) {
    echo 'This build does not exist. Maybe it has been deleted.';
    return;
}

$build->FillFromId($build->Id);
checkUserPolicy(@$_SESSION['cdash']['loginid'], $build->ProjectId);

$project = new Project();
$project->Id = $build->ProjectId;
$project->Fill();

$xml = begin_XML_for_XSLT();
$xml .= '<title>CDash : ' . $project->Name . '</title>';

$date = get_dashboard_date_from_build_starttime($build->StartTime, $project->NightlyTime);
$xml .= get_cdash_dashboard_xml_by_name($project->Name, $date);


// Menu
$xml .= '<menu>';
$xml .= add_XML_value('back', 'index.php?project=' . urlencode($project->Name) . '&date=' . $date);

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
$site = new Site();
$site->Id = $build->SiteId;
$xml .= '<build>';
$xml .= add_XML_value('site', $site_name);
$xml .= add_XML_value('siteid', $site->GetName());
$xml .= add_XML_value('buildname', $build->Name);
$xml .= add_XML_value('buildid', $build->Id);
$xml .= add_XML_value('hassubprojects', $has_subprojects);
$xml .= '</build>';

$configures_response = [];
$configures = $build->GetConfigures();
while ($configure = $configures->fetch()) {
    $configures_response[] = buildconfigure::marshal($configure);
}

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
