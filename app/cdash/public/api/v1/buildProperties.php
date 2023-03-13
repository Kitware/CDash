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

namespace CDash\Api\v1\BuildProperties;

require_once 'include/pdo.php';
require_once 'include/api_common.php';
require_once 'include/filterdataFunctions.php';

use App\Services\PageTimer;
use CDash\Database;
use DateInterval;
use DateTime;
use Illuminate\Support\Facades\Session;

if (!function_exists('get_defects_for_builds')) {
    function get_defects_for_builds()
    {
        if (!array_key_exists('buildid', $_GET)) {
            json_error_response('Missing parameter: buildid');
        }
        if (!array_key_exists('defect', $_GET)) {
            json_error_response('Missing parameter: defect');
        }

        if (!is_array($_GET['buildid']) || count($_GET['buildid']) < 1) {
            json_error_response("No builds specified");
        }
        if (!is_array($_GET['defect']) || count($_GET['defect']) < 1) {
            json_error_response("No defects specified");
        }

        $pdo = Database::getInstance()->getPdo();
        $placeholder_str = str_repeat('?,', count($_GET['buildid']) - 1). '?';

        if (!function_exists('gather_defects')) {
            function gather_defects($stmt, $prettyname, &$defects_response)
            {
                $results_found = false;
                while ($row = $stmt->fetch()) {
                    $results_found = true;
                    $descr = $row['descr'];
                    $idx = array_search($descr, array_column($defects_response, 'descr'));
                    if ($idx === false) {
                        $defects_response[] = [
                            'descr' => $descr,
                            'type' => $prettyname,
                            'builds' => []
                        ];
                        $idx = count($defects_response) - 1;
                    }
                    $defects_response[$idx]['builds'][] = $row['buildid'];
                }
                return $results_found;
            }
        }

        $defects_response = [];
        foreach ($_GET['defect'] as $defect) {
            $valid_defect = false;
            $sql = '';
            $sql2 = null;

            $error_defect = false;
            if ($defect == 'builderrors') {
                $error_defect = true;
                $prettyname = 'Error';
                $type = 0;
            } elseif ($defect == 'buildwarnings') {
                $error_defect = true;
                $prettyname = 'Warning';
                $type = 1;
            }

            if ($error_defect) {
                $valid_defect = true;
                // Query builderror table.
                $sql =
                    "SELECT buildid, text AS descr
                FROM builderror
                WHERE type = $type AND
                buildid IN ($placeholder_str)";
                // Query buildfailure table.
                $sql2 =
                    "SELECT bf.buildid, bfd.stderror AS descr
                FROM buildfailure bf
                JOIN buildfailuredetails bfd ON bf.detailsid = bfd.id
                WHERE bfd.type = $type AND
                bf.buildid IN ($placeholder_str)";
            } elseif ($defect == 'testfailed') {
                $valid_defect = true;
                $prettyname = 'Test Failure';
                $sql =
                    "SELECT t.name AS descr, b.id AS buildid
                FROM test t
                JOIN build2test b2t ON b2t.testid = t.id
                JOIN build b ON b.id = b2t.buildid
                WHERE b2t.status = 'failed' AND
                b.id IN ($placeholder_str)";
            }

            if (!$valid_defect) {
                continue;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($_GET['buildid']);

            if (!gather_defects($stmt, $prettyname, $defects_response) &&
                !is_null($sql2)) {
                $stmt = $pdo->prepare($sql2);
                $stmt->execute($_GET['buildid']);
                gather_defects($stmt, $prettyname, $defects_response);
            }
        }

        $response = [];
        $response['defects'] = $defects_response;
        echo json_encode(cast_data_for_JSON($response));
    }
}

// From now on, we are only concerned with the types of defects that were
// selected by the user (or by default).
// Filter out those that were not selected.
if (!function_exists('defect_type_selected')) {
    function defect_type_selected($defect_type)
    {
        return $defect_type['selected'];
    }
}

$pageTimer = new PageTimer();
$response = [];

if (array_key_exists('buildid', $_GET)) {
    get_defects_for_builds();
    return;
}

// Make sure the user has access to this project.
$Project = get_project_from_request();
if (is_null($Project)) {
    return;
}

// Load project data.
$Project->Fill();

// Begin our JSON response.
$response = begin_JSON_response();
$response['title'] = "$Project->Name : Build Properties";
$response['showcalendar'] = 0;
$response['nightlytime'] = $Project->NightlyTime;

// Figure out our time range.
$date = null;
$beginning_timestamp = null;
$end_timestamp = null;
if (isset($_GET['begin']) && isset($_GET['end'])) {
    $beginning_date = $_GET['begin'];
    $end_date = $_GET['end'];
    list($unused, $beginning_timestamp) =
        get_dates($beginning_date, $Project->NightlyTime);
    list($unused, $end_timestamp) =
        get_dates($end_date, $Project->NightlyTime);
    $datetime = new DateTime();
    $datetime->setTimeStamp($end_timestamp);
    $datetime->add(new DateInterval('P1D'));
    $end_timestamp = $datetime->getTimestamp();
    $response['begin'] = $beginning_date;
    $response['end'] = $end_date;
} elseif (isset($_GET['date'])) {
    // Otherwise use the provided date (if any).
    $date = $_GET['date'];
} else {
    // Default to the current date.
    $date = date(FMT_DATE);
}
if (is_null($beginning_timestamp)) {
    list($unused, $beginning_timestamp) =
        get_dates($date, $Project->NightlyTime);
    $datetime = new DateTime();
    $datetime->setTimeStamp($beginning_timestamp);
    $datetime->add(new DateInterval('P1D'));
    $end_timestamp = $datetime->getTimestamp();
}
$begin_date = date(FMT_DATETIME, $beginning_timestamp);
$end_date = date(FMT_DATETIME, $end_timestamp);

get_dashboard_JSON($Project->Name, date(FMT_DATE, $end_timestamp), $response);

// Hide traditional Previous/Current/Next links.
$response['hidenav'] = true;

// List of possible types of defects to track.
$defect_types = [
    [
        'name' => 'builderrors',
        'prettyname' => 'Errors',
        'selected' => false
    ],
    [
        'name' => 'buildwarnings',
        'prettyname' => 'Warnings',
        'selected' => false
    ],
    [
        'name' => 'testfailed',
        'prettyname' => 'Test Failures',
        'selected' => false
    ]
];

// Mark specified types of defects as selected.
if (isset($_GET['defects'])) {
    $selected_defect_types = explode(',', $_GET['defects']);
    foreach ($selected_defect_types as $selected_type) {
        foreach ($defect_types as &$type) {
            if ($type['name'] === $selected_type) {
                $type['selected'] = true;
                break;
            }
        }
        unset($type);
    }
} else {
    // Use the full list if none was specified.
    foreach ($defect_types as &$type) {
        $type['selected'] = true;
    }
    unset($type);
}
$response['defecttypes'] = $defect_types;

$defect_types = array_filter($defect_types, 'defect_type_selected');

// Construct an SQL SELECT clause for the requested types of defects.
$defect_keys = [];
foreach ($defect_types as $type) {
    $defect_keys[] = "b.{$type['name']}";
}
$defect_selection = implode(', ', $defect_keys);

// Get properties and error info for selected builds.
$pdo = Database::getInstance()->getPdo();
$stmt = $pdo->prepare(
    "SELECT b.id, b.name, $defect_selection, bp.properties
    FROM build b
    JOIN buildproperties bp ON (bp.buildid = b.id)
    WHERE b.projectid = :projectid AND b.parentid IN (0, -1)
    AND b.starttime < :end AND b.starttime >= :begin");
$stmt->bindParam(':projectid', $Project->Id);
$stmt->bindParam(':begin', $begin_date);
$stmt->bindParam(':end', $end_date);
pdo_execute($stmt);

$builds_response = [];
$all_properties = [];
while ($row = $stmt->fetch()) {
    $build_response = [];
    $buildid = $row['id'];
    $build_response['id'] = $buildid;
    foreach ($defect_types as $defect_type) {
        $key = $defect_type['name'];
        $build_response[$key] = $row[$key];
    }
    $properties = json_decode($row['properties'], true);
    $build_response['properties'] = $properties;
    $builds_response[] = $build_response;

    // Check for properties we haven't encountered yet.
    $new_property_keys = array_diff(array_keys($properties), array_keys($all_properties));
    foreach ($new_property_keys as $key) {
        // Determine what type of property this is.
        $value = $properties[$key];
        if (is_array($value)) {
            $type = 'array';
        } elseif (is_bool($value)) {
            $type = 'bool';
        } elseif (is_numeric($value) && strpos($value, 'e') === false) {
            $type = 'number';
        } else {
            $type = 'string';
        }

        // Add it to our list.
        $all_properties[$key] = ['type' => $type];
    }
}
$response['builds'] = $builds_response;
$response['properties'] = $all_properties;

// Timeline chart needs to know what defects we care about
// and what page we're coming from.
Session::put('defecttypes', $defect_types);

$response['filterdata']['pageId'] = 'buildProperties.php';

$pageTimer->end($response);
echo json_encode(cast_data_for_JSON($response));
