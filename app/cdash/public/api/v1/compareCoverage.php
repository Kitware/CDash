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

namespace CDash\Api\v1\CompareCoverage;

require_once 'include/pdo.php';
require_once 'include/api_common.php';
require_once 'include/filterdataFunctions.php';

use App\Services\PageTimer;
use CDash\Model\Project;

if (!function_exists('CDash\Api\v1\CompareCoverage\create_subproject')) {
    function create_subproject($coverage, $builds)
    {
        $subproject = array();
        $subproject['label'] = $coverage['label'];
        // Create a placeholder for each build
        foreach ($builds as $build) {
            $subproject[$build['key']] = -1;
        }
        return $subproject;
    }
}

if (!function_exists('CDash\Api\v1\CompareCoverage\populate_subproject')) {
    function populate_subproject($subproject, $key, $coverage)
    {
        $subproject[$key] = $coverage['percentage'];
        $subproject[$key.'id'] = $coverage['buildid'];
        if (array_key_exists('percentagediff', $coverage)) {
            $percentagediff = $coverage['percentagediff'];
        } else {
            $percentagediff = null;
        }
        $subproject[$key.'percentagediff'] = $percentagediff;
        return $subproject;
    }
}


if (!function_exists('CDash\Api\v1\CompareCoverage\get_build_label')) {
    function get_build_label($buildid, $build_array)
    {
        // Figure out how many labels to report for this build.
        if (!array_key_exists('numlabels', $build_array) ||
            $build_array['numlabels'] == 0
        ) {
            $num_labels = 0;
        } else {
            $num_labels = $build_array['numlabels'];
        }

        // Assign a label to this build based on how many labels it has.
        if ($num_labels == 0) {
            $build_label = '(none)';
        } elseif ($num_labels == 1) {
            // If exactly one label for this build, look it up here.
            $label_query =
                'SELECT l.text FROM label AS l
            INNER JOIN label2build AS l2b ON (l.id=l2b.labelid)
            INNER JOIN build AS b ON (l2b.buildid=b.id)
            WHERE b.id=' . qnum($buildid);
            $label_result = pdo_single_row_query($label_query);
            $build_label = $label_result['text'];
        } else {
            // More than one label, just report the number.
            $build_label = "($num_labels labels)";
        }

        return $build_label;
    }
}


if (!function_exists('CDash\Api\v1\CompareCoverage\get_coverage')) {
    function get_coverage($build_data, $subproject_groups)
    {
        $response = array();
        $response['coveragegroups'] = array();

        // Summarize coverage by subproject groups.
        // This happens when we have subprojects and we're looking at the children
        // of a specific build.
        $coverage_groups = array();
        foreach ($subproject_groups as $group) {
            // Keep track of coverage info on a per-group basis.
            $groupId = $group->GetId();

            $coverage_groups[$groupId] = array();
            $coverage_groups[$groupId]['label'] = $group->GetName();
            $coverage_groups[$groupId]['loctested'] = 0;
            $coverage_groups[$groupId]['locuntested'] = 0;
            $coverage_groups[$groupId]['coverages'] = array();
        }
        if (count($subproject_groups) > 1) {
            $coverage_groups[0] = array();
            $coverage_groups[0]['label'] = 'Total';
            $coverage_groups[0]['loctested'] = 0;
            $coverage_groups[0]['locuntested'] = 0;
        }

        // Generate the JSON response from the rows of builds.
        foreach ($build_data as $build_array) {
            $buildid = $build_array['id'];
            $coverageIsGrouped = false;
            $coverage_response = array();
            $coverage_response['buildid'] = $build_array['id'];

            $percent = round(
                compute_percentcoverage($build_array['loctested'],
                    $build_array['locuntested']), 2);

            if ($build_array['subprojectgroup']) {
                $groupId = $build_array['subprojectgroup'];
                if (array_key_exists($groupId, $coverage_groups)) {
                    $coverageIsGrouped = true;
                    $coverage_groups[$groupId]['loctested'] +=
                        $build_array['loctested'];
                    $coverage_groups[$groupId]['locuntested'] +=
                        $build_array['locuntested'];
                    if (count($subproject_groups) > 1) {
                        $coverage_groups[0]['loctested'] +=
                            $build_array['loctested'];
                        $coverage_groups[0]['locuntested'] +=
                            $build_array['locuntested'];
                    }
                }
            }

            $coverage_response['percentage'] = $percent;
            $coverage_response['locuntested'] = intval($build_array['locuntested']);
            $coverage_response['loctested'] = intval($build_array['loctested']);

            // Compute the diff
            if (!is_null($build_array['loctesteddiff']) || !is_null($build_array['locuntesteddiff'])) {
                $loctesteddiff = $build_array['loctesteddiff'];
                $locuntesteddiff = $build_array['locuntesteddiff'];
                $previouspercent =
                    round(($coverage_response['loctested'] - $loctesteddiff) /
                        ($coverage_response['loctested'] - $loctesteddiff +
                            $coverage_response['locuntested'] - $locuntesteddiff)
                        * 100, 2);
                $percentdiff = round($percent - $previouspercent, 2);
                $coverage_response['percentagediff'] = $percentdiff;
            }

            $coverage_response['label'] = get_build_label($buildid, $build_array);

            if ($coverageIsGrouped) {
                $coverage_groups[$groupId]['coverages'][] = $coverage_response;
            } else {
                $response['coverages'][] = $coverage_response;
            }
        } // end looping through builds

        // Generate coverage by group here.
        foreach ($coverage_groups as $groupid => $group) {
            $loctested = $group['loctested'];
            $locuntested = $group['locuntested'];
            if ($loctested == 0 && $locuntested == 0) {
                continue;
            }
            $percentage = round($loctested / ($loctested + $locuntested) * 100, 2);
            $group['percentage'] = $percentage;
            $group['id'] = $groupid;

            $response['coveragegroups'][] = $group;
        }

        return $response;
    }
}

if (!function_exists('CDash\Api\v1\CompareCoverage\get_build_data')) {
    function get_build_data($parentid, $projectid, $beginning_UTCDate, $end_UTCDate, $filter_sql='')
    {
        $date_clause = "AND b.starttime<'$end_UTCDate' AND b.starttime>='$beginning_UTCDate' ";
        $parent_clause = '';
        if (isset($parentid)) {
            // If we have a parentid, then we should only show children of that build.
            // Date becomes irrelevant in this case.
            $parent_clause = 'AND (b.parentid = ' . qnum($parentid) . ') ';
            $date_clause = '';
        } else {
            // Only show builds that are not children.
            $parent_clause = 'AND (b.parentid = -1 OR b.parentid = 0) ';
        }

        $sql = "SELECT b.id, b.parentid, b.name, sp.groupid AS subprojectgroup,
        (SELECT count(buildid) FROM label2build WHERE buildid=b.id) AS numlabels,
        cs.loctested, cs.locuntested,
        csd.loctested AS loctesteddiff, csd.locuntested AS locuntesteddiff
        FROM build AS b
        INNER JOIN build2group AS b2g ON (b2g.buildid=b.id)
        INNER JOIN buildgroup AS g ON (g.id=b2g.groupid)
        INNER JOIN coveragesummary AS cs ON (cs.buildid = b.id)
        LEFT JOIN coveragesummarydiff AS csd ON (csd.buildid = b.id)
        LEFT JOIN subproject2build AS sp2b ON (sp2b.buildid = b.id)
        LEFT JOIN subproject AS sp ON (sp2b.subprojectid = sp.id)
        WHERE b.projectid='$projectid' AND g.type='Daily' AND
        b.type='Nightly'
        $parent_clause $date_clause $filter_sql";
        $builds = pdo_query($sql);

        // Gather up results from this query.
        $build_data = array();
        while ($build_row = pdo_fetch_array($builds)) {
            $build_data[] = $build_row;
        }
        return $build_data;
    }
}


$pageTimer = new PageTimer();
$response = [];

// Check if a valid project was specified.
$projectname = $_GET['project'];
$projectname = htmlspecialchars(pdo_real_escape_string($projectname));
$projectid = get_project_id($projectname);
if ($projectid < 1) {
    $response['error'] =
        'This project does not exist. Maybe the URL you are trying to access is wrong.';
    echo json_encode($response);
    http_response_code(400);
    return;
}

if (!can_access_project($projectid)) {
    return;
}

$project_instance = new Project();
$project_instance->Id = $projectid;
$project_instance->Fill();

@$date = $_GET['date'];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

list($previousdate, $currentstarttime, $nextdate) = get_dates($date, $project_instance->NightlyTime);

$response = begin_JSON_response();
$response['title'] = 'CDash : Compare Coverage';
$response['showcalendar'] = 1;
get_dashboard_JSON($projectname, $date, $response);

$page_id = 'compareCoverage.php';

// Menu definition
$beginning_timestamp = $currentstarttime;
$end_timestamp = $currentstarttime + 3600 * 24;
$beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
$end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

// Menu
$menu = array();
$projectname_encoded = urlencode($projectname);
if ($date == '') {
    $back = "index.php?project=$projectname_encoded";
} else {
    $back = "index.php?project=$projectname_encoded&date=$date";
}
$menu['back'] = $back;
$menu['previous'] = "$page_id?project=$projectname_encoded&date=$previousdate";

$today = date(FMT_DATE, time());
$menu['current'] = "$page_id?project=$projectname_encoded&date=$today";

if (has_next_date($date, $currentstarttime)) {
    $menu['next'] = "$page_id?project=$projectname_encoded&date=$nextdate";
} else {
    $menu['next'] = false;
}
$response['menu'] = $menu;

// Filters
$filterdata = get_filterdata_from_request();
unset($filterdata['xml']);
$response['filterdata'] = $filterdata;
$filter_sql = $filterdata['sql'];
$response['filterurl'] = get_filterurl();

// Get the list of builds we're interested in.
$build_data = get_build_data(null, $projectid, $beginning_UTCDate,
    $end_UTCDate);
$response['builds'] = array();
$aggregate_build = array();
foreach ($build_data as $build_array) {
    $build = array();
    $build['name'] = $build_array['name'];
    $build['key'] = 'build' . $build_array['id'];
    $build['id'] = $build_array['id'];
    if ($build['name'] == 'Aggregate Coverage') {
        $aggregate_build = $build;
    } else {
        $response['builds'][] = $build;
    }
} // end looping through builds
// Add 'Aggregate' build last
$response['builds'][] = $aggregate_build;

$coverages = array(); // For un-grouped subprojects
$coveragegroups = array();  // For grouped subprojects

// Are there any subproject groups?
$subproject_groups = array();
if ($project_instance->GetNumberOfSubProjects($end_UTCDate) > 0) {
    $subproject_groups = $project_instance->GetSubProjectGroups();
}
foreach ($subproject_groups as $group) {
    // Keep track of coverage info on a per-group basis.
    $groupId = $group->GetId();

    $coveragegroups[$groupId] = array();
    $coverageThreshold = $group->GetCoverageThreshold();
    $coveragegroups[$groupId]['thresholdgreen'] = $coverageThreshold;
    $coveragegroups[$groupId]['thresholdyellow'] = $coverageThreshold * 0.7;

    $coveragegroups[$groupId]['coverages'] = array();

    foreach ($response['builds'] as $build) {
        $coveragegroups[$groupId][$build['key']] = -1;
    }
    $coveragegroups[$groupId]['label'] = $group->GetName();
    $coveragegroups[$groupId]['position'] = $group->GetPosition();
}
if (count($subproject_groups) > 1) {
    // Add group for Total coverage.
    $coveragegroups[0] = array();
    $coverageThreshold = $project_instance->CoverageThreshold;
    $coveragegroups[0]['thresholdgreen'] = $coverageThreshold;
    $coveragegroups[0]['thresholdyellow'] = $coverageThreshold * 0.7;
    foreach ($response['builds'] as $build) {
        $coveragegroups[0][$build['key']] = -1;
    }
    $coveragegroups[0]['label'] = 'Total';
    $coveragegroups[0]['position'] = 0;
}

// First, get the coverage data for the aggregate build.
$build_data = get_build_data($aggregate_build['id'], $projectid, $beginning_UTCDate, $end_UTCDate, $filter_sql);

$coverage_response = get_coverage($build_data, $subproject_groups);

// And make an entry in coverages for each possible subproject.

// Grouped subprojects
if (array_key_exists('coveragegroups', $coverage_response)) {
    foreach ($coverage_response['coveragegroups'] as $group) {
        $coveragegroups[$group['id']][$aggregate_build['key']] = $group['percentage'];
        $coveragegroups[$group['id']]['label'] = $group['label'];
        if ($group['id'] === 0) {
            // 'Total' group is just a summary, does not contain coverages.
            continue;
        }
        foreach ($group['coverages'] as $coverage) {
            $subproject = create_subproject($coverage, $response['builds']);
            $coveragegroups[$group['id']]['coverages'][] =
                populate_subproject($subproject, $aggregate_build['key'], $coverage);
        }
    }
}

// Un-grouped subprojects
if (array_key_exists('coverages', $coverage_response)) {
    foreach ($coverage_response['coverages'] as $coverage) {
        $subproject = create_subproject($coverage, $response['builds']);
        $coverages[] = populate_subproject($subproject, $aggregate_build['key'], $coverage);
    }
}

// Then loop through the other builds and fill in the subproject information
foreach ($response['builds'] as $build_response) {
    $buildid = $build_response['id'];
    if ($buildid == null || $buildid == $aggregate_build['id']) {
        continue;
    }

    $build_data = get_build_data($buildid, $projectid, $beginning_UTCDate, $end_UTCDate, $filter_sql);

    // Get the coverage data for each build.
    $coverage_response = get_coverage($build_data, $subproject_groups);

    // Grouped subprojects
    if (array_key_exists('coveragegroups', $coverage_response)) {
        foreach ($coverage_response['coveragegroups'] as $group) {
            $coveragegroups[$group['id']]['build' . $buildid] = $group['percentage'];
            $coveragegroups[$group['id']]['label'] = $group['label'];
            if ($group['id'] === 0) {
                // 'Total' group is just a summary, does not contain coverages.
                continue;
            }
            foreach ($group['coverages'] as $coverage) {
                // Find this subproject in the response
                foreach ($coveragegroups[$group['id']]['coverages'] as $key => $subproject_response) {
                    if ($subproject_response['label'] == $coverage['label']) {
                        $coveragegroups[$group['id']]['coverages'][$key] =
                            populate_subproject($coveragegroups[$group['id']]['coverages'][$key], 'build'.$buildid, $coverage);
                        break;
                    }
                }
            }
        }
    }

    // Un-grouped subprojects
    if (array_key_exists('coverages', $coverage_response)) {
        foreach ($coverage_response['coverages'] as $coverage) {
            // Find this subproject in the response
            foreach ($coverages as $key => $subproject_response) {
                if ($subproject_response['label'] == $coverage['label']) {
                    $coverages[$key] = populate_subproject($coverages[$key], 'build'.$buildid, $coverage);
                    break;
                }
            }
        }
    }
} // end loop through builds

if (!empty($subproject_groups)) {
    // At this point it is safe to remove any empty $coveragegroups from our response.
    $coveragegroups_response =
        array_filter($coveragegroups, function ($group) {
            return $group['label'] === 'Total' || !empty($group['coverages']);
        });

    // Report coveragegroups as a list, not an associative array.
    $coveragegroups_response = array_values($coveragegroups_response);

    $response['coveragegroups'] = $coveragegroups_response;
} else {
    $coverageThreshold = $project_instance->CoverageThreshold;
    $response['thresholdgreen'] = $coverageThreshold;
    $response['thresholdyellow'] = $coverageThreshold * 0.7;

    // Report coverages as a list, not an associative array.
    $response['coverages'] = array_values($coverages);
}

$pageTimer->end($response);
echo json_encode(cast_data_for_JSON($response));
