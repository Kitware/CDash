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
include("cdash/config.php");
require_once("cdash/pdo.php");
include('login.php');
include_once("cdash/common.php");
include("cdash/version.php");

@$buildid = $_GET["buildid"];
@$fileid = $_GET["fileid"];
@$date = $_GET["date"];

// Checks
if(!isset($buildid) || !is_numeric($buildid))
  {
  echo "Not a valid buildid!";
  return;
  }

@$userid = $_SESSION['cdash']['loginid'];
if(!isset($userid))
  {
  $userid = 0;
  }

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$build_array = pdo_fetch_array(pdo_query("SELECT starttime,projectid FROM build WHERE id='$buildid'"));
$projectid = $build_array["projectid"];
if(!isset($projectid) || $projectid==0)
  {
  echo "This build doesn't exist. Maybe it has been deleted.";
  exit();
  }

checkUserPolicy($userid,$projectid);

$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if(pdo_num_rows($project) == 0)
  {
  echo "This project doesn't exist.";
  exit();
  }

$project_array = pdo_fetch_array($project);
$projectname = $project_array["name"];

$role=0;
$user2project = pdo_query("SELECT role FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
if(pdo_num_rows($user2project)>0)
  {
  $user2project_array = pdo_fetch_array($user2project);
  $role = $user2project_array["role"];
  }
if(!$project_array["showcoveragecode"] && $role<2)
  {
  echo "This project doesn't allow display of coverage code. Contact the administrator of the project.";
  exit();
  }

list ($previousdate, $currenttime, $nextdate) = get_dates($date,$project_array["nightlytime"]);
$logoid = getLogoID($projectid);

$xml = begin_XML_for_XSLT();
$xml .= "<title>CDash : ".$projectname."</title>";

$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);

  // Build
  $xml .= "<build>";
  $build = pdo_query("SELECT * FROM build WHERE id='$buildid'");
  $build_array = pdo_fetch_array($build);
  $siteid = $build_array["siteid"];
  $site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
  $xml .= add_XML_value("site",$site_array["name"]);
  $xml .= add_XML_value("buildname",$build_array["name"]);
  $xml .= add_XML_value("buildid",$build_array["id"]);
  $xml .= add_XML_value("buildtime",$build_array["starttime"]);
  $xml .= "</build>";

  // coverage
  $coveragefile_array = pdo_fetch_array(pdo_query("SELECT fullpath,file FROM coveragefile WHERE id='$fileid'"));

  $xml .= "<coverage>";
  $xml .= add_XML_value("fullpath",$coveragefile_array["fullpath"]);

  if($CDASH_USE_COMPRESSION)
    {
    if($CDASH_DB_TYPE == "pgsql")
      {
      if(is_resource($coveragefile_array["file"]))
        {
        $file = base64_decode(stream_get_contents($coveragefile_array["file"]));
        }
      else
        {
        $file = base64_decode($coveragefile_array["file"]);
        }
      }
    else
      {
      $file = $coveragefile_array["file"];
      }

    @$uncompressedrow = gzuncompress($file);
    if($uncompressedrow !== false)
      {
      $file = $uncompressedrow;
      }
    }
  else
    {
    $file = $coveragefile_array["file"];
    }

    // Generating the html file
  $file_array = explode("<br>",$file);
  $i = 0;

  // Get the codes in an array
  $linecodes = array();
  $coveragefilelog = pdo_query("SELECT log FROM coveragefilelog WHERE fileid=".qnum($fileid)." AND buildid=".qnum($buildid));
  if(pdo_num_rows($coveragefilelog)>0)
    {
    $coveragefilelog_array = pdo_fetch_array($coveragefilelog);
    if($CDASH_DB_TYPE == "pgsql")
      {
      $log = stream_get_contents($coveragefilelog_array['log']);
      }
    else
      {
      $log = $coveragefilelog_array['log'];
      }
    $linecode = explode(';',$log);
    foreach($linecode as $value)
      {
      if(!empty($value))
        {
        $code = explode(':',$value);
        $linecodes[$code[0]] = $code[1];
        }
      }
    }

  foreach($file_array as $line)
    {
    $linenumber = $i+1;
    $line = htmlentities($line);

    $file_array[$i] = '<span class="lineNum">'.str_pad($linenumber,5,' ', STR_PAD_LEFT).'</span>';

    if(array_key_exists($i,$linecodes))
      {
      $code = $linecodes[$i];
      if($code==0)
        {
        $file_array[$i] .= '<span class="lineNoCov">';
        }
      else
        {
        $file_array[$i] .= '<span class="lineCov">';
        }
      $file_array[$i] .= str_pad($code,5,' ', STR_PAD_LEFT)." | ".$line;
      $file_array[$i] .= "</span>";
      }
    else
      {
      $file_array[$i] .= str_pad('',5,' ', STR_PAD_LEFT)." | ".$line;
      }
    $i++;
    }

  $file = implode("<br>",$file_array);

  $xml .= "<file>".utf8_encode(htmlspecialchars($file))."</file>";
  $xml .= "</coverage>";
  $xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewCoverageFile");
?>
