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
require_once 'include/version.php';
require_once 'models/build.php';
require_once 'models/project.php';
require_once 'models/site.php';

$start = microtime_float();
$response = [];

// Make sure a valid buildid was specified.
if (!isset($_GET['buildid']) || !is_numeric($_GET['buildid'])) {
    $response['error'] = 'Not a valid buildid!';
    echo json_encode($response);
    http_response_code(400);
    return;
}
$buildid = $_GET['buildid'];

$build = new Build();
$build->Id = $buildid;
if (!$build->Exists()) {
    $response['error'] = 'This build does not exist. Maybe it has been deleted.';
    echo json_encode($response);
    http_response_code(400);
    return;
}

$build->FillFromId($buildid);

// Make sure the user has access to this project.
if (!can_access_project($build->ProjectId)) {
    return;
}

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
$response['displaylabels'] = $project->DisplayLabels;

$date = get_dashboard_date_from_build_starttime($build->StartTime, $project->NightlyTime);

get_dashboard_JSON($project->Name, $date, $response);
$response['title'] = "$project->Name : Dynamic Analysis";

$menu = [];
$menu['back'] = "index.php?project=$project->Name&date=$date";

$previousbuildid = get_previous_buildid_dynamicanalysis($build->ProjectId, $build->SiteId, $build->Type, $build->Name, $build->StartTime);
if ($previousbuildid > 0) {
    $menu['previous'] = "viewDynamicAnalysis.php?buildid=$previousbuildid";
} else {
    $menu['noprevious'] = '1';
}

$currentbuildid = get_last_buildid_dynamicanalysis($build->ProjectId, $build->SiteId, $build->Type, $build->Name, $build->StartTime);
$menu['current'] = "viewDynamicAnalysis.php?buildid=$currentbuildid";

$nextbuildid = get_next_buildid_dynamicanalysis($build->ProjectId, $build->SiteId, $build->Type, $build->Name, $build->StartTime);
if ($nextbuildid > 0) {
    $menu['next'] = "viewDynamicAnalysis.php?buildid=$nextbuildid";
} else {
    $menu['nonext'] = '1';
}
$response['menu'] = $menu;

// Build
$site = new Site();
$site->Id = $build->SiteId;
$site_name = $site->GetName();

$build_response = [];
$build_response['site'] = $site_name;
$build_response['buildname'] = $build->Name;
$build_response['buildid'] = $buildid;
$build_response['buildtime'] = $build->StartTime;
$response['build'] = $build_response;

// Dynamic Analysis
$DA_stmt = $pdo->prepare(
    'SELECT * FROM dynamicanalysis WHERE buildid = ? ORDER BY status DESC');
pdo_execute($DA_stmt, [$buildid]);

$defect_types = [];
$dynamic_analyses = [];
while ($DA_row = $DA_stmt->fetch()) {
    $dynamic_analysis = [];
    $dynamic_analysis['status'] = ucfirst($DA_row['status']);
    $dynamic_analysis['name'] = $DA_row['name'];
    $dynamic_analysis['id'] = $DA_row['id'];

    $dynid = $DA_row['id'];
    $defects_stmt = $pdo->prepare(
        'SELECT * FROM dynamicanalysisdefect WHERE dynamicanalysisid = ?');
    pdo_execute($defects_stmt, [$dynid]);
    // Initialize defects array as zero for each type.
    $defects = array_fill(0, count($defect_types), 0);
    while ($defects_row = $defects_stmt->fetch()) {
        // Figure out how many defects of each type were found for this test.
        $defect_type = $defects_row['type'];
        if (array_key_exists($defect_type, $defect_nice_names)) {
            $defect_type = $defect_nice_names[$defect_type];
        }
        if (!in_array($defect_type, $defect_types)) {
            $defect_types[] = $defect_type;
            $defects[] = 0;
        }

        $column = array_search($defect_type, $defect_types);
        $defects[$column] = $defects_row['value'];
    }
    $dynamic_analysis['defects'] = $defects;

    if ($project->DisplayLabels) {
        get_labels_JSON_from_query_results(
                "SELECT text FROM label, label2dynamicanalysis
                WHERE label.id = label2dynamicanalysis.labelid AND
                label2dynamicanalysis.dynamicanalysisid = '$dynid'
                ORDER BY text ASC", $dynamic_analysis);
        if (array_key_exists('labels', $dynamic_analysis)) {
            $dynamic_analysis['labels'] = implode(', ', $dynamic_analysis['labels']);
        } else {
            $dynamic_analysis['labels'] = '';
        }
    }

    $dynamic_analyses[] = $dynamic_analysis;
}

// Insert zero entries for types of defects that were not detected by a given test.
$num_defect_types = count($defect_types);
foreach ($dynamic_analyses as &$dynamic_analysis) {
    for ($i = 0; $i < $num_defect_types; $i++) {
        if (!array_key_exists($i, $dynamic_analysis['defects'])) {
            $dynamic_analysis['defects'][$i] = 0;
        }
    }
}

$response['dynamicanalyses'] = $dynamic_analyses;

// explicitly list the defect types encountered here
// so we can dynamically generate the header row
$types_response = [];
foreach ($defect_types as $defect_type) {
    $type_response = [];
    $type_response['type'] = $defect_type;
    $types_response[] = $type_response;
}
$response['defecttypes'] = $types_response;

$end = microtime_float();
$response['generationtime'] = round($end - $start, 3);
echo json_encode(cast_data_for_JSON($response));
