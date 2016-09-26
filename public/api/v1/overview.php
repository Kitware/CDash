<?php
/*=========================================================================
  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
  =========================================================================*/

$noforcelogin = 1;
include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
include 'public/login.php';
include_once 'include/common.php';
include 'include/version.php';
require_once 'models/project.php';
require_once 'include/memcache_functions.php';

// handle required project argument
@$projectname = $_GET['project'];
if (!isset($projectname)) {
    echo 'Not a valid project!';
    return;
}

$start = microtime_float();

// Connect to the database.
$response = array();
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
if (!$db) {
    $response['error'] = 'Error connecting to CDash database server';
    echo json_encode($response);
    return;
}
if (!pdo_select_db("$CDASH_DB_NAME", $db)) {
    $response['error'] = 'Error selecting CDash database';
    echo json_encode($response);
    return;
}

// Connect to memcache
if ($CDASH_MEMCACHE_ENABLED) {
    list($server, $port) = $CDASH_MEMCACHE_SERVER;
    $memcache = cdash_memcache_connect($server, $port);

    // Disable memcache for this request if it fails to connect
    if ($memcache === false) {
        $CDASH_MEMCACHE_ENABLED = false;
    }
}

$projectname = htmlspecialchars(pdo_real_escape_string($projectname));
$projectid = get_project_id($projectname);
$Project = new Project();
$Project->Id = $projectid;
$Project->Fill();

// Make sure the user has access to this project
if (!checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid, 1)) {
    $response['requirelogin'] = 1;
    echo json_encode($response);
    return;
}

// Check if this project has SubProjects.
$has_subprojects = ($Project->GetNumberOfSubProjects() > 0);

// Handle optional date argument.
@$date = $_GET['date'];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
} else {
    $date = date(FMT_DATE);
}
list($previousdate, $currentstarttime, $nextdate) = get_dates($date, $Project->NightlyTime);

// Date range is currently hardcoded to two weeks in the past.
// This could become a configurable value instead.
$date_range = 14;

// Use cache if it's enabled, use_cache isn't set to 0, and an entry exists in Memcache
// Using cache is implied, but the user can set use_cache to 0 to explicitly disable it
// (This is a good method of ensuring the cache for this page stays up)
if ($CDASH_MEMCACHE_ENABLED &&
    !(isset($_GET['use_cache']) && $_GET['use_cache'] == 0) &&
    ($cachedResponse = cdash_memcache_get($memcache, cdash_memcache_key('overview'))) !== false) {
    echo $cachedResponse;
    return;
}

// begin JSON response that is used to render this page
$response = begin_JSON_response();
get_dashboard_JSON_by_name($projectname, $date, $response);
$response['title'] = "CDash Overview : $projectname";

$menu['previous'] = "overview.php?project=$projectname&date=$previousdate";
$menu['current'] = "overview.php?project=$projectname";
$menu['next'] = "overview.php?project=$projectname&date=$nextdate";
$response['menu'] = $menu;
$response['hasSubProjects'] = $has_subprojects;

// configure/build/test data that we care about.
$build_measurements = array('configure warnings', 'configure errors',
    'build warnings', 'build errors', 'failing tests');

// sanitized versions of these measurements.
$clean_measurements = array(
    'configure warnings' => 'configure_warnings',
    'configure errors'   => 'configure_errors',
    'build warnings'     => 'build_warnings',
    'build errors'       => 'build_errors',
    'failing tests'      => 'failing_tests');

// for static analysis, we only care about errors & warnings.
$static_measurements = array('errors', 'warnings');

// information on how to sort by the various build measurements
$sort = array(
    'configure warnings' => '-configure.warning',
    'configure errors'   => '-configure.error',
    'build warnings'     => '-compilation.warning',
    'build errors'       => '-compilation.error',
    'failing tests'      => '-test.fail');

// get the build groups that are included in this project's overview,
// split up by type (currently only static analysis and general builds).
$query =
    "SELECT bg.id, bg.name, obg.type FROM overview_components AS obg
    LEFT JOIN buildgroup AS bg ON (obg.buildgroupid = bg.id)
    WHERE obg.projectid = '$projectid' ORDER BY obg.position";
$group_rows = pdo_query($query);
add_last_sql_error('overview', $projectid);

$build_groups = array();
$static_groups = array();

while ($group_row = pdo_fetch_array($group_rows)) {
    if ($group_row['type'] == 'build') {
        $build_groups[] = array('id' => $group_row['id'],
            'name' => $group_row['name']);
    } elseif ($group_row['type'] == 'static') {
        $static_groups[] = array('id' => $group_row['id'],
            'name' => $group_row['name']);
    }
}

// Get default coverage threshold for this project.
$query = "SELECT coveragethreshold FROM project WHERE id='$projectid'";
$project = pdo_query($query);
add_last_sql_error('overview :: coveragethreshold', $projectid);
$project_array = pdo_fetch_array($project);

$has_subproject_groups = false;
$subproject_groups = array();
$coverage_categories = array();
$coverage_build_group_names = array();
if ($has_subprojects) {
    // Detect if the subprojects are split up into groups.
    $groups = $Project->GetSubProjectGroups();
    if (is_array($groups) && !empty($groups)) {
        $has_subproject_groups = true;
        foreach ($groups as $group) {
            // Store subproject groups in an array keyed by id.
            $subproject_groups[$group->GetId()] = $group;

            // Also store the low, medium, satisfactory threshold values
            // for this group.
            $group_name = $group->GetName();
            $threshold = $group->GetCoverageThreshold();
            $coverage_category = array();
            $coverage_category['name'] = $group_name;
            $coverage_category['position'] = $group->GetPosition();
            $coverage_category['low'] = 0.7 * $threshold;
            $coverage_category['medium'] = $threshold;
            $coverage_category['satisfactory'] = 100;
            $coverage_categories[] = $coverage_category;
        }
        // Also save a 'Total' category to summarize across groups.
        $coverage_category = array();
        $coverage_category['name'] = 'Total';
        $coverage_category['position'] = 0;
        $threshold = $project_array['coveragethreshold'];
        $coverage_category['low'] = 0.7 * $threshold;
        $coverage_category['medium'] = $threshold;
        $coverage_category['satisfactory'] = 100;
        $coverage_categories[] = $coverage_category;
    }
}

$threshold = $project_array['coveragethreshold'];
if (!$has_subproject_groups) {
    $coverage_category = array();
    $coverage_category['name']  = 'coverage';
    $coverage_category['position']  = 1;
    $coverage_category['low'] = 0.7 * $threshold;
    $coverage_category['medium'] = $threshold;
    $coverage_category['satisfactory'] = 100;
    $coverage_categories[] = $coverage_category;
}

foreach ($build_groups as $build_group) {
    $coverage_build_group_names[] = $build_group['name'];
}
$coverage_build_group_names[] = 'Aggregate';

// Initialize our storage data structures.
//
// overview_data holds most of the information about our builds.  It is a
// multi-dimensional array with the following structure:
//
//   overview_data[day][build group][measurement]
//
// Coverage and dynamic analysis are a bit more complicated, so we
// store their results separately.  Here is how their data structures
// are defined:
//   coverage_data[day][build group][coverage group] = percent_coverage
//   dynamic_analysis_data[day][group][checker] = num_defects

$overview_data = array();
$coverage_data = array();
$dynamic_analysis_data = array();

for ($i = 0; $i < $date_range; $i++) {
    $overview_data[$i] = array();
    foreach ($build_groups as $build_group) {
        $build_group_name = $build_group['name'];

        // overview
        $overview_data[$i][$build_group_name] = array();

        // dynamic analysis
        $dynamic_analysis_data[$i][$build_group_name] = array();
    }

    // coverage
    foreach ($coverage_build_group_names as $build_group_name) {
        foreach ($coverage_categories as $coverage_category) {
            $category_name = $coverage_category['name'];
            $coverage_data[$i][$build_group_name][$category_name] = array();
            $coverage_array =
                &$coverage_data[$i][$build_group_name][$category_name];
            $coverage_array['loctested'] = 0;
            $coverage_array['locuntested'] = 0;
            $coverage_array['percent'] = 0;
        }
    }

    // static analysis
    foreach ($static_groups as $static_group) {
        $static_group_name = $static_group['name'];
        $overview_data[$i][$static_group_name] = array();
    }
}

// Get the beginning and end of our relevant date rate.
$beginning_timestamp = $currentstarttime - (($date_range - 1) * 3600 * 24);
$end_timestamp = $currentstarttime + 3600 * 24;
$start_date = gmdate(FMT_DATETIME, $beginning_timestamp);
$end_date = gmdate(FMT_DATETIME, $end_timestamp);

// Perform a query to get info about all of our builds that fall within this
// time range.
$builds_query =
    "SELECT b.id, b.type, b.name,
    b.builderrors AS build_errors,
    b.buildwarnings AS build_warnings,
    b.testfailed AS failing_tests,
    b.configureerrors AS configure_errors,
    b.configurewarnings AS configure_warnings, b.starttime,
    cs.loctested AS loctested, cs.locuntested AS locuntested,
    das.checker AS checker, das.numdefects AS numdefects,
    b2g.groupid AS groupid
    FROM build AS b
    LEFT JOIN build2group AS b2g ON (b2g.buildid=b.id)
    LEFT JOIN coveragesummary AS cs ON (cs.buildid=b.id)
    LEFT JOIN dynamicanalysissummary AS das ON (das.buildid=b.id)
    WHERE b.projectid = '$projectid'
    AND b.starttime BETWEEN '$start_date' AND '$end_date'
    AND b.parentid IN (-1, 0)";

$builds_array = pdo_query($builds_query);
add_last_sql_error('gather_overview_data');

// If we have multiple coverage builds in a single day we will also
// show the aggregate.
$aggregate_tracker = array();
$show_aggregate = false;

// Keep track of the different types of dynamic analysis that are being
// performed on our build groups of interest.
$dynamic_analysis_types = array();

while ($build_row = pdo_fetch_array($builds_array)) {
    // get what day this build is for.
    $day = get_day_index($build_row['starttime']);

    $static_name = get_static_group_name($build_row['groupid']);
    // Special handling for static builds, as we don't need to record as
    // much data about them.
    if ($static_name) {
        foreach ($static_measurements as $measurement) {
            if (!array_key_exists($measurement, $overview_data[$day][$static_name])) {
                $overview_data[$day][$static_name][$measurement] =
                    intval($build_row["build_$measurement"]);
            } else {
                $overview_data[$day][$static_name][$measurement] +=
                    $build_row["build_$measurement"];
            }
            // Don't let our measurements be thrown off by CDash's tendency
            // to store -1s in the database.
            $overview_data[$day][$static_name][$measurement] =
                max(0, $overview_data[$day][$static_name][$measurement]);
        }
        continue;
    }

    if ($build_row['name'] == 'Aggregate Coverage') {
        $group_name = 'Aggregate';
    } else {
        $group_name = get_build_group_name($build_row['groupid']);
    }

    // Skip this build if it's not from a group that is represented by
    // the overview dashboard.
    if (!$group_name) {
        continue;
    }

    if ($group_name !== 'Aggregate') {
        // From here on out, we're dealing with "build" (not static) groups.
        foreach ($build_measurements as $measurement) {
            $clean_measurement = $clean_measurements[$measurement];
            if (!array_key_exists($measurement,
                    $overview_data[$day][$group_name])) {
                $overview_data[$day][$group_name][$measurement] =
                    intval($build_row[$clean_measurement]);
            } else {
                $overview_data[$day][$group_name][$measurement] +=
                    $build_row[$clean_measurement];
            }
            // Don't let our measurements be thrown off by CDash's tendency
            // to store -1s in the database.
            $overview_data[$day][$group_name][$measurement] =
                max(0, $overview_data[$day][$group_name][$measurement]);
        }
    }

    // Check if coverage was performed for this build.
    if ($build_row['loctested'] + $build_row['locuntested'] > 0) {

        // Check for multiple nightly coverage builds in a single day.
        if ($group_name !== 'Aggregate' && $build_row['type'] === 'Nightly') {
            if (array_key_exists($day, $aggregate_tracker)) {
                $show_aggregate = true;
            } else {
                $aggregate_tracker[$day] = true;
            }
        }

        if ($has_subproject_groups) {
            // Add this coverage to the Total group.
            $coverage_data[$day][$group_name]['Total']['loctested'] +=
                $build_row['loctested'];
            $coverage_data[$day][$group_name]['Total']['locuntested'] +=
                $build_row['locuntested'];

            // We need to query the children of this build to split up
            // coverage into subproject groups.
            $child_builds_query =
                "SELECT b.id,
                cs.loctested AS loctested, cs.locuntested AS locuntested,
                sp.id AS subprojectid, sp.groupid AS subprojectgroupid
                FROM build AS b
                LEFT JOIN coveragesummary AS cs ON (cs.buildid=b.id)
                LEFT JOIN subproject2build AS sp2b ON (sp2b.buildid = b.id)
                LEFT JOIN subproject as sp ON (sp2b.subprojectid = sp.id)
                WHERE b.parentid=" . qnum($build_row['id']);
            $child_builds_array = pdo_query($child_builds_query);
            add_last_sql_error('gather_overview_data');
            while ($child_build_row = pdo_fetch_array($child_builds_array)) {
                $loctested = $child_build_row['loctested'];
                $locuntested = $child_build_row['locuntested'];
                if ($loctested + $locuntested == 0) {
                    continue;
                }

                $subproject_group_id = $child_build_row['subprojectgroupid'];
                if (is_null($subproject_group_id)) {
                    continue;
                }

                // Record coverage for this subproject group.
                $subproject_group_name =
                    $subproject_groups[$subproject_group_id]->GetName();
                $coverage_data[$day][$group_name][$subproject_group_name]['loctested'] +=
                    $loctested;
                $coverage_data[$day][$group_name][$subproject_group_name]['locuntested'] +=
                    $locuntested;
            }
        } else {
            $coverage_data[$day][$group_name]['coverage']['loctested'] +=
                $build_row['loctested'];
            $coverage_data[$day][$group_name]['coverage']['locuntested'] +=
                $build_row['locuntested'];
        }
    }

    // Check if this build performed dynamic analysis.
    if (!empty($build_row['checker'])) {
        // Add this checker to our list if this is the first time we've
        // encountered it.
        $checker = $build_row['checker'];
        if (!in_array($checker, $dynamic_analysis_types)) {
            $dynamic_analysis_types[] = $checker;
        }

        // Record the number of defects for this day / checker / build group.
        $dynamic_analysis_array = &$dynamic_analysis_data[$day][$group_name];
        if (!array_key_exists($checker, $dynamic_analysis_array)) {
            $dynamic_analysis_array[$checker] = intval($build_row['numdefects']);
        } else {
            $dynamic_analysis_array[$checker] += intval($build_row['numdefects']);
        }
    }
}

if (!$show_aggregate) {
    // Remove the aggregate from our coverage data.
    $key = array_search('Aggregate', $coverage_build_group_names);
    if ($key !== false) {
        unset($coverage_build_group_names[$key]);
    }
    for ($day = 0; $day < $date_range; $day++) {
        if (array_key_exists('Aggregate', $coverage_data[$day])) {
            unset($coverage_data[$day]['Aggregate']);
        }
    }
}

// Compute coverage percentages here.
for ($i = 0; $i < $date_range; $i++) {
    foreach ($coverage_data[$i] as $build_group_name => &$build_group_data) {
        foreach ($build_group_data as $coverage_category => &$coverage_array) {
            $total_loc =
                $coverage_array['loctested'] + $coverage_array['locuntested'];
            if ($total_loc == 0) {
                continue;
            }
            $coverage_array['percent'] =
                round(($coverage_array['loctested'] / $total_loc) * 100, 2);
        }
    }
}

// Now that the data has been collected we can generate the XML.
// Start with the general build groups.
$groups = array();
foreach ($build_groups as $build_group) {
    $groups[] = array('name' => $build_group['name']);
}
$response['groups'] = $groups;

$measurements_response = array();
foreach ($build_measurements as $measurement) {
    $clean_measurement = $clean_measurements[$measurement];
    $measurement_response = array();
    $measurement_response['name'] = $measurement;
    $measurement_response['name_clean'] = $clean_measurement;
    $measurement_response['sort'] = $sort[$measurement];

    $groups_response = array();
    foreach ($build_groups as $build_group) {
        $group_response = array();
        $group_response['name'] = $build_group['name'];
        $group_response['name_clean'] =
            sanitize_string($build_group['name']);
        $value = get_current_value($build_group['name'], $measurement);
        $group_response['value'] = $value;

        $chart_data = get_chart_data($build_group['name'], $measurement);
        $group_response['chart'] = $chart_data;
        $groups_response[] = $group_response;
    }
    $measurement_response['groups'] = $groups_response;
    $measurements_response[] = $measurement_response;
}
$response['measurements'] = $measurements_response;

// coverage
$coverages_response = array();
$coverage_buildgroups = array();

foreach ($coverage_categories as $coverage_category) {
    $category_name = $coverage_category['name'];
    $coverage_category_response = array();
    $coverage_category_response['name_clean'] = sanitize_string($category_name);
    $coverage_category_response['name'] = $category_name;
    $coverage_category_response['position'] = $coverage_category['position'];
    $coverage_category_response['groups'] = array();

    foreach ($coverage_build_group_names as $build_group_name) {
        // Skip groups that don't have any coverage.
        $found = false;
        for ($i = 0; $i < $date_range; $i++) {
            $cov = &$coverage_data[$i][$build_group_name][$category_name];
            $loc_total = $cov['loctested'] + $cov['locuntested'];
            if ($loc_total > 0) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            continue;
        }

        $coverage_response = array();

        $coverage_response['name'] = $build_group_name;
        if (!in_array($build_group_name, $coverage_buildgroups)) {
            $coverage_buildgroups[] = $build_group_name;
        }
        $coverage_response['name_clean'] =
            sanitize_string($build_group_name);
        $coverage_response['low'] = $coverage_category['low'];
        $coverage_response['medium'] = $coverage_category['medium'];
        $coverage_response['satisfactory'] = $coverage_category['satisfactory'];

        list($current_value, $previous_value) =
            get_recent_coverage_values($build_group_name, $category_name);
        $coverage_response['current'] = $current_value;
        $coverage_response['previous'] = $previous_value;

        $chart_data =
            get_coverage_chart_data($build_group_name, $category_name);
        $coverage_response['chart'] = $chart_data;
        $coverage_category_response['groups'][] = $coverage_response;
    }

    if (!empty($coverage_category_response['groups'])) {
        $coverages_response[] = $coverage_category_response;
    }
}

$response['coverages'] = $coverages_response;
$response['coverage_buildgroups'] = $coverage_buildgroups;

// dynamic analysis
$dynamic_analyses_response = array();
foreach ($dynamic_analysis_types as $checker) {
    $DA_response = array();
    $DA_response['name_clean'] = sanitize_string($checker);
    $DA_response['name'] = $checker;

    $groups_response = array();
    foreach ($build_groups as $build_group) {
        // Skip groups that don't have any data for this tool.
        $found = false;
        for ($i = 0; $i < $date_range; $i++) {
            if (array_key_exists($checker,
                $dynamic_analysis_data[$i][$build_group['name']])) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            continue;
        }

        $group_response = array();
        $group_response['name'] = $build_group['name'];
        $group_response['name_clean'] =
            sanitize_string($build_group['name']);

        $chart_data = get_DA_chart_data($build_group['name'], $checker);
        $group_response['chart'] = $chart_data;

        $value = get_current_DA_value($build_group['name'], $checker);
        $group_response['value'] = $value;
        $groups_response[] = $group_response;
    }
    $DA_response['groups'] = $groups_response;
    $dynamic_analyses_response[] = $DA_response;
}
$response['dynamicanalyses'] = $dynamic_analyses_response;

// static analysis
$static_analyses_response = array();
foreach ($static_groups as $static_group) {
    // Skip this group if no data was found for it.
    $found = false;
    for ($i = 0; $i < $date_range; $i++) {
        $static_array = &$overview_data[$i][$static_group['name']];
        foreach ($static_measurements as $measurement) {
            if (array_key_exists($measurement, $static_array)) {
                $found = true;
                break;
            }
        }
        if ($found) {
            break;
        }
    }
    if (!$found) {
        continue;
    }

    $SA_response = array();
    $SA_response['group_name'] = $static_group['name'];
    $SA_response['group_name_clean'] = sanitize_string($static_group['name']);
    $measurements_response = array();
    foreach ($static_measurements as $measurement) {
        $measurement_response = array();
        $measurement_response['name'] = $measurement;
        $measurement_response['name_clean'] = sanitize_string($measurement);
        $measurement_response['sort'] = $sort["build $measurement"];
        $value = get_current_value($static_group['name'], $measurement);
        $measurement_response['value'] = $value;

        $chart_data = get_chart_data($static_group['name'], $measurement);
        $measurement_response['chart'] = $chart_data;
        $measurements_response[] = $measurement_response;
    }
    $SA_response['measurements'] = $measurements_response;
    $static_analyses_response[] = $SA_response;
}
$response['staticanalyses'] = $static_analyses_response;

$end = microtime_float();
$response['generationtime'] = round($end - $start, 3);
$response = json_encode(cast_data_for_JSON($response));

// Cache the overview page for 6 hours
if ($CDASH_MEMCACHE_ENABLED) {
    cdash_memcache_set($memcache, cdash_memcache_key('overview'), $response, 60 * 60 * 6);
}

echo $response;

// Replace all non-word characters with underscores.
function sanitize_string($input_string)
{
    return preg_replace('/\W/', '_', $input_string);
}

// Check if a given groupid belongs to one of our general overview groups.
function get_build_group_name($id)
{
    global $build_groups;
    foreach ($build_groups as $build_group) {
        if ($build_group['id'] == $id) {
            return $build_group['name'];
        }
    }
    return false;
}

// Check if a given groupid belongs to one of our static analysis groups.
function get_static_group_name($id)
{
    global $static_groups;
    foreach ($static_groups as $static_group) {
        if ($static_group['id'] == $id) {
            return $static_group['name'];
        }
    }
    return false;
}

// Convert a MySQL datetime into the number of days since the beginning of our
// time range.
function get_day_index($datetime)
{
    global $beginning_timestamp, $date_range;
    $timestamp = strtotime($datetime) - $beginning_timestamp;
    $day = (int) ($timestamp / (3600 * 24));

    // Just to be safe, clamp the return value of this function to
    // (0, date_range - 1)
    if ($day < 0) {
        $day = 0;
    } elseif ($day > $date_range - 1) {
        $day = $date_range - 1;
    }

    return $day;
}

// Get most recent value for a given group & measurement.
function get_current_value($group_name, $measurement)
{
    global $date_range, $overview_data;
    for ($i = $date_range - 1; $i > -1; $i--) {
        if (array_key_exists($measurement, $overview_data[$i][$group_name])) {
            return $overview_data[$i][$group_name][$measurement];
        }
    }
    return false;
}

// Get most recent dynamic analysis value for a given group & checker.
function get_current_DA_value($group_name, $checker)
{
    global $date_range, $dynamic_analysis_data;
    for ($i = $date_range - 1; $i > -1; $i--) {
        if (array_key_exists($checker, $dynamic_analysis_data[$i][$group_name])) {
            return $dynamic_analysis_data[$i][$group_name][$checker];
        }
    }
    return 'N/A';
}

// Get a Javascript-compatible date representing the $ith date of our
// time range.
function get_date_from_index($i)
{
    global $beginning_timestamp;
    $chart_beginning_timestamp = $beginning_timestamp + ($i * 3600 * 24);
    $chart_end_timestamp = $beginning_timestamp + (($i + 1) * 3600 * 24);
    // to be passed on to javascript chart renderers
    $chart_date = gmdate('M d Y H:i:s',
        ($chart_end_timestamp + $chart_beginning_timestamp) / 2.0);
    return $chart_date;
}

// Get line chart data for configure/build/test metrics.
function get_chart_data($group_name, $measurement)
{
    global $date_range, $overview_data;
    $chart_data = array();

    for ($i = 0; $i < $date_range; $i++) {
        if (!array_key_exists($measurement, $overview_data[$i][$group_name])) {
            continue;
        }
        $chart_date = get_date_from_index($i);
        $chart_data[] =
            array($chart_date, $overview_data[$i][$group_name][$measurement]);
    }

    // JSON encode the chart data to make it easier to use on the client side.
    return json_encode($chart_data);
}

// Get line chart data for coverage
function get_coverage_chart_data($build_group_name, $coverage_category)
{
    global $date_range, $coverage_data;
    $chart_data = array();

    for ($i = 0; $i < $date_range; $i++) {
        $coverage_array =
            &$coverage_data[$i][$build_group_name][$coverage_category];
        $total_loc =
            $coverage_array['loctested'] + $coverage_array['locuntested'];
        if ($total_loc == 0) {
            continue;
        }

        $chart_date = get_date_from_index($i);
        $chart_data[] = array($chart_date, $coverage_array['percent']);
    }
    return json_encode($chart_data);
}

// Get the current & previous coverage percentage value.
// These are used by the bullet chart.
function get_recent_coverage_values($build_group_name, $coverage_category)
{
    global $date_range, $coverage_data;
    $current_value_found = false;
    $current_value = 0;

    for ($i = $date_range - 1; $i > -1; $i--) {
        $coverage_array =
            &$coverage_data[$i][$build_group_name][$coverage_category];
        $total_loc =
            $coverage_array['loctested'] + $coverage_array['locuntested'];
        if ($total_loc == 0) {
            continue;
        }
        if (!$current_value_found) {
            $current_value = $coverage_array['percent'];
            $current_value_found = true;
        } else {
            $previous_value = $coverage_array['percent'];
            return array($current_value, $previous_value);
        }
    }

    // Reaching this line implies that we only found a single day's worth
    // of coverage for these groups.  In this case, we make previous & current
    // hold the same value.  We do this because nvd3's bullet chart implementation
    // does not support leaving the "marker" off of the chart.
    return array($current_value, $current_value);
}

// Get line chart data for dynamic analysis
function get_DA_chart_data($group_name, $checker)
{
    global $date_range, $dynamic_analysis_data;
    $chart_data = array();

    for ($i = 0; $i < $date_range; $i++) {
        $dynamic_analysis_array = &$dynamic_analysis_data[$i][$group_name];
        if (!array_key_exists($checker, $dynamic_analysis_array)) {
            continue;
        }

        $chart_date = get_date_from_index($i);
        $chart_data[] =
            array($chart_date, $dynamic_analysis_data[$i][$group_name][$checker]);
    }
    return json_encode($chart_data);
}
