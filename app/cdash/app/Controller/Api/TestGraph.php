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

namespace CDash\Controller\Api;

use App\Models\BuildTest;
use App\Models\Test;
use App\Models\TestOutput;

use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Project;
use CDash\Model\Site;

require_once 'include/api_common.php';

class TestGraph extends BuildTestApi
{
    public $echoResponse;
    public $buildtest;
    public $validTypes;

    public function __construct(Database $db, BuildTest $buildtest)
    {
        $this->echoResponse = true;
        $this->validTypes = ['time', 'status', 'measurement'];
        parent::__construct($db, $buildtest);
    }

    public function getResponse()
    {
        $type = $_GET['type'];
        if (!in_array($type, $this->validTypes)) {
            json_error_response(['error' => 'Invalid type of graph requested.']);
            return [];
        }

        $extra_columns = '';
        $extra_joins = '';
        $extra_wheres = '';
        $query_params = [
            ':siteid'=> $this->build->SiteId,
            ':projectid' => $this->project->Id,
            ':type' => $this->build->Type,
            ':buildname' => $this->build->Name,
            ':testname' => $this->test->name
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
                $extra_joins = 'JOIN testmeasurement tm ON (b2t.outputid = tm.outputid)';
                $extra_wheres = 'AND tm.name = :measurementname';
                $query_params[':measurementname'] = $measurement_name;
                break;
        }

        // Select relevant data about all runs of this test from this recurring build.
        $query =
            "SELECT b.starttime, b2t.id AS buildtestid, $extra_fields
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
        $stmt = $this->db->prepare($query);
        $this->db->execute($stmt, $query_params);

        while ($row = $stmt->fetch()) {
            $data_point = [];
            $data_point['buildtestid'] = $row['buildtestid'];
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
                        $row['timemean'] + $this->project->TestTimeStd * $row['timestd'];
                    $data_point['y'] = $threshold;
                    $chart_data[1]['data'][] = $data_point;
                    break;

                case 'status':
                    $status = strtolower($row['status']);
                    // Only show passed & failed tests on the chart (skip 'notrun').
                    if ($status != 'passed' && $status != 'failed') {
                        break;
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
        return $chart_data;
    }
}
