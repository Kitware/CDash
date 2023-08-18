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
use CDash\Database;

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
            abort(400, 'Invalid type of graph requested.');
        }

        $chart_data = [];

        switch ($type) {
            case 'time':
                $this->testHistoryQueryExtraColumns = ', b2t.time, b2t.timemean, b2t.timestd';
                $chart_data[] = [
                    'label' => 'Execution Time (seconds)',
                    'data' => [],
                ];
                $chart_data[] = [
                    'label' => 'Acceptable Range',
                    'data' => [],
                ];
                break;
            case 'status':
                $this->testHistoryQueryExtraColumns = ', b2t.status';
                $chart_data[] = [
                    'label' => 'Passed',
                    'data' => [],
                ];
                $chart_data[] = [
                    'label' => 'Failed',
                    'data' => [],
                ];
                break;
            case 'measurement':
                $measurement_name = $_GET['measurementname'];
                if (!isset($measurement_name) || !is_string($measurement_name)) {
                    abort(400, 'No measurement requested.');
                }
                $chart_data[] = [
                    'label' => $measurement_name,
                    'data' => [],
                ];
                $this->testHistoryQueryExtraColumns = ', tm.value';
                $this->testHistoryQueryExtraJoins = 'JOIN testmeasurement tm ON (b2t.outputid = tm.outputid)';
                $this->testHistoryQueryExtraWheres = 'AND tm.name = :measurementname';
                $this->testHistoryQueryParams[':measurementname'] = $measurement_name;
                break;
        }

        // Select relevant data about all runs of this test from this recurring build.
        $this->generateTestHistoryQuery();
        $stmt = $this->db->prepare($this->testHistoryQuery);
        $this->db->execute($stmt, $this->testHistoryQueryParams);

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
