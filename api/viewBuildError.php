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
set_include_path(__DIR__.'/..');
include("cdash/config.php");
require_once("cdash/pdo.php");
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
  $response['error'] = "Not a valid buildid!";
  echo json_encode($response);
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
  $response['error'] = "This build does not exist. Maybe it has been deleted.";
  echo json_encode($response);
  return;
  }

$projectid = $build_array["projectid"];
$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if(pdo_num_rows($project)>0)
  {
  $project_array = pdo_fetch_array($project);
  $projectname = $project_array["name"];
  }

if (!checkUserPolicy(@$_SESSION['cdash']['loginid'],$project_array["id"], 1))
  {
  $response['requirelogin'] = '1';
  echo json_encode($response);
  return;
  }

$response = begin_JSON_response();
$response['title'] = "CDash : $projectname";

$siteid = $build_array["siteid"];
$buildtype = $build_array["type"];
$buildname = $build_array["name"];
$starttime = $build_array["starttime"];
$revision = $build_array["revision"];

$date = get_dashboard_date_from_build_starttime($build_array["starttime"],$project_array["nightlytime"]);
get_dashboard_JSON_by_name($projectname, $date, $response);

$menu = array();
$menu['back'] = "index.php?project=".urlencode($projectname)."&date=".$date;
$previousbuildid = get_previous_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
if($previousbuildid>0)
  {
  $menu['previous'] = "viewBuildError.php?buildid=$previousbuildid";
  }
else
  {
  $menu['noprevious'] = 1;
  }
$menu['current'] = "viewBuildError.php?buildid=".
  get_last_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
$nextbuildid = get_next_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
if($nextbuildid>0)
  {
  $menu['next'] = "viewBuildError.php?buildid=$nextbuildid";
  }
else
  {
  $menu['nonext'] = 1;
  }
$response['menu'] = $menu;

// Build
$build = array();
$site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
$build['site'] = $site_array['name'];
$build['siteid'] = $siteid;
$build['buildname'] = $build_array['name'];
$build['starttime'] =
  date(FMT_DATETIMETZ, strtotime($build_array["starttime"]."UTC"));
$build['buildid'] = $build_array['id'];
$response['build'] = $build;

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
  $response['errortypename'] = 'Error';
  $response['nonerrortypename'] = 'Warning';
  $response['nonerrortype'] = 1;
  }
else
  {
  $response['errortypename'] = 'Warning';
  $response['nonerrortypename'] = 'Error';
  $response['nonerrortype'] = 0;
  }

$errors_response = array();

if(isset($_GET["onlydeltan"]))
  {
  // Build error table
  $errors = pdo_query(
    "SELECT * FROM
      (SELECT * FROM builderror
        WHERE buildid=".$previousbuildid." AND type=".$type.") AS builderrora
      LEFT JOIN (SELECT crc32 AS crc32b FROM builderror
        WHERE buildid=".$buildid." AND type=".$type.") AS builderrorb
        ON builderrora.crc32=builderrorb.crc32b
      WHERE builderrorb.crc32b IS NULL");

  $errorid = 0;
  while($error_array = pdo_fetch_array($errors))
    {
    $error_response = array();
    $error_response['id'] = $errorid;
    $error_response['new'] = -1;
    $error_response['logline'] = $error_array['logline'];
    $error_response['text'] = $error_array['text'];
    $error_response['sourcefile'] = $error_array['sourcefile'];
    $error_response['sourceline'] = $error_array['sourceline'];
    $error_response['precontext'] = $error_array['precontext'];
    $error_response['postcontext'] = $error_array['postcontext'];

    $projectCvsUrl = $project_array['cvsurl'];
    $file = basename($error_array['sourcefile']);
    $directory = dirname($error_array['sourcefile']);
    $cvsurl =
      get_diff_url($projectid,$projectCvsUrl,$directory,$file,$revision);

    $error_response['cvsurl'] = $cvsurl;
    $errorid++;

    $errors_response[] = $error_response;
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
    $error_response = array();
    $error_response['id'] = $errorid;
    $error_response['language'] = $error_array['language'];
    $error_response['sourcefile'] = $error_array['sourcefile'];
    $error_response['targetname'] = $error_array['targetname'];
    $error_response['outputfile'] = $error_array['outputfile'];
    $error_response['outputtype'] = $error_array['outputtype'];
    $error_response['workingdirectory'] = $error_array['workingdirectory'];

    $buildfailureid = $error_array['id'];
    $arguments = pdo_query(
      "SELECT bfa.argument FROM buildfailureargument AS bfa,
              buildfailure2argument AS bf2a
       WHERE bf2a.buildfailureid='$buildfailureid' AND
             bf2a.argumentid=bfa.id ORDER BY bf2a.place ASC");

    $i=0;
    $arguments_response = array();
    while($argument_array = pdo_fetch_array($arguments))
      {
      if($i == 0)
        {
        $error_response['argumentfirst'] = $argument_array['argument'];
        }
      else
        {
        $arguments_response[] = $argument_array['argument'];
        }
      $i++;
      }
    $error_response['arguments'] = $arguments_response;

    get_labels_xml_from_query_results(
      "SELECT text FROM label, label2buildfailure
       WHERE label.id=label2buildfailure.labelid AND
             label2buildfailure.buildfailureid='$buildfailureid'
       ORDER BY text ASC", $error_response);

    $error_response['stderror'] = $error_array['stderror'];
    $rows = substr_count($error_array['stderror'], "\n") + 1;
    if ($rows > 10)
      {
      $rows = 10;
      }
    $error_response['stderrorrows'] = $rows;

    $error_response['stdoutput'] = $error_array['stdoutput'];
    $rows = substr_count($error_array['stdoutput'], "\n") + 1;
    if ($rows > 10)
      {
      $rows = 10;
      }
    $error_response['stdoutputrows'] = $rows;

    $error_response['exitcondition'] = $error_array['exitcondition'];

    if(isset($error_array['sourcefile']))
      {
      $projectCvsUrl = $project_array['cvsurl'];
      $file = basename($error_array['sourcefile']);
      $directory = dirname($error_array['sourcefile']);
      $cvsurl =
        get_diff_url($projectid, $projectCvsUrl, $directory, $file, $revision);
      $error_response['cvsurl'] = $cvsurl;
      }
    $errorid++;
    $errors_response[] = $error_response;
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
    $error_response = array();
    $error_response['id'] = $errorid;
    $error_response['new'] = $error_array['newstatus'];
    $error_response['logline'] = $error_array['logline'];

    $projectCvsUrl = $project_array['cvsurl'];
    $text = $error_array['text'];

    // Detect if the source directory has already been replaced by CTest with /.../
    $pattern = "&/.../(.*?)/&";
    $matches = array();
    preg_match($pattern, $text, $matches);
    if (sizeof($matches) > 1)
      {
      $file = $error_array['sourcefile'];
      $directory = $matches[1];
      }
    else
      {
      $file = basename($error_array['sourcefile']);
      $directory = dirname($error_array['sourcefile']);
      }

    $cvsurl = get_diff_url($projectid,$projectCvsUrl,$directory,$file,$revision);

    $error_response['cvsurl'] = $cvsurl;
    // when building without launchers, CTest truncates the source dir to /.../
    // use this pattern to linkify compiler output.
    $precontext = linkify_compiler_output($projectCvsUrl, "/\.\.\.", $revision, $error_array['precontext']);
    $text = linkify_compiler_output($projectCvsUrl, "/\.\.\.", $revision, $error_array['text']);
    $postcontext = linkify_compiler_output($projectCvsUrl, "/\.\.\.", $revision, $error_array['postcontext']);

    $error_response['precontext'] =  $precontext;
    $error_response['text'] =  $text;
    $error_response['postcontext'] =  $postcontext;
    $error_response['sourcefile'] = $error_array['sourcefile'];
    $error_response['sourceline'] = $error_array['sourceline'];

    $errorid++;
    $errors_response[] = $error_response;
    }

  // Build failure table
  $errors = pdo_query("SELECT * FROM buildfailure WHERE buildid='$buildid' and type='$type'".$extrasql." ORDER BY id ASC");
  while($error_array = pdo_fetch_array($errors))
    {
    $error_response = array();
    $error_response['id'] = $errorid;
    $error_response['language'] = $error_array['language'];
    $error_response['sourcefile'] = $error_array['sourcefile'];
    $error_response['targetname'] = $error_array['targetname'];
    $error_response['outputfile'] = $error_array['outputfile'];
    $error_response['outputtype'] = $error_array['outputtype'];
    $error_response['workingdirectory'] = $error_array['workingdirectory'];

    $buildfailureid = $error_array['id'];
    $arguments = pdo_query(
      "SELECT bfa.argument FROM buildfailureargument AS bfa,
              buildfailure2argument AS bf2a
       WHERE bf2a.buildfailureid='$buildfailureid' AND bf2a.argumentid=bfa.id
       ORDER BY bf2a.place ASC");
    $i=0;
    $arguments_response = array();
    while($argument_array = pdo_fetch_array($arguments))
      {
      if($i == 0)
        {
        $error_response['argumentfirst'] = $argument_array['argument'];
        }
      else
        {
        $arguments_response[] = $argument_array['argument'];
        }
      $i++;
      }
    $error_response['arguments'] = $arguments_response;

    get_labels_JSON_from_query_results(
      "SELECT text FROM label, label2buildfailure
       WHERE label.id=label2buildfailure.labelid AND
             label2buildfailure.buildfailureid='$buildfailureid'
       ORDER BY text ASC", $error_response);

    $stderror = $error_array['stderror'];
    $stdoutput = $error_array['stdoutput'];

    if(isset($error_array['sourcefile']))
      {
      $projectCvsUrl = $project_array['cvsurl'];
      $file = basename($error_array['sourcefile']);
      $directory = dirname($error_array['sourcefile']);
      $cvsurl =
        get_diff_url($projectid, $projectCvsUrl, $directory, $file,$revision);
      $error_response['cvsurl'] = $cvsurl;

      $source_dir = get_source_dir($projectid, $projectCvsUrl, $directory);
      if ($source_dir !== NULL)
        {
        $stderror = linkify_compiler_output($projectCvsUrl, $source_dir,
                                            $revision, $stderror);
        $stdoutput = linkify_compiler_output($projectCvsUrl, $source_dir,
                                             $revision, $stdoutput);
        }
      }

    if ($stderror)
      {
      $error_response['stderror'] =  $stderror;
      }
    if ($stdoutput)
      {
      $error_response['stdoutput'] =  $stdoutput;
      }
    $error_response['exitcondition'] = $error_array['exitcondition'];
    $errorid++;
    $errors_response[] = $error_response;
    }
  } // end if onlydeltan

$response['errors'] = $errors_response;
$end = microtime_float();
$response['generationtime'] = round($end-$start, 3);

echo json_encode($response);
?>
