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
$hasSubprojects = ($Project->GetNumberOfSubProjects() > 0);

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
$xml .= add_XML_value("hasSubprojects", $hasSubprojects);

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

// initialize our storage data structures here.
//
// overview_data generally contains all the relevant numbers
// pertaining to the most recent day's builds.
//
// linechart_data, on the other hand, contains data points going
// back two weeks.  As the name suggests, this data is used
// as the input to the line charts on this page.
$overview_data = array();
foreach($build_measurements as $measurement)
  {
  $overview_data[$measurement] = array();
  $linechart_data[$measurement] = array();
  foreach($build_groups as $build_group)
    {
    $linechart_data[$measurement][$build_group["name"]] = array();
    }
  }

// How we handle coverage_data will need to change if we abstract way
// core vs. non-core into arbitrary subproject groups.
$coverage_data = array();

// Get core & non-core coverage thresholds
$query = "SELECT coveragethreshold, coveragethreshold2 FROM project
          WHERE id='$projectid'";
$project = pdo_query($query);
add_last_sql_error("overview :: coveragethreshold", $projectid);
$project_array = pdo_fetch_array($project);

// Detect if this project has any non-core subprojects
$haveNonCore = false;
$query = "SELECT * FROM subproject WHERE projectid='$projectid' AND core != 1";
if (pdo_num_rows(pdo_query($query)) > 0)
  {
  $haveNonCore = true;
  $coverage_group_names = array("core coverage", "non-core coverage");
  $coverage_thresholds =
    array("core coverage"     => $project_array["coveragethreshold"],
          "non-core coverage" => $project_array["coveragethreshold2"]);
  }
else
  {
  $coverage_group_names = array("coverage");
  $coverage_thresholds =
    array("coverage" => $project_array["coveragethreshold"]);
  }
add_last_sql_error("overview :: detect-non-core", $projectid);

// initialize storage for coverage data
foreach($build_groups as $build_group)
  {
    $overview_data[$build_group["name"]] = array();
    $linechart_data[$build_group["name"]] = array();
  foreach($coverage_group_names as $coverage_group_name)
    {
    $coverage_data[$build_group["name"]][$coverage_group_name] = array();
    $linechart_data[$build_group["name"]][$coverage_group_name] = array();
    $coverage_data[$build_group["name"]][$coverage_group_name]["previous"] = 0;
    $coverage_data[$build_group["name"]][$coverage_group_name]["current"] = 0;
    $threshold = $coverage_thresholds[$coverage_group_name];
    $coverage_data[$build_group["name"]][$coverage_group_name]["low"] = 0.7 * $threshold;
    $coverage_data[$build_group["name"]][$coverage_group_name]["medium"] = $threshold;
    $coverage_data[$build_group["name"]][$coverage_group_name]["satisfactory"] = 100;
    }
  }

// used to keep track of current dynamic analysis defects.
$dynamic_analysis_data = array();

// used to keep track of the different types of dynamic analysis
// that are being performed on our build groups of interest.
$dynamic_analysis_types = array();

// gather up the relevant stats for the general build groups
foreach($build_groups as $build_group)
  {
  $beginning_timestamp = $currentstarttime;
  $end_timestamp = $currentstarttime + 3600 * 24;
  $beginning_UTCDate = gmdate(FMT_DATETIME,$beginning_timestamp);
  $end_UTCDate = gmdate(FMT_DATETIME,$end_timestamp);

  $data = gather_overview_data($beginning_UTCDate, $end_UTCDate,
                               $build_group["id"]);

  foreach($build_measurements as $measurement)
    {
    $overview_data[$measurement][$build_group["name"]] = $data[$measurement];
    }

  foreach($coverage_group_names as $coverage_group_name)
    {
    if (array_key_exists($coverage_group_name, $data))
      {
      $coverage_data[$build_group["name"]][$coverage_group_name]["current"] =
        $data[$coverage_group_name];
      }
    }

  if (array_key_exists("dynamic_analysis", $data) &&
      !empty($data["dynamic_analysis"]))
    {
      foreach(array_keys($data["dynamic_analysis"]) as $checker)
        {
        if (!in_array($checker, $dynamic_analysis_types))
          {
          $dynamic_analysis_types[] = $checker;
          }
        if (!array_key_exists($checker, $dynamic_analysis_data))
          {
          $dynamic_analysis_data[$checker] = array();
          }
        // store how many defects were detected by this checker
        // for this build group
        $dynamic_analysis_data[$checker][$build_group["name"]] =
          $data["dynamic_analysis"][$checker];
        }
    }

  // for charting purposes, we also pull data from the past two weeks
  for($i = -13; $i < 1; $i++)
    {
    $chart_beginning_timestamp = $beginning_timestamp + ($i * 3600 * 24);
    $chart_end_timestamp = $end_timestamp + ($i * 3600 * 24);
    $chart_beginning_UTCDate = gmdate(FMT_DATETIME, $chart_beginning_timestamp);
    $chart_end_UTCDate = gmdate(FMT_DATETIME, $chart_end_timestamp);
    // to be passed on to javascript chart renderers
    $chart_data_date = gmdate("M d Y H:i:s", ($chart_end_timestamp + $chart_beginning_timestamp) / 2.0);

    $data = gather_overview_data($chart_beginning_UTCDate, $chart_end_UTCDate,
                                 $build_group["id"]);
    foreach($build_measurements as $measurement)
      {
      $linechart_data[$measurement][$build_group["name"]][] =
        array($chart_data_date, $data[$measurement]);
      }

    // dynamic analysis
    if (array_key_exists("dynamic_analysis", $data) &&
        !empty($data["dynamic_analysis"]))
      {
      foreach(array_keys($data["dynamic_analysis"]) as $checker)
        {
        // add this DA checker to our list if its the first time we've
        // encountered it.
        if (!in_array($checker, $dynamic_analysis_types))
          {
          $dynamic_analysis_types[] = $checker;
          }
        // similarly, make sure this checker / build group combination have
        // an array where they can store their line chart data.
        if (!array_key_exists($checker, $linechart_data[$build_group["name"]]))
          {
          $linechart_data[$build_group["name"]][$checker] = array();
          }

        // add this dynamic analysis data point to our line chart data.
        $num_defects = $data["dynamic_analysis"][$checker];
        $linechart_data[$build_group["name"]][$checker][] =
          array($chart_data_date, (int)$num_defects);
        }
      }

    // coverage too
    foreach($coverage_group_names as $coverage_group_name)
      {
      if (array_key_exists($coverage_group_name, $data))
        {
        $coverage_value = $data[$coverage_group_name];
        $linechart_data[$build_group["name"]][$coverage_group_name][] =
          array($chart_data_date, $coverage_value);

        // assign this date's coverage value as "current" if we don't have one yet.
        if ($coverage_data[$build_group["name"]][$coverage_group_name]["current"] == 0)
          {
          $coverage_data[$build_group["name"]][$coverage_group_name]["current"] =
            $data[$coverage_group_name];
          }
        }
      }
    }
  }

// compute previous coverage value (used by bullet charts)
foreach($build_groups as $build_group)
  {
  foreach($coverage_group_names as $coverage_group_name)
    {
    // isolate the previous coverage value.  This is typically the
    // second to last coverage data point that we collected, but
    // we're careful to check for the case where only a single point
    // was recovered.
    $num_points = count($linechart_data[$build_group["name"]][$coverage_group_name]);

    // normal case: get the value from the 2nd to last data point.
    if ($num_points > 1)
      {
      $coverage_data[$build_group["name"]][$coverage_group_name]["previous"] =
        $linechart_data[$build_group["name"]][$coverage_group_name][$num_points - 2][1];
      }
    // singular case: just make previous & current hold the same value.
    // We do this because nvd3's bullet chart implementation does not support
    // leaving the "marker" off of the chart.
    else
      {
      $prev_point = end($linechart_data[$build_group["name"]][$coverage_group_name]);
      $coverage_data[$build_group["name"]][$coverage_group_name]["previous"] = $prev_point[1];
      }
    }
  }

// gather up data for static analysis
foreach($static_groups as $static_group)
  {
  $beginning_timestamp = $currentstarttime;
  $end_timestamp = $currentstarttime + 3600 * 24;
  $beginning_UTCDate = gmdate(FMT_DATETIME,$beginning_timestamp);
  $end_UTCDate = gmdate(FMT_DATETIME,$end_timestamp);

  $data = gather_static_data($beginning_UTCDate, $end_UTCDate,
                               $static_group["id"]);

  foreach($static_measurements as $measurement)
    {
    $overview_data[$measurement][$static_group["name"]] = $data[$measurement];
    }

  // for charting purposes, we also pull data from the past two weeks
  for($i = -13; $i < 1; $i++)
    {
    $chart_beginning_timestamp = $beginning_timestamp + ($i * 3600 * 24);
    $chart_end_timestamp = $end_timestamp + ($i * 3600 * 24);
    $chart_beginning_UTCDate = gmdate(FMT_DATETIME, $chart_beginning_timestamp);
    $chart_end_UTCDate = gmdate(FMT_DATETIME, $chart_end_timestamp);
    // to be passed on to javascript chart renderers
    $chart_data_date = gmdate("M d Y H:i:s", ($chart_end_timestamp + $chart_beginning_timestamp) / 2.0);

    $data = gather_static_data($chart_beginning_UTCDate, $chart_end_UTCDate,
                                 $static_group["id"]);
    foreach($static_measurements as $measurement)
      {
      $linechart_data[$measurement][$static_group["name"]][] =
        array($chart_data_date, $data[$measurement]);
      }
    }
  }

// now that the data has been collected, we can generate the .xml data
// start with general build groups.
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
    $xml .= add_XML_value("value", $overview_data[$measurement][$build_group["name"]]);
    // JSON encode linechart data to make it easier to use on the client side
    $xml .= add_XML_value("chart", json_encode($linechart_data[$measurement][$build_group["name"]]));
    $xml .= "</group>";
    }
  $xml .= "</measurement>";
  }

// coverage
foreach($build_groups as $build_group)
  {
  foreach($coverage_group_names as $coverage_group_name)
    {
    // skip groups that don't have any coverage info
    if (empty($linechart_data[$build_group["name"]][$coverage_group_name]))
      {
      continue;
      }
    $xml .= "<coverage>";
    $xml .= add_XML_value("name", preg_replace("/[ -]/", "_", $coverage_group_name));
    $xml .= add_XML_value("nice_name", "$coverage_group_name");
    $xml .= add_XML_value("group_name", $build_group["name"]);
    $xml .= add_XML_value("group_name_clean", sanitize_string($build_group["name"]));
    $xml .= add_XML_value("low", $coverage_data[$build_group["name"]][$coverage_group_name]["low"]);
    $xml .= add_XML_value("medium",
      $coverage_data[$build_group["name"]][$coverage_group_name]["medium"]);
    $xml .= add_XML_value("satisfactory",
      $coverage_data[$build_group["name"]][$coverage_group_name]["satisfactory"]);
    $xml .= add_XML_value("current",
      $coverage_data[$build_group["name"]][$coverage_group_name]["current"]);
    $xml .= add_XML_value("previous",
      $coverage_data[$build_group["name"]][$coverage_group_name]["previous"]);
    $xml .= add_XML_value("chart",
      json_encode($linechart_data[$build_group["name"]][$coverage_group_name]));
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
    // skip groups that don't have any data for this tool
    if (empty($linechart_data[$build_group["name"]][$checker]))
      {
      continue;
      }
    $xml .= "<group>";
    $xml .= add_XML_value("group_name", $build_group["name"]);
    $xml .= add_XML_value("group_name_clean", sanitize_string($build_group["name"]));
    $xml .= add_XML_value("chart",
      json_encode($linechart_data[$build_group["name"]][$checker]));
    if (array_key_exists($checker, $dynamic_analysis_data))
      {
      $xml .= add_XML_value("value",
          $dynamic_analysis_data[$checker][$build_group["name"]]);
      }
    else
      {
      $xml .= add_XML_value("value", "N/A");
      }
    $xml .= "</group>";
    }
  $xml .= "</dynamicanalysis>";
  }

// static analysis
foreach($static_groups as $static_group)
  {
  // skip this group if no data was found for it.
  $found = false;
  foreach($static_measurements as $measurement)
    {
    if (!empty($linechart_data[$measurement][$static_group["name"]]))
      {
      $found = true;
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
    $xml .= add_XML_value("value",
      $overview_data[$measurement][$static_group["name"]]);
    $xml .= add_XML_value("chart",
      json_encode($linechart_data[$measurement][$static_group["name"]]));
    $xml .= "</measurement>";
    }
  $xml .= "</staticanalysis>";
  }

$end = microtime_float();
$xml .= "<generationtime>".round($end-$global_start,3)."</generationtime>";
$xml .= "</cdash>";

// Now do the xslt transition
if(!isset($NoXSLGenerate))
  {
  generate_XSLT($xml, "overview");
  }


// function to query database for general build info
function gather_overview_data($start_date, $end_date, $group_id)
{
  global $projectid;
  global $haveNonCore;

  $num_configure_warnings = 0;
  $num_configure_errors = 0;
  $num_build_warnings = 0;
  $num_build_errors = 0;
  $num_failing_tests = 0;
  $dynamic_analysis = array();
  $return_values = array();

  // for coverage
  $core_tested = 0;
  $core_untested = 0;
  if ($haveNonCore)
    {
    $non_core_tested = 0;
    $non_core_untested = 0;
    }

  $builds_query = "SELECT b.id, b.builderrors, b.buildwarnings, b.testfailed,
                   b.configureerrors, b.configurewarnings,
                   cs.loctested AS loctested, cs.locuntested AS locuntested,
                   sp.id AS subprojectid, sp.core AS subprojectcore
                   FROM build AS b
                   LEFT JOIN build2group AS b2g ON (b2g.buildid=b.id)
                   LEFT JOIN coveragesummary AS cs ON (cs.buildid=b.id)
                   LEFT JOIN subproject2build AS sp2b ON (sp2b.buildid = b.id)
                   LEFT JOIN subproject as sp ON (sp2b.subprojectid = sp.id)
                   WHERE b.projectid = '$projectid'
                   AND b.starttime BETWEEN '$start_date' AND '$end_date'
                   AND b2g.groupid = '$group_id'
                   AND b.parentid < 1";

  $builds_array = pdo_query($builds_query);
  add_last_sql_error("gather_overview_data", $group_id);

  while($build_row = pdo_fetch_array($builds_array))
    {
    if ($build_row["configurewarnings"] > 0)
      {
      $num_configure_warnings += $build_row["configurewarnings"];
      }

    if ($build_row["configureerrors"] > 0)
      {
      $num_configure_errors += $build_row["configureerrors"];
      }

    if ($build_row["buildwarnings"] > 0)
      {
      $num_build_warnings += $build_row["buildwarnings"];
      }

    if ($build_row["builderrors"] > 0)
      {
      $num_build_errors += $build_row["builderrors"];
      }

    if ($build_row["testfailed"] > 0)
      {
      $num_failing_tests += $build_row["testfailed"];
      }

    if ($haveNonCore && $build_row["subprojectcore"] == 0)
      {
      $non_core_tested += $build_row["loctested"];
      $non_core_untested += $build_row["locuntested"];
      }
    else
      {
      $core_tested += $build_row["loctested"];
      $core_untested += $build_row["locuntested"];
      }
    }

  $return_values["configure_warnings"] = $num_configure_warnings;
  $return_values["configure_errors"] = $num_configure_errors;
  $return_values["build_warnings"] = $num_build_warnings;
  $return_values["build_errors"] = $num_build_errors;
  $return_values["failing_tests"] = $num_failing_tests;

  if ($haveNonCore)
    {
    if ($core_tested + $core_untested > 0)
      {
      $return_values["core coverage"] =
        round($core_tested / ($core_tested + $core_untested) * 100, 2);
      }
    if ($non_core_tested + $non_core_untested > 0)
      {
      $return_values["non-core coverage"] =
        round($non_core_tested / ($non_core_tested + $non_core_untested) * 100, 2);
      }
    }
  else
    {
    if ($core_tested + $core_untested > 0)
      {
      $return_values["coverage"] =
        round($core_tested / ($core_tested + $core_untested) * 100, 2);
      }
    }

  // handle dynamic analysis defects separately
  $defects_query = "SELECT da.checker AS checker,
                    sum(dd.value) AS defects
                    FROM build AS b
                    LEFT JOIN build2group AS b2g ON (b2g.buildid=b.id)
                    LEFT JOIN dynamicanalysis as da ON (da.buildid = b.id)
                    LEFT JOIN dynamicanalysisdefect as dd ON (dd.dynamicanalysisid=da.id)
                    WHERE b.projectid = '$projectid'
                    AND b.starttime < '$end_date'
                    AND b.starttime >= '$start_date'
                    AND b2g.groupid = '$group_id'
                    AND checker IS NOT NULL
                    GROUP BY checker";

  $defects_array = pdo_query($defects_query);
  add_last_sql_error("gather_overview_data", $group_id);

  while($defect_row = pdo_fetch_array($defects_array))
    {
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
    if (!array_key_exists($defect_row["checker"], $dynamic_analysis))
      {
      $dynamic_analysis[$defect_row["checker"]] = $defect_row["defects"];
      }
    else
      {
      $dynamic_analysis[$defect_row["checker"]] += $defect_row["defects"];
      }
    }

  // add dynamic analysis data to our return package if we found any
  // relevant data.
  if (!empty($dynamic_analysis))
    {
    $return_values["dynamic_analysis"] = $dynamic_analysis;
    }

  return $return_values;
}

// simplified version of gather_overview_data that just collects build errors
// and warnings for static analysis build groups.
function gather_static_data($start_date, $end_date, $group_id)
{
  global $projectid;

  $num_build_warnings = 0;
  $num_build_errors = 0;
  $return_values = array();

  $builds_query = "SELECT b.id, b.builderrors, b.buildwarnings
                   FROM build AS b
                   LEFT JOIN build2group AS b2g ON (b2g.buildid=b.id)
                   WHERE b.projectid = '$projectid'
                   AND b.starttime < '$end_date'
                   AND b.starttime >= '$start_date'
                   AND b2g.groupid = '$group_id'";

  $builds_array = pdo_query($builds_query);
  add_last_sql_error("gather_overview_data", $group_id);

  while($build_row = pdo_fetch_array($builds_array))
    {
    if ($build_row["buildwarnings"] > 0)
      {
      $num_build_warnings += $build_row["buildwarnings"];
      }

    if ($build_row["builderrors"] > 0)
      {
      $num_build_errors += $build_row["builderrors"];
      }
    }

  $return_values["errors"] = $num_build_errors;
  $return_values["warnings"] = $num_build_warnings;
  return $return_values;
}

function sanitize_string($input_string)
{
  // replace various chars that trip up javascript with underscores.
  $retval = str_replace(" ", "_", $input_string);
  $retval = str_replace("-", "_", $retval);
  $retval = str_replace(".", "_", $retval);
  $retval = str_replace("(", "_", $retval);
  $retval = str_replace(")", "_", $retval);
  return $retval;
}

?>
