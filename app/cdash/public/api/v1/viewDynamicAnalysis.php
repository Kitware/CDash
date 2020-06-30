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
require_once 'include/pdo.php';
require_once 'include/api_common.php';

use App\Services\PageTimer;
use App\Services\TestingDay;

use CDash\Model\Project;
use CDash\Model\Site;

$pageTimer = new PageTimer();
$response = [];

$build = get_request_build();
if (is_null($build)) {
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

$date = TestingDay::get($project, $build->StartTime);

$response = begin_JSON_response();
get_dashboard_JSON($project->Name, $date, $response);
$response['title'] = "$project->Name : Dynamic Analysis";

$menu = [];
if ($build->GetParentId() > 0) {
    $menu['back'] = 'index.php?project=' . urlencode($project->Name) . "&parentid={$build->GetParentId()}";
} else {
    $menu['back'] = 'index.php?project=' . urlencode($project->Name) . "&date=$date";
}

$previousbuildid = get_previous_buildid_dynamicanalysis($build->ProjectId, $build->SiteId, $build->Type, $build->Name, $build->StartTime);
if ($previousbuildid > 0) {
    $menu['previous'] = "viewDynamicAnalysis.php?buildid=$previousbuildid";
} else {
    $menu['previous'] = false;
}

$currentbuildid = get_last_buildid_dynamicanalysis($build->ProjectId, $build->SiteId, $build->Type, $build->Name, $build->StartTime);
$menu['current'] = "viewDynamicAnalysis.php?buildid=$currentbuildid";

$nextbuildid = get_next_buildid_dynamicanalysis($build->ProjectId, $build->SiteId, $build->Type, $build->Name, $build->StartTime);
if ($nextbuildid > 0) {
    $menu['next'] = "viewDynamicAnalysis.php?buildid=$nextbuildid";
} else {
    $menu['next'] = false;
}
$response['menu'] = $menu;

// Build
$site = new Site();
$site->Id = $build->SiteId;
$site_name = $site->GetName();

$build_response = [];
$build_response['site'] = $site_name;
$build_response['buildname'] = $build->Name;
$build_response['buildid'] = $build->Id;
$build_response['buildtime'] = $build->StartTime;
$response['build'] = $build_response;

// Dynamic Analysis
$defect_types = [];
$dynamic_analyses = [];

// Process 50 rows at a time so we don't run out of memory.
$rows = \DB::table('dynamicanalysis')
        ->where('buildid', '=', $build->Id)
        ->orderBy('status', 'desc')
        ->chunk(50, function ($rows) use ($pdo, &$dynamic_analyses, &$defect_types, $defect_nice_names, $project) {
            foreach ($rows as $DA_row) {
                $dynamic_analysis = [];
                $dynamic_analysis['status'] = ucfirst($DA_row->status);
                $dynamic_analysis['name'] = $DA_row->name;
                $dynamic_analysis['id'] = $DA_row->id;

                $dynid = $DA_row->id;
                $defects_stmt = $pdo->prepare(
                    'SELECT * FROM dynamicanalysisdefect WHERE dynamicanalysisid = ?');
                pdo_execute($defects_stmt, [$dynid]);
                // Initialize defects array as zero for each type.
                $num_types = count($defect_types);
                if ($num_types > 0) {
                    // Work around a bug in older versions of PHP where the 2nd argument to
                    // array_fill must be greater than zero.
                    $defects = array_fill(0, count($defect_types), 0);
                } else {
                    $defects = [];
                }
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
        });

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

$pageTimer->end($response);
echo json_encode(cast_data_for_JSON($response));
