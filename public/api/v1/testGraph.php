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
require_once 'include/common.php';
require_once 'include/api_common.php';
use CDash\Model\Build;
use CDash\Model\Project;
use CDash\Database;

$build = get_request_build();

$testid = pdo_real_escape_numeric($_GET['testid']);
if (!isset($testid) || !is_numeric($testid)) {
    json_error_response(['error' => 'A valid test was not specified.']);
}

$valid_types = ['time', 'status', 'measurement'];
$type = $_GET['type'];
if (!in_array($type, $valid_types)) {
    json_error_response(['error' => 'Invalid type of graph requested.']);
}

$project = new Project();
$project->Id = $build->ProjectId;
$project->Fill();

// Get the name of this test.
$pdo = Database::getInstance()->getPdo();
$stmt = $pdo->prepare('SELECT name FROM test WHERE id = ?');
pdo_execute($stmt, [$testid]);
$test_name = $stmt->fetchColumn();

$extra_columns = '';
$extra_joins = '';
$extra_wheres = '';
$query_params = [
    ':siteid'=> $build->SiteId,
    ':projectid' => $project->Id,
    ':type' => $build->Type,
    ':buildname' => $build->Name,
    ':testname' => $test_name
];
$chart_data = [];

switch ($type) {
    case 'time':
        $extra_fields = 'b2t.time, b2t.timemean, b2t.timestd';
        $chart_data[] = [
            'label' => 'Execution Time (seconds)',
            'data' => []
        ];
        $chart_data[] = [
            'label' => 'Acceptable Range',
            'data' => []
        ];
        break;
    case 'status':
        $extra_fields = 'b2t.status';
        $chart_data[] = [
            'label' => 'Passed',
            'data' => []
        ];
        $chart_data[] = [
            'label' => 'Failed',
            'data' => []
        ];
        break;
    case 'measurement':
        $measurement_name = $_GET['measurementname'];
        if (!isset($measurement_name) || !is_string($measurement_name)) {
            json_error_response(['error' => 'No measurement requested.']);
        }
        $chart_data[] = [
            'label' => $measurement_name,
            'data' => []
        ];
        $extra_fields = 'tm.value';
        $extra_joins = 'JOIN testmeasurement tm ON (b2t.testid = tm.testid)';
        $extra_where = 'AND tm.name = :measurementname';
        $params[':measurementname'] = $measurement_name;
        break;
}

// Select relevant data about all runs of this test from this recurring build.
$query =
        "SELECT b.id, b.starttime, b2t.testid, $extra_fields
        FROM build b
        JOIN build2test b2t ON (b.id = b2t.buildid)
        $extra_joins
        WHERE b.siteid = :siteid
        AND b.projectid = :projectid
        AND b.type = :type
        AND b.name = :buildname
        AND b2t.testid IN (SELECT id FROM test WHERE name = :testname)
        $extra_wheres
        ORDER BY b.starttime";
$stmt = $pdo->prepare($query);
pdo_execute($stmt, $query_params);

while ($row = $stmt->fetch()) {
    $data_point = [];
    $data_point['buildid'] = $row['id'];
    $data_point['testid'] = $row['testid'];
    $build_start_time = strtotime($row['starttime']) * 1000;
    $data_point['x'] = $build_start_time;

    switch ($type) {
        case 'time':
            $data_point['y'] = $row['time'];
            $chart_data[0]['data'][] = $data_point;

            // Also insert a point for the threshold at this time value.
            $data_point= [];
            $data_point['x'] = $build_start_time;
            $threshold =
                $row['timemean'] + $project->TestTimeStd * $row['timestd'];
            $data_point['y'] = $threshold;
            $chart_data[1]['data'][] = $data_point;
            break;

        case 'status':
            $status = strtolower($row['status']);
            // Only show passed & failed tests on the chart (skip 'notrun').
            if ($status != 'passed' && $status != 'failed') {
                continue;
            }
            if ($status == 'passed') {
                $data_point['y'] = 1;
                $chart_data[0]['data'][] = $data_point;
            } else {
                $data_point['y'] = -1;
                $chart_data[1]['data'][] = $data_point;
            }
            break;

        case 'measurement':
            $data_point['y'] = $row['value'];
            $chart_data[0]['data'][] = $data_point;
            break;
    }
}

echo json_encode(cast_data_for_JSON($chart_data));
