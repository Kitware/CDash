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


/** Get the previous file id dynamicanalysis*/
function get_previous_fileid_dynamicanalysis($filename, $projectid, $siteid, $buildtype, $buildname, $starttime)
{
    $previousbuild = pdo_query("SELECT dynamicanalysis.id FROM build,dynamicanalysis
                              WHERE build.siteid='$siteid' AND build.type='$buildtype' AND build.name='$buildname'
                              AND build.projectid='$projectid' AND build.starttime<'$starttime'
                              AND dynamicanalysis.buildid=build.id
                              AND dynamicanalysis.name='$filename'
                              ORDER BY build.starttime DESC LIMIT 1");

    if (pdo_num_rows($previousbuild) > 0) {
        $previousbuild_array = pdo_fetch_array($previousbuild);
        return $previousbuild_array['id'];
    }
    return 0;
}

/** Get the next file id dynamicanalysis*/
function get_next_fileid_dynamicanalysis($filename, $projectid, $siteid, $buildtype, $buildname, $starttime)
{
    $nextbuild = pdo_query("SELECT dynamicanalysis.id FROM build,dynamicanalysis
                          WHERE build.siteid='$siteid' AND build.type='$buildtype' AND build.name='$buildname'
                          AND build.projectid='$projectid' AND build.starttime>'$starttime'
                          AND dynamicanalysis.buildid=build.id
                          AND dynamicanalysis.name='$filename'
                          ORDER BY build.starttime ASC LIMIT 1");

    if (pdo_num_rows($nextbuild) > 0) {
        $nextbuild_array = pdo_fetch_array($nextbuild);
        return $nextbuild_array['id'];
    }
    return 0;
}

/** Get the last file id dynamicanalysis */
function get_last_fileid_dynamicanalysis($filename, $projectid, $siteid, $buildtype, $buildname, $starttime)
{
    $nextbuild = pdo_query("SELECT dynamicanalysis.id FROM build,dynamicanalysis
                          WHERE build.siteid='$siteid' AND build.type='$buildtype' AND build.name='$buildname'
                          AND build.projectid='$projectid'
                          AND dynamicanalysis.buildid=build.id
                          AND dynamicanalysis.name='$filename'
                          ORDER BY build.starttime DESC LIMIT 1");

    if (pdo_num_rows($nextbuild) > 0) {
        $nextbuild_array = pdo_fetch_array($nextbuild);
        return $nextbuild_array['id'];
    }
    return 0;
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
$previousfileid = get_previous_fileid_dynamicanalysis($DA->Name, $project->Id, $build->SiteId, $build->Type, $build->Name, $build->StartTime);
if ($previousfileid > 0) {
    $xml .= add_XML_value('previous', 'viewDynamicAnalysisFile.php?id=' . $previousfileid);
} else {
    $xml .= add_XML_value('noprevious', '1');
}
$xml .= add_XML_value('current', 'viewDynamicAnalysisFile.php?id=' . get_last_fileid_dynamicanalysis($DA->Name, $project->Id, $build->SiteId, $build->Type, $build->Name, $build->StartTime));
$nextfileid = get_next_fileid_dynamicanalysis($DA->Name, $project->Id, $build->SiteId, $build->Type, $build->Name, $build->StartTime);
if ($nextfileid > 0) {
    $xml .= add_XML_value('next', 'viewDynamicAnalysisFile.php?id=' . $nextfileid);
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
