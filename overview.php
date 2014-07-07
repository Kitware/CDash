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
$projectname = htmlspecialchars(pdo_real_escape_string($projectname));
$projectid = get_project_id($projectname);
$Project = new Project();
$Project->Id = $projectid;
$Project->Fill();

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
$xml .= add_XML_value("previous", "overview.php?projectid=$projectid&date=$previousdate");
$xml .= add_XML_value("current", "overview.php?projectid=$projectid");
$xml .= add_XML_value("next", "overview.phpv?projectid=$projectid&date=$nextdate");
$xml .= "</menu>";

// function to query database for relevant info
function gather_overview_data($start_date, $end_date, $group_id)
{
  global $projectid;
  global $haveNonCore;

  $num_configure_warnings = 0;
  $num_configure_errors = 0;
  $num_build_warnings = 0;
  $num_build_errors = 0;
  $num_failing_tests = 0;
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
                   c.status AS configurestatus, c.warnings AS configurewarnings,
                   cs.loctested AS loctested, cs.locuntested AS locuntested,
                   sp.id AS subprojectid, sp.core AS subprojectcore
                   FROM build AS b
                   LEFT JOIN build2group AS b2g ON (b2g.buildid=b.id)
                   LEFT JOIN configure AS c ON (c.buildid=b.id)
                   LEFT JOIN coveragesummary AS cs ON (cs.buildid=b.id)
                   LEFT JOIN subproject2build AS sp2b ON (sp2b.buildid = b.id)
                   LEFT JOIN subproject as sp ON (sp2b.subprojectid = sp.id)
                   WHERE b.projectid = '$projectid'
                   AND b.starttime < '$end_date'
                   AND b.starttime >= '$start_date'
                   AND b2g.groupid = '$group_id'";

  $builds_array = pdo_query($builds_query);
  add_last_sql_error("gather_overview_data", $group_id);

  while($build_row = pdo_fetch_array($builds_array))
    {
    if ($build_row["configurewarnings"] > 0)
      {
      $num_configure_warnings += $build_row["configurewarnings"];
      }

    if ($build_row["configurestatus"] != 0)
      {
      $num_configure_errors++;
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

  return $return_values;
}


$measurements = array("configure_warnings", "configure_errors",
                      "build_warnings", "build_errors", "failing_tests");

// get the build groups that are included in this project's overview
$query =
  "SELECT bg.id, bg.name FROM overviewbuildgroups AS obg
   LEFT JOIN buildgroup AS bg ON (obg.buildgroupid = bg.id)
   WHERE obg.projectid = '$projectid' ORDER BY obg.position";
$build_group_rows = pdo_query($query);
add_last_sql_error("overview", $projectid);
$build_groups = array();
while($build_group_row = pdo_fetch_array($build_group_rows))
  {
  $build_groups[] = array('id' => $build_group_row["id"],
                          'name' => $build_group_row["name"]);
  }

$overview_data = array();
foreach($measurements as $measurement)
  {
  $overview_data[$measurement] = array();
  $linechart_data[$measurement] = array();
  foreach($build_groups as $build_group)
    {
    $linechart_data[$measurement][$build_group["name"]] = array();
    }
  }


// This logic will need to change if we abstract way core vs. non-core
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

foreach($coverage_group_names as $coverage_group_name)
  {
  $coverage_data[$coverage_group_name] = array();
  $linechart_data[$coverage_group_name] = array();
  $coverage_data[$coverage_group_name]["previous"] = 0;
  $coverage_data[$coverage_group_name]["current"] = 0;
  $threshold = $coverage_thresholds[$coverage_group_name];
  $coverage_data[$coverage_group_name]["low"] = 0.7 * $threshold;
  $coverage_data[$coverage_group_name]["medium"] = $threshold;
  $coverage_data[$coverage_group_name]["satisfactory"] = 100;
  }

// gather up the relevant stats
foreach($build_groups as $build_group)
  {
  $beginning_timestamp = $currentstarttime;
  $end_timestamp = $currentstarttime + 3600 * 24;
  $beginning_UTCDate = gmdate(FMT_DATETIME,$beginning_timestamp);
  $end_UTCDate = gmdate(FMT_DATETIME,$end_timestamp);

  $data = gather_overview_data($beginning_UTCDate, $end_UTCDate,
                               $build_group["id"]);

  foreach($measurements as $measurement)
    {
    $overview_data[$measurement][$build_group["name"]] = $data[$measurement];
    }

  // here we assume that coverage will only be performed by one
  // of the build groups, otherwise this data will be overwritten each
  // time through this (outer) foreach loop.
  foreach($coverage_group_names as $coverage_group_name)
    {
    if (array_key_exists($coverage_group_name, $data))
      {
      $coverage_data[$coverage_group_name]["current"] = $data[$coverage_group_name];
      }
    }

  // for charting purposes, we also pull data from the past two weeks
  for($i = -13; $i < 1; $i++)
    {
    $chart_beginning_timestamp = $beginning_timestamp + ($i * 3600 * 24);
    $chart_end_timestamp = $end_timestamp + ($i * 3600 * 24);
    $chart_beginning_UTCDate = gmdate(FMT_DATETIME, $chart_beginning_timestamp);
    $chart_end_UTCDate = gmdate(FMT_DATETIME, $chart_end_timestamp);
    $data = gather_overview_data($chart_beginning_UTCDate, $chart_end_UTCDate,
                                 $build_group["id"]);
    foreach($measurements as $measurement)
      {
      $linechart_data[$measurement][$build_group["name"]][] =
        array('x' => $chart_end_timestamp * 1000, 'y' => $data[$measurement]);
      }

    // coverage too
    foreach($coverage_group_names as $coverage_group_name)
      {
      if (array_key_exists($coverage_group_name, $data))
        {
        $coverage_value = $data[$coverage_group_name];
        $linechart_data[$coverage_group_name][] =
          array('x' => $chart_end_timestamp * 1000, 'y' => $coverage_value);
        }
      }
    }
  }

// compute previous coverage value
foreach($coverage_group_names as $coverage_group_name)
  {
  // isolate the previous coverage value.  This is typically the
  // second to last coverage data point that we collected, but
  // we're careful to check for the case where only a single point
  // was recovered.
  $num_points = count($linechart_data[$coverage_group_name]);
  if ($num_points > 1)
    {
    $coverage_data[$coverage_group_name]["previous"] =
      $linechart_data[$coverage_group_name][$num_points - 2]['y'];
    }
  else
    {
    $prev_point = end($linechart_data[$coverage_group_name]);
    $coverage_data[$coverage_group_name]["previous"] = $prev_point['y'];
    }
  if (!isset($coverage_data[$coverage_group_name]["previous"]))
    {
    $coverage_data[$coverage_group_name]["previous"] =
      $coverage_data[$coverage_group_name]["current"];
    }
  }

// now that the data has been collected, we can generate the .xml data
foreach($build_groups as $build_group)
  {
  $xml .= "<group>";
  $xml .= add_XML_value("name", $build_group["name"]);
  $xml .= "</group>";
  }
foreach($measurements as $measurement)
  {
  $xml .= "<measurement>";
  $xml .= add_XML_value("name", $measurement);
  $xml .= add_XML_value("nice_name", str_replace("_", " ", $measurement));
  foreach($build_groups as $build_group)
    {
    $xml .= "<group>";
    $xml .= add_XML_value("group_name", $build_group["name"]);
    $xml .= add_XML_value("group_name_clean", str_replace(" ", "_", $build_group["name"]));
    $xml .= add_XML_value("value", $overview_data[$measurement][$build_group["name"]]);
    // JSON encode linechart data to make it easier to use on the client side
    $xml .= add_XML_value("chart", json_encode($linechart_data[$measurement][$build_group["name"]]));
    $xml .= "</group>";
    }
  $xml .= "</measurement>";
  }

foreach($coverage_group_names as $coverage_group_name)
  {
  $xml .= "<coverage>";
  $xml .= add_XML_value("name", preg_replace("/[ -]/", "_", $coverage_group_name));
  $xml .= add_XML_value("nice_name", "$coverage_group_name");
  $xml .= add_XML_value("low", $coverage_data[$coverage_group_name]["low"]);
  $xml .= add_XML_value("medium",
    $coverage_data[$coverage_group_name]["medium"]);
  $xml .= add_XML_value("satisfactory",
    $coverage_data[$coverage_group_name]["satisfactory"]);
  $xml .= add_XML_value("current",
    $coverage_data[$coverage_group_name]["current"]);
  $xml .= add_XML_value("previous",
    $coverage_data[$coverage_group_name]["previous"]);
  $xml .= add_XML_value("chart",
    json_encode($linechart_data[$coverage_group_name]));
  $xml .= "</coverage>";
  }

$xml .= "</cdash>";

// Now do the xslt transition
if(!isset($NoXSLGenerate))
  {
  generate_XSLT($xml, "overview");
  }
?>
