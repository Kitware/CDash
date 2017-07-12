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
include 'public/login.php';
require_once 'include/common.php';
require_once 'include/version.php';
require_once 'models/build.php';
require_once 'models/dynamicanalysis.php';
require_once 'models/project.php';
require_once 'models/site.php';

// Checks
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo 'Not a valid id!';
    return;
}
$id = $_GET['id'];

@$date = $_GET['date'];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

$DA = new DynamicAnalysis();
$DA->Id = $id;
if (!$DA->Fill()) {
    echo 'Not a valid id!';
    return;
}


$build = new Build();
$build->Id = $DA->BuildId;
if (!$build->Exists()) {
    echo 'This build does not exist. Maybe it has been deleted.';
    return;
}
$build->FillFromId($build->Id);

checkUserPolicy(@$_SESSION['cdash']['loginid'], $build->ProjectId);

$project = new Project();
$project->Id = $build->ProjectId;
$project->Fill();

list($previousdate, $currenttime, $nextdate) = get_dates($date, $project->NightlyTime);

$xml = begin_XML_for_XSLT();
$xml .= '<title>CDash : ' . $project->Name . '</title>';
$xml .= get_cdash_dashboard_xml_by_name($project->Name, $date);

// Build
$site = new Site();
$site->Id = $build->SiteId;
$site_name = $site->GetName();
$xml .= '<build>';
$xml .= add_XML_value('site', $site_name);
$xml .= add_XML_value('buildname', $build->Name);
$xml .= add_XML_value('buildid', $build->Id);
$xml .= add_XML_value('buildtime', $build->StartTime);
$xml .= '</build>';

$xml .= '<menu>';
$xml .= add_XML_value('back', 'viewDynamicAnalysis.php?buildid=' . $build->Id);
$previous_id = $DA->GetPreviousId($build);
if ($previous_id > 0) {
    $xml .= add_XML_value('previous', 'viewDynamicAnalysisFile.php?id=' . $previous_id);
} else {
    $xml .= add_XML_value('noprevious', '1');
}
$current_id = $DA->GetLastId($build);
$xml .= add_XML_value('current', 'viewDynamicAnalysisFile.php?id=' . $current_id);
$next_id = $DA->GetNextId($build);
if ($next_id > 0) {
    $xml .= add_XML_value('next', 'viewDynamicAnalysisFile.php?id=' . $next_id);
} else {
    $xml .= add_XML_value('nonext', '1');
}
$xml .= '</menu>';

// dynamic analysis
$xml .= '<dynamicanalysis>';
$xml .= add_XML_value('status', ucfirst($DA->Status));
$xml .= add_XML_value('filename', $DA->Name);
// Only display the first 1MB of the log (in case it's huge)
$xml .= add_XML_value('log', substr($DA->Log, 0, 1024 * 1024));
$href = 'testSummary.php?project=' . $project->Id . '&name=' . $DA->Name;
if ($date) {
    $href .= '&date=' . $date;
} else {
    $href .= '&date=' . date(FMT_DATE);
}
$xml .= add_XML_value('href', $href);
$xml .= '</dynamicanalysis>';

$xml .= '</cdash>';

// Now doing the xslt transition
generate_XSLT($xml, 'viewDynamicAnalysisFile');
