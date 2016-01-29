<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
$noforcelogin = 1;
include(dirname(__DIR__)."/config/config.php");
require_once("include/pdo.php");
include('public/login.php');
include_once("include/common.php");
include("include/version.php");

@$buildid = $_GET["buildid"];
if ($buildid != null) {
    $buildid = pdo_real_escape_numeric($buildid);
}

@$date = $_GET["date"];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

// Checks
if (!isset($buildid) || !is_numeric($buildid)) {
    echo "Not a valid buildid!";
    return;
}

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

$build_array = pdo_fetch_array(pdo_query("SELECT starttime,projectid,siteid,type,name FROM build WHERE id='$buildid'"));
$projectid = $build_array["projectid"];
if (!isset($projectid) || $projectid==0) {
    echo "This build doesn't exist. Maybe it has been deleted.";
    exit();
}

checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid);

// lookup table to make the reported defect types more understandable.
// feel free to expand as necessary.
$defect_nice_names = array(
  "FIM" => "Freeing Invalid Memory",
  "IPR" => "Invalid Pointer Read",
  "IPW" => "Invalid Pointer Write");

$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if (pdo_num_rows($project)>0) {
    $project_array = pdo_fetch_array($project);
    $projectname = $project_array["name"];
}

$xml = begin_XML_for_XSLT();
$xml .= "<title>CDash : ".$projectname."</title>";

$xml .= get_cdash_dashboard_xml_by_name($projectname, $date);

$siteid = $build_array["siteid"];
$buildtype = $build_array["type"];
$buildname = $build_array["name"];
$starttime = $build_array["starttime"];

$xml .= "<menu>";
$xml .= add_XML_value("back", "index.php?project=".urlencode($projectname)."&date=".get_dashboard_date_from_build_starttime($build_array["starttime"], $project_array["nightlytime"]));
$previousbuildid = get_previous_buildid_dynamicanalysis($projectid, $siteid, $buildtype, $buildname, $starttime);
if ($previousbuildid>0) {
    $xml .= add_XML_value("previous", "viewDynamicAnalysis.php?buildid=".$previousbuildid);
} else {
    $xml .= add_XML_value("noprevious", "1");
}
$xml .= add_XML_value("current", "viewDynamicAnalysis.php?buildid=".get_last_buildid_dynamicanalysis($projectid, $siteid, $buildtype, $buildname, $starttime));
$nextbuildid = get_next_buildid_dynamicanalysis($projectid, $siteid, $buildtype, $buildname, $starttime);
if ($nextbuildid>0) {
    $xml .= add_XML_value("next", "viewDynamicAnalysis.php?buildid=".$nextbuildid);
} else {
    $xml .= add_XML_value("nonext", "1");
}
$xml .= "</menu>";

  // Build
  $xml .= "<build>";
  $site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
  $xml .= add_XML_value("site", $site_array["name"]);
  $xml .= add_XML_value("buildname", $build_array["name"]);
  $xml .= add_XML_value("buildid", $buildid);
  $xml .= add_XML_value("buildtime", $build_array["starttime"]);
  $xml .= "</build>";

  // dynamic analysis
  $i=0;
  $dynamicanalysis = pdo_query("SELECT * FROM dynamicanalysis WHERE buildid='$buildid' ORDER BY status DESC");
  $defect_types = array();
  while ($dynamicanalysis_array = pdo_fetch_array($dynamicanalysis)) {
      $xml .= "<dynamicanalysis>";
      if ($i%2==0) {
          $xml .= add_XML_value("bgcolor", "#b0c4de");
      }
      $i++;
      $xml .= add_XML_value("status", ucfirst($dynamicanalysis_array["status"]));
      $xml .= add_XML_value("name", $dynamicanalysis_array["name"]);
      $xml .= add_XML_value("id", $dynamicanalysis_array["id"]);

      $dynid = $dynamicanalysis_array["id"];
      $defects = pdo_query("SELECT * FROM dynamicanalysisdefect WHERE dynamicanalysisid='$dynid'");
      while ($defects_array = pdo_fetch_array($defects)) {
          // defects
      $defect_type = $defects_array["type"];
          if (array_key_exists($defect_type, $defect_nice_names)) {
              $defect_type = $defect_nice_names[$defect_type];
          }
          if (!in_array($defect_type, $defect_types)) {
              $defect_types[] = $defect_type;
          }
          $column = array_search($defect_type, $defect_types);

          $xml .= "<defect>";
          $xml .= add_XML_value("column", $column);
          $xml .= add_XML_value("value", $defects_array["value"]);
          $xml .= "</defect>";
      }

      $xml .= get_labels_xml_from_query_results(
      "SELECT text FROM label, label2dynamicanalysis WHERE ".
      "label.id=label2dynamicanalysis.labelid AND ".
      "label2dynamicanalysis.dynamicanalysisid='$dynid' ".
      "ORDER BY text ASC"
      );

      $xml .= "</dynamicanalysis>";
  }

  // explicitly list the defect types encountered here
  // so we can dynamically generate the header row
  foreach ($defect_types as $defect_type) {
      $xml .= "<defecttypes>";
      $xml .= add_XML_value("type", $defect_type);
      $xml .= "</defecttypes>";
  }

  $xml .= add_XML_value("numcolumns", sizeof($defect_types));
  $xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml, "viewDynamicAnalysis");
