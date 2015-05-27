<?php
/*=========================================================================

  Copyright (c) Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/

$noforcelogin = 1;
include("cdash/config.php");
require_once("cdash/pdo.php");
include('login.php');
include_once("cdash/common.php");
include("cdash/version.php");
require_once("models/project.php");

// handle required project argument
@$projectname = $_GET["project"];
if(!isset($projectname))
  {
  echo "Not a valid project!";
  return;
  }

$start = microtime_float();

$projectname = htmlspecialchars(pdo_real_escape_string($projectname));
$projectid = get_project_id($projectname);
$Project = new Project();
$Project->Id = $projectid;
$Project->Fill();

// check if this project has subprojects.
$has_subprojects = ($Project->GetNumberOfSubProjects() > 0);

// make sure the user has access to this project
checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid);

// connect to the database
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

// handle optional date argument
@$date = $_GET["date"];
if ($date != NULL)
  {
  $date = htmlspecialchars(pdo_real_escape_string($date));
  }
list ($previousdate, $currentstarttime, $nextdate) = get_dates($date, $Project->NightlyTime);

// Date range is currently hardcoded to two weeks in the past.
// This could become a configurable value instead.
$date_range = 14;

// begin .xml that is used to render this page
$xml = begin_XML_for_XSLT();
$xml .= get_cdash_dashboard_xml($projectname,$date);
$projectname = get_project_name($projectid);
$xml .= "<title>CDash Overview : ".$projectname."</title>";

$xml .= get_cdash_dashboard_xml_by_name($projectname, $date);

$xml .= "<menu>";
$xml .= add_XML_value("previous", "overview.php?project=$projectname&date=$previousdate");
$xml .= add_XML_value("current", "overview.php?project=$projectname");
$xml .= add_XML_value("next", "overview.phpv?project=$projectname&date=$nextdate");
$xml .= "</menu>";
$xml .= add_XML_value("has_subprojects", $has_subprojects);

// configure/build/test data that we care about.
$build_measurements = array("configure_warnings", "configure_errors",
                      "build_warnings", "build_errors", "failing_tests");

// for static analysis, we only care about errors & warnings.
$static_measurements = array("errors", "warnings");

// information on how to sort by the various build measurements
$sort = array(
  "configure_warnings" => "[[5,1]]",
  "configure_errors"   => "[[4,1]]",
  "build_warnings"     => "[[8,1]]",
  "build_errors"       => "[[7,1]]",
  "failing_tests"      => "[[11,1]]");

// get the build groups that are included in this project's overview,
// split up by type (currently only static analysis and general builds).
$query =
  "SELECT bg.id, bg.name, obg.type FROM overview_components AS obg
   LEFT JOIN buildgroup AS bg ON (obg.buildgroupid = bg.id)
   WHERE obg.projectid = '$projectid' ORDER BY obg.position";
$group_rows = pdo_query($query);
add_last_sql_error("overview", $projectid);

$build_groups = array();
$static_groups = array();

while($group_row = pdo_fetch_array($group_rows))
  {
  if ($group_row["type"] == "build")
    {
    $build_groups[] = array('id' => $group_row["id"],
                            'name' => $group_row["name"]);
    }
  else if ($group_row["type"] == "static")
    {
    $static_groups[] = array('id' => $group_row["id"],
                             'name' => $group_row["name"]);
    }
  }

// Get default coverage threshold for this project.
$query = "SELECT coveragethreshold FROM project WHERE id='$projectid'";
$project = pdo_query($query);
add_last_sql_error("overview :: coveragethreshold", $projectid);
$project_array = pdo_fetch_array($project);

$has_subproject_groups = false;
$subproject_groups = array();
$coverage_group_names = array();
$coverage_thresholds = array();
if ($has_subprojects)
  {
  // Detect if the subprojects are split up into groups.
  $groups = $Project->GetSubProjectGroups();
  if (is_array($groups) && !empty($groups))
    {
    $has_subproject_groups = true;

    // Store subproject groups in an array keyed by id.
    // Also store the low, medium, satisfactory threshold values for this group.

    foreach($groups as $group)
      {
      $subproject_groups[$group->GetId()] = $group;
      $group_name = $group->GetName();
      $threshold = $group->GetCoverageThreshold();
      $coverage_group_names[] = $group_name;

      $coverage_thresholds[$group_name] = array();
      $coverage_thresholds[$group_name]["low"] = 0.7 * $threshold;
      $coverage_thresholds[$group_name]["medium"] = $threshold;
      $coverage_thresholds[$group_name]["satisfactory"] = 100;
      }
    }
  }

if (!$has_subproject_groups)
  {
  $coverage_group_names = array("coverage");
  $threshold = $project_array["coveragethreshold"];
  $coverage_thresholds["coverage"] = array();
  $coverage_thresholds["coverage"]["low"] = 0.7 * $threshold;
  $coverage_thresholds["coverage"]["medium"] = $threshold;
  $coverage_thresholds["coverage"]["satisfactory"] = 100;
  }

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

for ($i = 0; $i < $date_range; $i++)
  {
  $overview_data[$i] = array();
  foreach($build_groups as $build_group)
    {
    $build_group_name = $build_group["name"];

    // overview
    $overview_data[$i][$build_group_name] = array();

    // coverage
    foreach($coverage_group_names as $coverage_group_name)
      {
      $coverage_data[$i][$build_group_name][$coverage_group_name] = array();
      $coverage_array =
        &$coverage_data[$i][$build_group_name][$coverage_group_name];
      $coverage_array["loctested"] = 0;
      $coverage_array["locuntested"] = 0;
      $coverage_array["percent"] = 0;
      }

    // dynamic analysis
    $dynamic_analysis_data[$i][$build_group_name] = array();
    }

  // overview_data also needs to represent the static groups (if any)
  foreach($static_groups as $static_group)
    {
    $static_group_name = $static_group["name"];
    $overview_data[$i][$static_group_name] = array();
    }
  }

// Get the beginning and end of our relevant date rate.
$beginning_timestamp = $currentstarttime - (($date_range - 1) * 3600 * 24);
$end_timestamp = $currentstarttime + 3600 * 24;
$start_date = gmdate(FMT_DATETIME,$beginning_timestamp);
$end_date = gmdate(FMT_DATETIME,$end_timestamp);

// Perform a query to get info about all of our builds that fall within this
// time range.
$builds_query =
  "SELECT b.id,
   b.builderrors AS build_errors,
   b.buildwarnings AS build_warnings,
   b.testfailed AS failing_tests,
   b.configureerrors AS configure_errors,
   b.configurewarnings AS configure_warnings, b.starttime,
   cs.loctested AS loctested, cs.locuntested AS locuntested,
   b2g.groupid AS groupid
   FROM build AS b
   LEFT JOIN build2group AS b2g ON (b2g.buildid=b.id)
   LEFT JOIN coveragesummary AS cs ON (cs.buildid=b.id)
   WHERE b.projectid = '$projectid'
   AND b.starttime BETWEEN '$start_date' AND '$end_date'
   AND b.parentid IN (-1, 0)";

$builds_array = pdo_query($builds_query);
add_last_sql_error("gather_overview_data");

while($build_row = pdo_fetch_array($builds_array))
  {
  // get what day this build is for.
  $day = get_day_index($build_row["starttime"]);

  $static_name = get_static_group_name($build_row["groupid"]);
  // Special handling for static builds, as we don't need to record as
  // much data about them.
  if ($static_name)
    {
    foreach($static_measurements as $measurement)
      {
      if (!array_key_exists($measurement, $overview_data[$day][$static_name]))
        {
        $overview_data[$day][$static_name][$measurement] =
          intval($build_row["build_$measurement"]);
        }
      else
        {
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

  $group_name = get_build_group_name($build_row["groupid"]);

  // Skip this build if it's not from a group that is represented by
  // the overview dashboard.
  if (!$group_name)
    {
    continue;
    }

  // From here on out, we're dealing with "build" (not static) groups.
  foreach($build_measurements as $measurement)
    {
    if (!array_key_exists($measurement, $overview_data[$day][$group_name]))
      {
      $overview_data[$day][$group_name][$measurement] =
        intval($build_row[$measurement]);
      }
    else
      {
      $overview_data[$day][$group_name][$measurement] +=
        $build_row[$measurement];
      }
    // Don't let our measurements be thrown off by CDash's tendency
    // to store -1s in the database.
    $overview_data[$day][$group_name][$measurement] =
      max(0, $overview_data[$day][$group_name][$measurement]);
    }

  // Check if coverage was performed for this build.
  if ($build_row["loctested"] + $build_row["locuntested"] > 0)
    {
    if ($has_subproject_groups)
      {
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
        WHERE b.parentid=" . qnum($build_row["id"]);
      $child_builds_array = pdo_query($child_builds_query);
      add_last_sql_error("gather_overview_data");
      while($child_build_row = pdo_fetch_array($child_builds_array))
        {
        if ($build_row["loctested"] + $build_row["locuntested"] == 0)
          {
          continue;
          }

        // Record coverage for this subproject group.
        $subproject_group_id = $child_build_row["subprojectgroupid"];
        $subproject_group_name =
          $subproject_groups[$subproject_group_id]->GetName();
        $coverage_data[$day][$group_name][$subproject_group_name]["loctested"] +=
          $child_build_row["loctested"];
        $coverage_data[$day][$group_name][$subproject_group_name]["locuntested"] +=
          $child_build_row["locuntested"];
        }
      }
    else
      {
      $coverage_data[$day][$group_name]["coverage"]["loctested"] +=
        $build_row["loctested"];
      $coverage_data[$day][$group_name]["coverage"]["locuntested"] +=
        $build_row["locuntested"];
      }
    }
  }

// Compute coverage percentages here.
for ($i = 0; $i < $date_range; $i++)
  {
  foreach($build_groups as $build_group)
    {
    $build_group_name = $build_group["name"];
    foreach($coverage_group_names as $coverage_group_name)
      {
      $coverage_array =
        &$coverage_data[$i][$build_group_name][$coverage_group_name];
      $total_loc =
        $coverage_array["loctested"] + $coverage_array["locuntested"];
      if ($total_loc == 0)
        {
        continue;
        }
      $coverage_array["percent"] =
        round(($coverage_array["loctested"] / $total_loc) * 100, 2);
      }
    }
  }

// Dynamic analysis is handled here with a separate query.
$defects_query =
  "SELECT da.checker AS checker, dd.value AS defects,
          b2g.groupid AS groupid, b.starttime AS starttime
   FROM build AS b
   LEFT JOIN build2group AS b2g ON (b2g.buildid=b.id)
   LEFT JOIN dynamicanalysis as da ON (da.buildid = b.id)
   LEFT JOIN dynamicanalysisdefect as dd ON (dd.dynamicanalysisid=da.id)
   WHERE b.projectid = '$projectid'
   AND b.starttime BETWEEN '$start_date' AND '$end_date'
   AND checker IS NOT NULL";
$defects_array = pdo_query($defects_query);
add_last_sql_error("gather_dynamic_analysis_data");

// Keep track of the different types of dynamic analysis that are being
// performed on our build groups of interest.
$dynamic_analysis_types = array();

while($defect_row = pdo_fetch_array($defects_array))
  {
  $group_name = get_build_group_name($defect_row["groupid"]);
  // Is this a valid groupid?
  if (!$group_name)
    {
    continue;
    }

  // make sure this row has both checker & defect info for us
  if (!array_key_exists("checker", $defect_row) ||
      !array_key_exists("defects", $defect_row))
    {
    continue;
    }
  if (is_null($defect_row["defects"]))
    {
    $defect_row["defects"] = 0;
    }
  if (!is_numeric($defect_row["defects"]))
    {
    continue;
    }

  // Get the day that these results are for.
  $day = get_day_index($defect_row["starttime"]);

  // add this DA checker to our list if its the first time we've
  // encountered it.
  $checker = $defect_row["checker"];
  if (!in_array($checker, $dynamic_analysis_types))
    {
    $dynamic_analysis_types[] = $checker;
    }

  // Record this defect value for this checker & build group.
  $dynamic_analysis_array = &$dynamic_analysis_data[$day][$group_name];
  if (!array_key_exists($checker, $dynamic_analysis_array))
    {
    $dynamic_analysis_array[$checker] = $defect_row["defects"];
    }
  else
    {
    $dynamic_analysis_array[$checker] += $defect_row["defects"];
    }
  }

// Now that the data has been collected we can generate the XML.
// Start with the general build groups.
foreach($build_groups as $build_group)
  {
  $xml .= "<group>";
  $xml .= add_XML_value("name", $build_group["name"]);
  $xml .= "</group>";
  }
foreach($build_measurements as $measurement)
  {
  $xml .= "<measurement>";
  $xml .= add_XML_value("name", $measurement);
  $xml .= add_XML_value("nice_name", sanitize_string($measurement));
  $xml .= add_XML_value("sort", $sort[$measurement]);

  foreach($build_groups as $build_group)
    {
    $xml .= "<group>";
    $xml .= add_XML_value("group_name", $build_group["name"]);
    $xml .= add_XML_value("group_name_clean", sanitize_string($build_group["name"]));
    $value = get_current_value($build_group["name"], $measurement);
    $xml .= add_XML_value("value", $value);

    $chart_data = get_chart_data($build_group["name"], $measurement);
    $xml .= add_XML_value("chart", $chart_data);
    $xml .= "</group>";
    }
  $xml .= "</measurement>";
  }

// coverage
foreach($build_groups as $build_group)
  {
  foreach($coverage_group_names as $coverage_group_name)
    {
    // Skip groups that don't have any coverage.
    $found = false;
    for ($i = 0; $i < $date_range; $i++)
      {
      $cov = &$coverage_data[$i][$build_group["name"]][$coverage_group_name];
      $loc_total = $cov["loctested"] + $cov["locuntested"];
      if ($loc_total > 0)
        {
        $found = true;
        break;
        }
      }
    if (!$found)
      {
      continue;
      }

    $xml .= "<coverage>";
    $xml .= add_XML_value("name", preg_replace("/[ -]/", "_", $coverage_group_name));
    $xml .= add_XML_value("nice_name", "$coverage_group_name");
    $xml .= add_XML_value("group_name", $build_group["name"]);
    $xml .= add_XML_value("group_name_clean", sanitize_string($build_group["name"]));
    $xml .= add_XML_value("low",
      $coverage_thresholds[$coverage_group_name]["low"]);
    $xml .= add_XML_value("medium",
      $coverage_thresholds[$coverage_group_name]["medium"]);
    $xml .= add_XML_value("satisfactory",
      $coverage_thresholds[$coverage_group_name]["satisfactory"]);

    list($current_value, $previous_value) =
      get_recent_coverage_values($build_group["name"], $coverage_group_name);
    $xml .= add_XML_value("current", $current_value);
    $xml .= add_XML_value("previous", $previous_value);

    $chart_data =
      get_coverage_chart_data($build_group["name"], $coverage_group_name);
    $xml .= add_XML_value("chart", $chart_data);
    $xml .= "</coverage>";
    }
  }

// dynamic analysis
foreach($dynamic_analysis_types as $checker)
  {
  $xml .= "<dynamicanalysis>";
  $xml .= add_XML_value("name", preg_replace("/[ -]/", "_", $checker));
  $xml .= add_XML_value("nice_name", "$checker");

  foreach($build_groups as $build_group)
    {
    // Skip groups that don't have any data for this tool.
    $found = false;
    for ($i = 0; $i < $date_range; $i++)
      {
      if (array_key_exists($checker,
        $dynamic_analysis_data[$i][$build_group["name"]]))
        {
        $found = true;
        break;
        }
      }
    if (!$found)
      {
      continue;
      }

    $xml .= "<group>";
    $xml .= add_XML_value("group_name", $build_group["name"]);
    $xml .= add_XML_value("group_name_clean",
      sanitize_string($build_group["name"]));

    $chart_data = get_DA_chart_data($build_group["name"], $checker);
    $xml .= add_XML_value("chart", $chart_data);

    $value = get_current_DA_value($build_group["name"], $checker);
    $xml .= add_XML_value("value", $value);
    $xml .= "</group>";
    }
  $xml .= "</dynamicanalysis>";
  }

// static analysis
foreach($static_groups as $static_group)
  {
  // Skip this group if no data was found for it.
  $found = false;
  for ($i = 0; $i < $date_range; $i++)
    {
    $static_array = &$overview_data[$i][$static_group["name"]];
    foreach($static_measurements as $measurement)
      {
      if (array_key_exists($measurement, $static_array))
        {
        $found = true;
        break;
        }
      }
    if ($found)
      {
      break;
      }
    }
  if (!$found)
    {
    continue;
    }

  $xml .= "<staticanalysis>";
  $xml .= add_XML_value("group_name", $static_group["name"]);
  $xml .= add_XML_value("group_name_clean", sanitize_string($static_group["name"]));
  foreach($static_measurements as $measurement)
    {
    $xml .= "<measurement>";
    $xml .= add_XML_value("name", $measurement);
    $xml .= add_XML_value("nice_name", sanitize_string($measurement));
    $xml .= add_XML_value("sort", $sort["build_" . $measurement]);
    $value = get_current_value($static_group["name"], $measurement);
    $xml .= add_XML_value("value", $value);

    $chart_data = get_chart_data($static_group["name"], $measurement);
    $xml .= add_XML_value("chart", $chart_data);
    $xml .= "</measurement>";
    }
  $xml .= "</staticanalysis>";
  }

$end = microtime_float();
$xml .= "<generationtime>".round($end-$start,3)."</generationtime>";
$xml .= add_XML_value("hasSubProjects", $has_subprojects);
$xml .= "</cdash>";

// Now do the xslt transition
if(!isset($NoXSLGenerate))
  {
  generate_XSLT($xml, "overview");
  }


// Replace various characters that trip up Javascript with underscores.
function sanitize_string($input_string)
{
  $retval = str_replace(" ", "_", $input_string);
  $retval = str_replace("-", "_", $retval);
  $retval = str_replace(".", "_", $retval);
  $retval = str_replace("(", "_", $retval);
  $retval = str_replace(")", "_", $retval);
  return $retval;
}


// Check if a given groupid belongs to one of our general overview groups.
function get_build_group_name($id)
{
  global $build_groups;
  foreach ($build_groups as $build_group)
    {
    if($build_group["id"] == $id)
      {
      return $build_group["name"];
      }
    }
  return false;
}


// Check if a given groupid belongs to one of our static analysis groups.
function get_static_group_name($id)
{
  global $static_groups;
  foreach ($static_groups as $static_group)
    {
    if($static_group["id"] == $id)
      {
      return $static_group["name"];
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
  if ($day < 0)
    {
    $day = 0;
    }
  else if ($day > $date_range - 1)
    {
    $day = $date_range - 1;
    }

  return $day;
}


// Get most recent value for a given group & measurement.
function get_current_value($group_name, $measurement)
{
  global $date_range, $overview_data;
  for ($i = $date_range - 1; $i > -1; $i--)
    {
    if (array_key_exists($measurement, $overview_data[$i][$group_name]))
      {
      return $overview_data[$i][$group_name][$measurement];
      }
    }
  return false;
}


// Get most recent dynamic analysis value for a given group & checker.
function get_current_DA_value($group_name, $checker)
{
  global $date_range, $dynamic_analysis_data;
  for ($i = $date_range - 1; $i > -1; $i--)
    {
    if (array_key_exists($checker, $dynamic_analysis_data[$i][$group_name]))
      {
      return $dynamic_analysis_data[$i][$group_name][$checker];
      }
    }
  return "N/A";
}


// Get a Javascript-compatible date representing the $ith date of our
// time range.
function get_date_from_index($i)
{
  global $beginning_timestamp;
  $chart_beginning_timestamp = $beginning_timestamp + ($i * 3600 * 24);
  $chart_end_timestamp = $beginning_timestamp + (($i + 1) * 3600 * 24);
  // to be passed on to javascript chart renderers
  $chart_date = gmdate("M d Y H:i:s",
    ($chart_end_timestamp + $chart_beginning_timestamp) / 2.0);
  return $chart_date;
}


// Get line chart data for configure/build/test metrics.
function get_chart_data($group_name, $measurement)
{
  global $date_range, $overview_data;
  $chart_data = array();

  for ($i = 0; $i < $date_range; $i++)
    {
    if (!array_key_exists($measurement, $overview_data[$i][$group_name]))
      {
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
function get_coverage_chart_data($build_group_name, $coverage_group_name)
{
  global $date_range, $coverage_data;
  $chart_data = array();

  for ($i = 0; $i < $date_range; $i++)
    {
    $coverage_array =
      &$coverage_data[$i][$build_group_name][$coverage_group_name];
    $total_loc =
      $coverage_array["loctested"] + $coverage_array["locuntested"];
    if ($total_loc == 0)
      {
      continue;
      }

    $chart_date = get_date_from_index($i);
    $chart_data[] = array($chart_date, $coverage_array["percent"]);
    }
  return json_encode($chart_data);
}


// Get the current & previous coverage percentage value.
// These are used by the bullet chart.
function get_recent_coverage_values($build_group_name, $coverage_group_name)
{
  global $date_range, $coverage_data;
  $current_value_found = false;
  $current_value = 0;

  for ($i = $date_range - 1; $i > -1; $i--)
    {
    $coverage_array =
      &$coverage_data[$i][$build_group_name][$coverage_group_name];
    $total_loc =
      $coverage_array["loctested"] + $coverage_array["locuntested"];
    if ($total_loc == 0)
      {
      continue;
      }
    if (!$current_value_found)
      {
      $current_value = $coverage_array["percent"];
      $current_value_found = true;
      }
    else
      {
      $previous_value = $coverage_array["percent"];
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

  for ($i = 0; $i < $date_range; $i++)
    {
    $dynamic_analysis_array = &$dynamic_analysis_data[$i][$group_name];
    if (!array_key_exists($checker, $dynamic_analysis_array))
      {
      continue;
      }

    $chart_date = get_date_from_index($i);
    $chart_data[] =
      array($chart_date, $dynamic_analysis_data[$i][$group_name][$checker]);
    }
  return json_encode($chart_data);
}

?>
