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

use CDash\Database;

// Handle required parameters: project and page.
$Project = get_project_from_request();
if (!can_access_project($Project->Id)) {
    return;
}
$Project->Fill();

if (!isset($_GET['page'])) {
    json_error_response('Missing parameter: page');
}

// Handle optional parameters: begin & end or date.
$begin = null;
$end = null;
if (isset($_GET['begin']) && isset($_GET['end'])) {
    $begin = $_GET['begin'];
    $end_datetime = new DateTime($_GET['end'] . ' +1 day');
} elseif (isset($_GET['date'])) {
    $begin = $_GET['date'];
    $end_datetime = new DateTime($begin . ' +1 day');
}
// Extend end +1 day so it covers the whole testing day.
$end = $end_datetime->format('Y-m-d');

// Generate data based on the page that's requesting this chart.
$response = [];
switch ($_GET['page']) {
    case 'index.php':
        $response = chart_for_index($Project, $begin, $end);
        break;
    case 'testOverview.php':
        $response = chart_for_testOverview($Project, $begin, $end);
        break;
    default:
        json_error_response('Unexpected value for page');
        break;
}
echo json_encode(cast_data_for_JSON($response));


function chart_for_index($Project, $begin, $end)
{
    $defect_types = [
        [
            'name' => 'builderrors',
            'prettyname' => 'Errors',
        ],
        [
            'name' => 'buildwarnings',
            'prettyname' => 'Warnings',
        ],
        [
            'name' => 'testfailed',
            'prettyname' => 'Test Failures',
        ]
    ];

    // Query for defects on expected builds only.
    $pdo = Database::getInstance()->getPdo();
    $stmt = $pdo->prepare('
        SELECT b.id, b.starttime, b.builderrors, b.buildwarnings, b.testfailed
        FROM build b
        JOIN build2group b2g ON b2g.buildid = b.id
        JOIN build2grouprule b2gr ON
                b2g.groupid = b2gr.groupid AND b2gr.buildtype = b.type AND
                b2gr.buildname = b.name AND b2gr.siteid = b.siteid
        WHERE b.projectid = :projectid AND b.parentid IN (0, -1)
        AND b2gr.expected = 1
        ORDER BY starttime');
    if (!pdo_execute($stmt, [':projectid' => $Project->Id])) {
        json_error_response('Failed to load results');
        return [];
    }
    return get_timeline_chart_data($defect_types, $stmt, $Project, $begin, $end,
                                   true);
}

function chart_for_testOverview($Project, $begin, $end)
{
    $defect_types = [
        [
            'name' => 'testfailed',
            'prettyname' => 'Failing Tests',
        ],
        [
            'name' => 'testnotrun',
            'prettyname' => 'Not Run Tests',
        ],
        [
            'name' => 'testpassed',
            'prettyname' => 'Passing Tests',
        ]
    ];

    $pdo = Database::getInstance()->getPdo();
    $stmt = $pdo->prepare('
        SELECT b.id, b.starttime, b.testfailed, b.testnotrun, b.testpassed
        FROM build b
        WHERE b.projectid = :projectid AND b.parentid IN (0, -1)
        ORDER BY starttime');
    if (!pdo_execute($stmt, [':projectid' => $Project->Id])) {
        json_error_response('Failed to load results');
        return [];
    }
    return get_timeline_chart_data($defect_types, $stmt, $Project, $begin, $end,
                                   false);
}

function get_timeline_chart_data($defect_types, $input_stmt, $Project, $begin,
                                 $end, $include_clean_builds)
{
    $pdo = Database::getInstance()->getPdo();
    $response = [];

    // Find the dates of the oldest and newest builds for this project.
    $query = "
        SELECT id from build
        WHERE projectid = :projectid AND
            (starttime =
                (SELECT MIN(starttime) FROM build WHERE projectid = :projectid))
        LIMIT 1";
    $stmt = $pdo->prepare($query);
    pdo_execute($stmt, [':projectid' => $Project->Id]);
    $b = new Build();
    $b->Id = $stmt->fetchColumn();
    $oldest_date = $b->GetDate();

    $query = "
        SELECT id from build
        WHERE projectid = :projectid AND
            (starttime =
                (SELECT MAX(starttime) FROM build WHERE projectid = :projectid))
        LIMIT 1";
    $stmt = $pdo->prepare($query);
    pdo_execute($stmt, [':projectid' => $Project->Id]);
    $b = new Build();
    $b->Id = $stmt->fetchColumn();
    $newest_date = $b->GetDate();

    // Extend the end of our date range by *two* days.
    //
    // The first day is added because PHP's DatePeriod does not include the
    // ending day in its range.
    //
    // The second day is added so that the chart extends through the end
    // of the final testing day.
    // We do this to satisfy two competing desires:
    // 1) A single day's selection should appear as a box (not a line).
    // 2) It should be possible to select all builds (from start to end date).
    $end_datetime = new DateTime($newest_date);
    $end_datetime->modify('+2 days');
    $newest_date = $end_datetime->format('Y-m-d');

    // Record min and max timestamps.
    list($unused, $timestamp) = get_dates($oldest_date, $Project->NightlyTime);
    $min_timestamp = $timestamp * 1000;
    list($unused, $timestamp) = get_dates($newest_date, $Project->NightlyTime);
    $max_timestamp = $timestamp * 1000;

    // time_data is used to record the data that will populate our
    // chart.  Its format is time_data[date][defect_type] = num_builds.
    $time_data = [];
    // time_to_date is a mapping of timestamp (int) to date (str).
    // We record this info here we don't have to reimplement this logic
    // in Javascript.
    $time_to_date = [];

    // Initialize time_data and time_to_date for each date in our range.
    $period = new DatePeriod(
        new DateTime($oldest_date),
        new DateInterval('P1D'),
        new DateTime($newest_date)
    );
    foreach ($period as $datetime) {
        $date = $datetime->format('Y-m-d');
        list($unused, $start_of_day) = get_dates($date, $Project->NightlyTime);
        // Convert to milliseconds.
        // This is the format nvd3 (our charting library) expects.
        $start_of_day *= 1000;

        // Initialize trends for this date.
        if ($include_clean_builds) {
            $time_data[$start_of_day] = ['clean' => 0];
        } else {
            $time_data[$start_of_day] = [];
        }
        foreach ($defect_types as $defect_type) {
            $key = $defect_type['name'];
            $time_data[$start_of_day][$key] = 0;
        }

        $time_to_date[$start_of_day] = $date;
    }

    $nightly_timestamp = strtotime($Project->NightlyTime);

    while ($row = $input_stmt->fetch()) {
        // Use this build's starttime to get the beginning of the appropriate
        // testing day.
        $test_date =
            Build::GetTestingDate($row['starttime'], $nightly_timestamp);
        list($unused, $start_of_day) =
            get_dates($test_date, $Project->NightlyTime);
        $start_of_day *= 1000;

        // Update our chart data to reflect this build's defects (if any).
        $clean_build = true;
        foreach ($defect_types as $defect_type) {
            $key = $defect_type['name'];
            if ($row[$key] > 0) {
                $time_data[$start_of_day][$key] += 1;
                $clean_build = false;
            }
        }
        if ($include_clean_builds && $clean_build) {
            $time_data[$start_of_day]['clean'] += 1;
        }
    }

    $response['min'] = $min_timestamp;
    $response['max'] = $max_timestamp;
    $response['time_to_date'] = $time_to_date;

    // Now that we've collected all this data, massage into the format used
    // by nvd3.
    // time_chart_data = [{
    //   key: defect_type,
    //   values = [[timestamp, num_builds], ...]
    //   }, ...]
    $chart_keys = [];
    foreach ($defect_types as $defect_type) {
        $chart_keys[] = [
            'name' => $defect_type['name'],
            'prettyname' => $defect_type['prettyname']
        ];
    }
    if ($include_clean_builds) {
        $chart_keys[] = ['name' => 'clean', 'prettyname' => 'Clean Builds'];
    }

    $time_chart_data = [];
    foreach ($chart_keys as $key) {
        $trend = [];
        $trend['key'] = $key['prettyname'];
        $trend['values'] = [];
        foreach ($time_data as $start_of_day => $day_values) {
            $data_point = [$start_of_day, $day_values[$key['name']]];
            $trend['values'][] = $data_point;
        }
        $time_chart_data[] = $trend;
    }

    $response['data'] = $time_chart_data;

    // Determine the range of the chart that should be selected by default.
    // This is referred to as the extent.
    list($unused, $begin_extent) = get_dates($begin, $Project->NightlyTime);
    $begin_extent *= 1000;
    list($unused, $end_extent) = get_dates($end, $Project->NightlyTime);
    $end_extent *= 1000;
    if ($begin_extent < $min_timestamp) {
        $begin_extent = $min_timestamp;
    }
    if ($end_extent > $max_timestamp) {
        $end_extent = $max_timestamp;
    }
    if ($begin_extent > $end_extent) {
        $begin_extent = $end_extent;
    }
    $response['extentstart'] = $begin_extent;
    $response['extentend'] = $end_extent;
    return $response;
}
