<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id: viewBuildError.php 3475 2014-05-09 06:54:04Z jjomier $
  Language:  PHP
  Date:      $Date: 2014-05-09 06:54:04 +0000 (Fri, 09 May 2014) $
  Version:   $Revision: 3475 $

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
include_once("cdash/repository.php");
include("cdash/version.php");

@$buildid = $_GET["buildid"];
if ($buildid != NULL)
  {
  $buildid = pdo_real_escape_numeric($buildid);
  }

@$date = $_GET["date"];
if ($date != NULL)
  {
  $date = htmlspecialchars(pdo_real_escape_string($date));
  }

// Checks
if(!isset($buildid) || !is_numeric($buildid))
  {
  echo "Not a valid buildid!";
  return;
  }

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$start = microtime_float();

$build_query = "SELECT build.id, build.projectid, build.siteid, build.type,
                       build.name, build.starttime, buildupdate.revision
                FROM build
                LEFT JOIN build2update ON (build2update.buildid = build.id)
                LEFT JOIN buildupdate ON (buildupdate.id = build2update.updateid)
                WHERE build.id = '$buildid'";
$build_array = pdo_fetch_array(pdo_query($build_query));

if(empty($build_array))
  {
  echo "This build does not exist. Maybe it has been deleted.";
  exit();
  }

$projectid = $build_array["projectid"];
$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if(pdo_num_rows($project)>0)
  {
  $project_array = pdo_fetch_array($project);
  $projectname = $project_array["name"];
  }

checkUserPolicy(@$_SESSION['cdash']['loginid'],$project_array["id"]);

$xml = begin_XML_for_XSLT();
$xml .= "<title>CDash : ".$projectname."</title>";

$siteid = $build_array["siteid"];
$buildtype = $build_array["type"];
$buildname = $build_array["name"];
$starttime = $build_array["starttime"];
$revision = $build_array["revision"];

$date = get_dashboard_date_from_build_starttime($build_array["starttime"],$project_array["nightlytime"]);
$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);

$xml .= "<menu>";
$xml .= add_XML_value("back","index.php?project=".urlencode($projectname)."&date=".$date);
$previousbuildid = get_previous_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
if($previousbuildid>0)
  {
  $xml .= add_XML_value("previous","viewBuildError.php?buildid=".$previousbuildid);
  }
else
  {
  $xml .= add_XML_value("noprevious","1");
  }
$xml .= add_XML_value("current","viewBuildError.php?buildid=".get_last_buildid($projectid,$siteid,$buildtype,$buildname,$starttime));
$nextbuildid = get_next_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
if($nextbuildid>0)
  {
  $xml .= add_XML_value("next","viewBuildError.php?buildid=".$nextbuildid);
  }
else
  {
  $xml .= add_XML_value("nonext","1");
  }
$xml .= "</menu>";

  // Build
  $xml .= "<build>";
  $site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
  $xml .= add_XML_value("site",$site_array["name"]);
  $xml .= add_XML_value("siteid",$siteid);
  $xml .= add_XML_value("buildname",$build_array["name"]);
  $xml .= add_XML_value("starttime",date(FMT_DATETIMETZ,strtotime($build_array["starttime"]."UTC")));
  $xml .= add_XML_value("buildid",$build_array["id"]);
  $xml .= "</build>";

  @$type = $_GET["type"];
  if ($type != NULL)
    {
    $type = pdo_real_escape_numeric($type);
    }
  if(!isset($type))
    {
    $type = 0;
    }

  // Set the error
  if($type == 0)
    {
    $xml .= add_XML_value("errortypename","Error");
    $xml .= add_XML_value("nonerrortypename","Warning");
    $xml .= add_XML_value("nonerrortype","1");
    }
  else
    {
    $xml .= add_XML_value("errortypename","Warning");
    $xml .= add_XML_value("nonerrortypename","Error");
    $xml .= add_XML_value("nonerrortype","0");
    }

  $xml .= "<errors>";

  if(isset($_GET["onlydeltan"]))
    {
    // Build error table
    $errors = pdo_query("SELECT *
               FROM (SELECT * FROM builderror WHERE buildid=".$previousbuildid."
               AND type=".$type.") AS builderrora
               LEFT JOIN (SELECT crc32 as crc32b FROM builderror WHERE buildid=".$buildid."
               AND type=".$type.") AS builderrorb
               ON builderrora.crc32=builderrorb.crc32b WHERE builderrorb.crc32b IS NULL");

    $errorid = 0;
    while($error_array = pdo_fetch_array($errors))
      {
      $lxml = "<error>";
      $lxml .= add_XML_value("id",$errorid);
      $lxml .= add_XML_value("new","-1");
      $lxml .= add_XML_value("logline",$error_array["logline"]);
      $lxml .= add_XML_value("text",$error_array["text"]);
      $lxml .= add_XML_value("sourcefile",$error_array["sourcefile"]);
      $lxml .= add_XML_value("sourceline",$error_array["sourceline"]);
      $lxml .= add_XML_value("precontext",$error_array["precontext"]);
      $lxml .= add_XML_value("postcontext",$error_array["postcontext"]);

      $projectCvsUrl = $project_array["cvsurl"];
      $file = basename($error_array["sourcefile"]);
      $directory = dirname($error_array["sourcefile"]);
      $cvsurl = get_diff_url($projectid,$projectCvsUrl,$directory,$file,$revision);


      $lxml .= add_XML_value("cvsurl",$cvsurl);
      $errorid++;
      $lxml .= "</error>";

      $xml .= $lxml;
      }

    // Build failure table
    $errors = pdo_query("SELECT *
               FROM (SELECT * FROM buildfailure WHERE buildid=".$previousbuildid."
               AND type=".$type.") AS builderrora
               LEFT JOIN (SELECT crc32 as crc32b FROM buildfailure WHERE buildid=".$buildid."
               AND type=".$type.") AS builderrorb
               ON builderrora.crc32=builderrorb.crc32b WHERE builderrorb.crc32b IS NULL");

   while($error_array = pdo_fetch_array($errors))
      {
      $lxml = "<error>";
      $lxml .= add_XML_value("id",$errorid);
      $lxml .= add_XML_value("language",$error_array["language"]);
      $lxml .= add_XML_value("sourcefile",$error_array["sourcefile"]);
      $lxml .= add_XML_value("targetname",$error_array["targetname"]);
      $lxml .= add_XML_value("outputfile",$error_array["outputfile"]);
      $lxml .= add_XML_value("outputtype",$error_array["outputtype"]);
      $lxml .= add_XML_value("workingdirectory",$error_array["workingdirectory"]);

      $buildfailureid = $error_array["id"];
      $arguments = pdo_query("SELECT bfa.argument FROM buildfailureargument AS bfa,buildfailure2argument AS bf2a
                              WHERE bf2a.buildfailureid='$buildfailureid' AND bf2a.argumentid=bfa.id ORDER BY bf2a.place ASC");

      $i=0;
      while($argument_array = pdo_fetch_array($arguments))
        {
        if($i == 0)
          {
          $lxml .= add_XML_value("argumentfirst",$argument_array["argument"]);
          }
        else
          {
          $lxml .= add_XML_value("argument",$argument_array["argument"]);
          }
        $i++;
        }

      $lxml .= get_labels_xml_from_query_results(
        "SELECT text FROM label, label2buildfailure WHERE ".
        "label.id=label2buildfailure.labelid AND ".
        "label2buildfailure.buildfailureid='$buildfailureid' ".
        "ORDER BY text ASC");

      $lxml .= add_XML_value("stderror",$error_array["stderror"]);
      $rows = substr_count($error_array["stderror"],"\n")+1;
      if ($rows > 10)
        {
        $rows = 10;
        }
      $lxml .= add_XML_value("stderrorrows",$rows);

      $lxml .= add_XML_value("stdoutput",$error_array["stdoutput"]);
      $rows = substr_count($error_array["stdoutput"],"\n")+1;
      if ($rows > 10)
        {
        $rows = 10;
        }
      $lxml .= add_XML_value("stdoutputrows",$rows);

      $lxml .= add_XML_value("exitcondition",$error_array["exitcondition"]);

      if(isset($error_array["sourcefile"]))
        {
        $projectCvsUrl = $project_array["cvsurl"];
        $file = basename($error_array["sourcefile"]);
        $directory = dirname($error_array["sourcefile"]);
        $cvsurl = get_diff_url($projectid,$projectCvsUrl,$directory,$file,$revision);
        $lxml .= add_XML_value("cvsurl",$cvsurl);
        }
      $errorid++;
      $lxml .= "</error>";

      $xml .= $lxml;
      }

    }
  else
    {
    $extrasql = "";
    if(isset($_GET["onlydeltap"]))
      {
      $extrasql = " AND newstatus='1'";
      }

    // Build error table
    $errors = pdo_query("SELECT * FROM builderror WHERE buildid='$buildid' AND type='$type'".$extrasql." ORDER BY logline ASC");
    $errorid = 0;
    while($error_array = pdo_fetch_array($errors))
      {
      $lxml = "<error>";
      $lxml .= add_XML_value("id",$errorid);
      $lxml .= add_XML_value("new",$error_array["newstatus"]);
      $lxml .= add_XML_value("logline",$error_array["logline"]);

      $projectCvsUrl = $project_array["cvsurl"];
      $text = $error_array["text"];

      // Detect if the source directory has already been replaced by CTest with /.../
      $pattern = "&/.../(.*?)/&";
      $matches = array();
      preg_match($pattern, $text, $matches);
      if (sizeof($matches) > 1)
        {
        $file = $error_array["sourcefile"];
        $directory = $matches[1];
        }
      else
        {
        $file = basename($error_array["sourcefile"]);
        $directory = dirname($error_array["sourcefile"]);
        }

      $cvsurl = get_diff_url($projectid,$projectCvsUrl,$directory,$file,$revision);

      $lxml .= add_XML_value("cvsurl",$cvsurl);
      // when building without launchers, CTest truncates the source dir to /.../
      // use this pattern to linkify compiler output.
      $precontext = linkify_compiler_output($projectCvsUrl, "/\.\.\.", $revision, $error_array["precontext"]);
      $text = linkify_compiler_output($projectCvsUrl, "/\.\.\.", $revision, $error_array["text"]);
      $postcontext = linkify_compiler_output($projectCvsUrl, "/\.\.\.", $revision, $error_array["postcontext"]);

      $lxml .= add_XML_value("precontext", $precontext);
      $lxml .= add_XML_value("text", $text);
      $lxml .= add_XML_value("postcontext", $postcontext);
      $lxml .= add_XML_value("sourcefile",$error_array["sourcefile"]);
      $lxml .= add_XML_value("sourceline",$error_array["sourceline"]);

      $errorid++;
      $lxml .= "</error>";

      $xml .= $lxml;
      }

    // Build failure table
    $errors = pdo_query("SELECT * FROM buildfailure WHERE buildid='$buildid' and type='$type'".$extrasql." ORDER BY id ASC");
    while($error_array = pdo_fetch_array($errors))
      {
      $lxml = "<error>";
      $lxml .= add_XML_value("id",$errorid);
      $lxml .= add_XML_value("language",$error_array["language"]);
      $lxml .= add_XML_value("sourcefile",$error_array["sourcefile"]);
      $lxml .= add_XML_value("targetname",$error_array["targetname"]);
      $lxml .= add_XML_value("outputfile",$error_array["outputfile"]);
      $lxml .= add_XML_value("outputtype",$error_array["outputtype"]);
      $lxml .= add_XML_value("workingdirectory",$error_array["workingdirectory"]);

      $buildfailureid = $error_array["id"];
      $arguments = pdo_query("SELECT bfa.argument FROM buildfailureargument AS bfa,buildfailure2argument AS bf2a
                              WHERE bf2a.buildfailureid='$buildfailureid' AND bf2a.argumentid=bfa.id ORDER BY bf2a.place ASC");
      $i=0;
      while($argument_array = pdo_fetch_array($arguments))
        {
        if($i == 0)
          {
          $lxml .= add_XML_value("argumentfirst",$argument_array["argument"]);
          }
        else
          {
          $lxml .= add_XML_value("argument",$argument_array["argument"]);
          }
        $i++;
        }

      $lxml .= get_labels_xml_from_query_results(
        "SELECT text FROM label, label2buildfailure WHERE ".
        "label.id=label2buildfailure.labelid AND ".
        "label2buildfailure.buildfailureid='$buildfailureid' ".
        "ORDER BY text ASC");

      $stderror = $error_array["stderror"];
      $stdoutput = $error_array["stdoutput"];

      if(isset($error_array["sourcefile"]))
        {
        $projectCvsUrl = $project_array["cvsurl"];
        $file = basename($error_array["sourcefile"]);
        $directory = dirname($error_array["sourcefile"]);
        $cvsurl = get_diff_url($projectid,$projectCvsUrl,$directory,$file,$revision);
        $lxml .= add_XML_value("cvsurl",$cvsurl);

        $source_dir = get_source_dir($projectid, $projectCvsUrl, $directory);
        if ($source_dir !== NULL)
          {
          $stderror = linkify_compiler_output($projectCvsUrl, $source_dir, $revision, $stderror);
          $stdoutput = linkify_compiler_output($projectCvsUrl, $source_dir, $revision, $stdoutput);
          }
        }

      if ($stderror)
        {
        $lxml .= add_XML_value("stderror", $stderror);
        }
      if ($stdoutput)
        {
        $lxml .= add_XML_value("stdoutput", $stdoutput);
        }
      $lxml .= add_XML_value("exitcondition",$error_array["exitcondition"]);
      $errorid++;
      $lxml .= "</error>";
      $xml .= $lxml;
      }
    } // end if onlydeltan

  $xml .= "</errors>";
  $end = microtime_float();
  $xml .= "<generationtime>".round($end-$start,3)."</generationtime>";
  $xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewBuildError");
?>
