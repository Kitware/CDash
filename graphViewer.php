<?php
error_reporting(E_ALL ^E_NOTICE);
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id: graphViewer.php $
  Language:  PHP
  Date:      $Date: 2013-07-17 23:38:13 +0200 (Wed, 17 Jul 2013) $

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.
  Copyright (c) 2014 Volkan Gezer <volkangezer@gmail.com>
=========================================================================*/
include("cdash/config.php");
require_once("cdash/pdo.php");
include('login.php');
include_once("cdash/common.php");
include_once("models/project.php");
require_once("filterdataFunctions.php");

if ($session_OK)
{
$projectid = pdo_real_escape_numeric($_REQUEST["projectid"]);
checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);

// Checks
if(!isset($projectid) || !is_numeric($projectid))
  {
  echo "Not a valid projectid!";
  return;
  }

if(isset($_REQUEST["value1"]) && strlen($_REQUEST["value1"])>0)
  {
  $filtercount = $_REQUEST["filtercount"];
  }
else
  {
  $filtercount = 0;
  }

$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if(pdo_num_rows($project)>0)
  {
  $project_array = pdo_fetch_array($project);
  $projectname = $project_array["name"];
  $nightlytime = $project_array["nightlytime"];
  }

$starttime=$_POST['starttime'];
$endtime=$_POST['endtime'];


$xml = begin_XML_for_XSLT();
$xml .= "<backurl>user.php</backurl>";
$xml .= "<title>CDash - ".$projectname." Graph Viewer</title>";
$xml .= "<menutitle>".$projectname."</menutitle>";
$xml .= "<menusubtitle>Graph Viewer</menusubtitle>";
$xml .= "<date>".date("Y-m-d")."</date>";

$xml .= "<dashboard>";
$xml .= "<date>".date("Y-m-d")."</date>";
$xml .= "<projectname>".$projectname."</projectname>";
$xml .= "<projectname_encoded>".urlencode($projectname)."</projectname_encoded>";
$xml .= "<projectid>".$projectid."</projectid>";
$xml .= "</dashboard>";

if($projectid>0)
  {
  $Project = new Project;
  $Project->Id = $projectid;
  $xml .= "<project>";
  $xml .= add_XML_value("id",$projectid);
  $xml .= add_XML_value("name",$Project->GetName());
  $xml .= add_XML_value("name_encoded",urlencode($Project->GetName()));

  $xml .= "</project>";
  }

// Menu
$xml .= "<menu>";

$nightlytime = get_project_property($projectname,"nightlytime");
$xml .= add_XML_value("back","index.php?project=".urlencode($projectname)."&date=".get_dashboard_date_from_build_starttime($build_array["starttime"],$nightlytime));

  $xml .= add_XML_value("noprevious","1");
  $xml .= add_XML_value("nonext","1");
  $xml .= "</menu>";
  $xml .= add_XML_value("filtercount",$filtercount);
  if($filtercount>0)
  {
  $xml .= add_XML_value("showfilters",1);
  }
   {
   $xml .= "<user>";
   $userid = $_SESSION['cdash']['loginid'];
   $user = pdo_query("SELECT admin FROM ".qid("user")." WHERE id='$userid'");
   $user_array = pdo_fetch_array($user);
   $xml .= add_XML_value("id",$userid);
   $xml .= add_XML_value("admin",$user_array["admin"]);
   $xml .= "</user>";
   }

// Filters:
//
$filterdata = get_filterdata_from_request();
$filter_sql = $filterdata['sql'];
$limit_sql = '';
if ($filterdata['limit']>0)
{
  $limit_sql = ' LIMIT '.$filterdata['limit'];
}
$xml .= $filterdata['xml'];

if($filter_sql)
{

  $query = "SELECT test.id, testmeasurement.name AS mname, test.name AS tname, site.name AS site, build2test.buildid,
            testmeasurement.value, build.starttime, build.endtime
            FROM (test, site, build)
            JOIN testmeasurement ON (testmeasurement.testid = test.id)
            JOIN build2test ON (build2test.buildid = build.id AND test.id = build2test.testid)
            WHERE site.id = build.siteid AND testmeasurement.type LIKE '%numeric%'";
  if($starttime !== '')
    {
    $query .= " AND build.starttime >= '$starttime'";
    }
  if($endtime !== '')
    {
    $query .= " AND build.endtime <= '$endtime'";
    }
  $query .= "$filter_sql $limit_sql";
  $result = pdo_query($query);

  $test_list = array();
  $measurement_list = array();
  $site_list = array();
  $graph_array = array();
  while($row = pdo_fetch_array($result))
    {
    if(!in_array($row['mname'],$measurement_list)) $measurement_list[] = $row['mname'];
    if(!in_array($row['tname'],$test_list)) $test_list[] = $row['tname'];
    if(!in_array($row['site'],$site_list)) $site_list[] = $row['site'];

    $graph_array[$row['mname']][$row['tname']][$row['site']][]=$row['value'];
    }
  $xml .= add_XML_value("starttime", $starttime);
  $xml .= add_XML_value("endtime", $endtime);
  if(pdo_num_rows($result) == 0)
    {
    $xml .= "<test>";
    $xml .= add_XML_value("name", "Filters returned no results!");
    $xml .= "</test>";
    }
  foreach($graph_array as $measurement => $tests)
    {
    foreach($tests as $test => $computers)
      {
      $xml .= "<test>";
      $xml .= add_XML_value("name", $test);
      $xml .= add_XML_value("mname", $measurement);
      foreach($computers as $sitename => $values)
        {
        $xml .= "<site>";
        $xml .= add_XML_value("tname", $test);
        $xml .= add_XML_value("mname", $measurement);
        $xml .= add_XML_value("name", $sitename);
        foreach($values as $index => $value)
          {
          $xml .= add_XML_value("value", $value);
          }
        $xml .= "</site>";
        }
        $xml .= "</test>";
      }
    }

}
$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"graphViewer");
} // end if session
?>
