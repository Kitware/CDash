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
require_once 'models/project.php';
require_once 'models/site.php';

// Make sure a valid buildid was specified.
if (!isset($_GET['buildid']) || !is_numeric($_GET['buildid'])) {
    echo 'Not a valid buildid!';
    return;
}
$buildid = $_GET['buildid'];

@$date = $_GET['date'];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

$build = new Build();
$build->Id = $buildid;
if (!$build->Exists()) {
    echo "This build doesn't exist. Maybe it has been deleted.";
    exit();
}

$build->FillFromId($buildid);

checkUserPolicy(@$_SESSION['cdash']['loginid'], $build->ProjectId);

$pdo = get_link_identifier()->getPdo();

// lookup table to make the reported defect types more understandable.
// feel free to expand as necessary.
$defect_nice_names = array(
    'FIM' => 'Freeing Invalid Memory',
    'IPR' => 'Invalid Pointer Read',
    'IPW' => 'Invalid Pointer Write');

$project = new Project();
$project->Id = $build->ProjectId;
$project->Fill();

$xml = begin_XML_for_XSLT();
$xml .= '<title>CDash : ' . $project->Name . '</title>';

$xml .= get_cdash_dashboard_xml_by_name($project->Name, $date);

$xml .= '<menu>';
$xml .= add_XML_value('back', 'index.php?project=' . urlencode($project->Name) . '&date=' . get_dashboard_date_from_build_starttime($build->StartTime, $project->NightlyTime));
$previousbuildid = get_previous_buildid_dynamicanalysis($build->ProjectId, $build->SiteId, $build->Type, $build->Name, $build->StartTime);
if ($previousbuildid > 0) {
    $xml .= add_XML_value('previous', 'viewDynamicAnalysis.php?buildid=' . $previousbuildid);
} else {
    $xml .= add_XML_value('noprevious', '1');
}
$xml .= add_XML_value('current', 'viewDynamicAnalysis.php?buildid=' . get_last_buildid_dynamicanalysis($build->ProjectId, $build->SiteId, $build->Type, $build->Name, $build->StartTime));
$nextbuildid = get_next_buildid_dynamicanalysis($build->ProjectId, $build->SiteId, $build->Type, $build->Name, $build->StartTime);
if ($nextbuildid > 0) {
    $xml .= add_XML_value('next', 'viewDynamicAnalysis.php?buildid=' . $nextbuildid);
} else {
    $xml .= add_XML_value('nonext', '1');
}
$xml .= '</menu>';

// Build
$xml .= '<build>';
$site = new Site();
$site->Id = $build->SiteId;
$site_name = $site->GetName();
$xml .= add_XML_value('site', $site_name);
$xml .= add_XML_value('buildname', $build->Name);
$xml .= add_XML_value('buildid', $buildid);
$xml .= add_XML_value('buildtime', $build->StartTime);
$xml .= '</build>';

// dynamic analysis
$i = 0;
$DA_stmt = $pdo->prepare(
    'SELECT * FROM dynamicanalysis WHERE buildid = ? ORDER BY status DESC');
pdo_execute($DA_stmt, [$buildid]);

$defect_types = array();
while ($DA_row = $DA_stmt->fetch()) {
    $xml .= '<dynamicanalysis>';
    if ($i % 2 == 0) {
        $xml .= add_XML_value('bgcolor', '#b0c4de');
    }
    $i++;
    $xml .= add_XML_value('status', ucfirst($DA_row['status']));
    $xml .= add_XML_value('name', $DA_row['name']);
    $xml .= add_XML_value('id', $DA_row['id']);

    $dynid = $DA_row['id'];
    $defects_stmt = $pdo->prepare(
        'SELECT * FROM dynamicanalysisdefect WHERE dynamicanalysisid = ?');
    pdo_execute($defects_stmt, [$dynid]);
    while ($defects_row = $defects_stmt->fetch()) {
        // defects
        $defect_type = $defects_row['type'];
        if (array_key_exists($defect_type, $defect_nice_names)) {
            $defect_type = $defect_nice_names[$defect_type];
        }
        if (!in_array($defect_type, $defect_types)) {
            $defect_types[] = $defect_type;
        }
        $column = array_search($defect_type, $defect_types);

        $xml .= '<defect>';
        $xml .= add_XML_value('column', $column);
        $xml .= add_XML_value('value', $defects_row['value']);
        $xml .= '</defect>';
    }

    $xml .= get_labels_xml_from_query_results(
        'SELECT text FROM label, label2dynamicanalysis WHERE ' .
        'label.id=label2dynamicanalysis.labelid AND ' .
        "label2dynamicanalysis.dynamicanalysisid='$dynid' " .
        'ORDER BY text ASC'
    );

    $xml .= '</dynamicanalysis>';
}

// explicitly list the defect types encountered here
// so we can dynamically generate the header row
foreach ($defect_types as $defect_type) {
    $xml .= '<defecttypes>';
    $xml .= add_XML_value('type', $defect_type);
    $xml .= '</defecttypes>';
}

$xml .= add_XML_value('numcolumns', sizeof($defect_types));
$xml .= '</cdash>';

// Now doing the xslt transition
generate_XSLT($xml, 'viewDynamicAnalysis');
