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
include_once("cdash/repository.php");
include("cdash/version.php");
require_once("cdash/bugurl.php");

@$buildid = $_GET["buildid"];
@$date = $_GET["date"];

// Checks
if(!isset($buildid) || !is_numeric($buildid))
  {
  echo "Not a valid buildid!";
  return;
  }

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$build_array = pdo_fetch_array(pdo_query("SELECT * FROM build WHERE id='$buildid'"));
$projectid = $build_array["projectid"];
checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);

$project = pdo_query("SELECT cvsurl,name,nightlytime,bugtrackerfileurl,bugtrackerurl
                      FROM project WHERE id='$projectid'");
if(pdo_num_rows($project)>0)
  {
  $project_array = pdo_fetch_array($project);
  $svnurl = $project_array["cvsurl"];
  $projectname = $project_array["name"];
  }

$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";

$siteid = $build_array["siteid"];
$buildtype = $build_array["type"];
$buildname = $build_array["name"];
$starttime = $build_array["starttime"];

$date = get_dashboard_date_from_build_starttime($build_array["starttime"],$project_array["nightlytime"]);

// Menu
$xml .= "<menu>";
$xml .= add_XML_value("back","index.php?project=".urlencode($projectname)."&date=".$date);
$previousbuildid = get_previous_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
if($previousbuildid>0)
  {
  $xml .= add_XML_value("previous","viewUpdate.php?buildid=".$previousbuildid);
  }
else
  {
  $xml .= add_XML_value("noprevious","1");
  }
$xml .= add_XML_value("current","viewUpdate.php?buildid=".get_last_buildid($projectid,$siteid,$buildtype,$buildname,$starttime));
$nextbuildid = get_next_buildid($projectid,$siteid,$buildtype,$buildname,$starttime);
if($nextbuildid>0)
  {
  $xml .= add_XML_value("next","viewUpdate.php?buildid=".$nextbuildid);
  }
else
  {
  $xml .= add_XML_value("nonext","1");
  }
$xml .= "</menu>";

$xml .= get_cdash_dashboard_xml_by_name($projectname,$date);

  // Build
  $xml .= "<build>";
  $site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
  $xml .= add_XML_value("site",$site_array["name"]);
  $xml .= add_XML_value("siteid",$siteid);
  $xml .= add_XML_value("buildname",$build_array["name"]);
  $xml .= add_XML_value("buildid",$build_array["id"]);
  $xml .= add_XML_value("buildtime",date("D, d M Y H:i:s T",strtotime($build_array["starttime"]." UTC")));
  $xml .= "</build>";

  $xml .= "<updates>";
  // Return the status
  $status_array = pdo_fetch_array(pdo_query("SELECT status,revision,priorrevision,path
                                  FROM buildupdate,build2update AS b2u WHERE buildupdate.id=b2u.updateid AND b2u.buildid='$buildid'"));
  if(strlen($status_array["status"]) > 0 && $status_array["status"]!="0")
    {
    $xml .= add_XML_value("status",$status_array["status"]);
    }
  else
    {
    $xml .= add_XML_value("status",""); // empty status
    }
  $xml .= add_XML_value("revision",$status_array["revision"]);
  $xml .= add_XML_value("priorrevision",$status_array["priorrevision"]);
  $xml .= add_XML_value("path",$status_array["path"]);
  $xml .= add_XML_value("revisionurl",
    get_revision_url($projectid, $status_array["revision"], $status_array["priorrevision"]));
  $xml .= add_XML_value("revisiondiff",
    get_revision_url($projectid, $status_array["priorrevision"], '')); // no prior prior revision...

  $xml .= "<javascript>";

  $updatedfiles = pdo_query("SELECT * FROM updatefile AS uf,build2update AS b2u WHERE uf.updateid=b2u.updateid AND b2u.buildid=".$buildid."
                              ORDER BY REVERSE(RIGHT(REVERSE(filename),LOCATE('/',REVERSE(filename)))) ");

  function sort_array_by_directory($a,$b)
    {
    return $a>$b ? 1:0;
    }

  function sort_array_by_filename($a,$b)
    {
    // Extract directory
    $filenamea = $a['filename'];
    $filenameb = $b['filename'];
    return $filenamea>$filenameb ? 1:0;
    }

  $directoryarray = array();
  $updatearray1 = array();
  // Create an array so we can sort it
  while($file_array = pdo_fetch_array($updatedfiles))
    {
    $file = array();
    $file['filename'] = $file_array["filename"];
    $file['author'] = $file_array["author"];
    $file['status'] = $file_array["status"];

    // Only display email if the user is logged int
    if(isset($_SESSION['cdash']))
      {
      $file['email'] = get_author_email($projectname, $file['author']);
      }
    else
      {
      $file['email'] = "";
      }

    $file['log'] = $file_array["log"];
    $file['revision'] = $file_array["revision"];
    $updatearray1[] = $file;
    $directoryarray[] = substr($file_array["filename"],0,strrpos($file_array["filename"],"/"));
    }

  $directoryarray = array_unique($directoryarray);
  usort($directoryarray, "sort_array_by_directory");
  usort($updatearray1, "sort_array_by_filename");

  $updatearray = array();

  foreach($directoryarray as $directory)
    {
    foreach($updatearray1 as $update)
      {
      $filename = $update['filename'];
      if(substr($filename,0,strrpos($filename,"/")) == $directory)
        {
        $updatearray[] = $update;
        }
      }
    }

  $projecturl = $svnurl;

  $locallymodified = array();
  $conflictingfiles = array();
  $updatedfiles = array();

  // locally cached query result same as get_project_property($projectname, "cvsurl");
  foreach($updatearray as $file)
    {
    $filename = $file['filename'];
    $filename = str_replace("\\", "/", $filename);
    $directory = substr($filename,0,strrpos($filename,"/"));

    $pos = strrpos($filename,"/");
    if($pos !== FALSE)
      {
      $filename = substr($filename,$pos+1);
      }

    $baseurl = $project_array["bugtrackerfileurl"];
    if(empty($baseurl))
      {
      $baseurl = $project_array["bugtrackerurl"];
      }

    $author = $file['author'];
    $email = $file['email'];
    $log = $file['log'];
    $status = $file['status'];
    $revision = $file['revision'];
    $log = str_replace("\r"," ",$log);
    $log = str_replace("\n", " ", $log);
    // Do this twice so that <something> ends up as
    // &amp;lt;something&amp;gt; because it gets sent to a
    // java script function not just displayed as html
    $log = XMLStrFormat($log); // Apparently no need to do this twice anymore
    $log = XMLStrFormat($log);

    $log = trim($log);

    $file['directory'] = $directory;
    $file['author'] = $author;
    $file['email'] = $email;
    $file['log'] = $log;
    $file['revision'] = $revision;
    $file['filename'] = $filename;
    $file['bugurl'] = "";
    $file['bugid'] = "0";
    $file['bugpos'] = "0";

    $bug = get_bug_from_log($log, $baseurl);
    if ($bug !== FALSE)
      {
      $file['bugurl'] = $bug[0];
      $file['bugid'] = $bug[1];
      $file['bugpos'] = $bug[2];
      }

    if($status == "UPDATED")
      {
      $diff_url = get_diff_url($projectid,$projecturl, $directory, $filename, $revision);
      $diff_url = XMLStrFormat($diff_url);
      $file['diff_url'] = $diff_url;
      $updatedfiles[] = $file;
      }
    else if($status == "MODIFIED")
      {
      $diff_url = get_diff_url($projectid,$projecturl, $directory, $filename);
      $diff_url = XMLStrFormat($diff_url);
      $file['diff_url'] = $diff_url;
      $locallymodified[] = $file;
      }
    else //CONFLICTED
      {
      $diff_url = get_diff_url($projectid,$projecturl, $directory, $filename);
      $diff_url = XMLStrFormat($diff_url);
      $file['diff_url'] = $diff_url;
      $conflictingfiles[] = $file;
      }
    }

  // Updated files
  $xml .= "dbAdd (true, \"".$projectname." Updated files  (".count($updatedfiles).")\", \"\", 0, \"\", \"1\", \"\", \"\", \"\", \"\", \"\", \"\")\n";
   $previousdir = "";
  foreach($updatedfiles as $file)
    {
    $directory = $file['directory'];
    if($previousdir=="" || $directory != $previousdir)
      {
      $xml .= " dbAdd (true, \"".$directory."\", \"\", 1, \"\", \"1\", \"\", \"\", \"\", \"\", \"\", \"\")\n";
      $previousdir = $directory;
      }
    $xml .= " dbAdd ( false, \"".$file['filename']." Revision: ".$file['revision']."\",\"".$file['diff_url']."\",2,\"\",\"1\",\"".$file['author']."\",\"".$file['email']."\",\"".$file['log']."\",\"".$file['bugurl']."\",\"".$file['bugid']."\",\"".$file['bugpos']."\")\n";
    }

  // Modified files
  $xml .= "dbAdd (true, \"Modified files  (".count($locallymodified).")\", \"\", 0, \"\", \"1\", \"\", \"\", \"\", \"\", \"\", \"\")\n";
  $previousdir = "";
  foreach($locallymodified as $file)
    {
    $directory = $file['directory'];
    if($previousdir=="" || $directory != $previousdir)
      {
      $xml .= " dbAdd (true, \"".$directory."\", \"\", 1, \"\", \"1\", \"\", \"\", \"\", \"\", \"\", \"\")\n";
      $previousdir = $directory;
      }
    $xml .= " dbAdd ( false, \"".$file['filename']."\",\"".$file['diff_url']."\",2,\"\",\"1\",\"".$file['author']."\",\"".$file['email']."\",\"".$file['log']."\",\"".$file['bugurl']."\",\"".$file['bugid']."\",\"".$file['bugpos']."\")\n";
    }

  // Conflicting files
  $xml .= "dbAdd (true, \"Conflicting files  (".count($conflictingfiles).")\", \"\", 0, \"\", \"1\", \"\", \"\", \"\", \"\", \"\", \"\")\n";
  $previousdir = "";
  foreach($conflictingfiles as $file)
    {
    $directory = $file['directory'];
    if($previousdir=="" || $directory != $previousdir)
      {
      $xml .= " dbAdd (true, \"".$directory."\", \"\", 1, \"\", \"1\", \"\", \"\", \"\", \"\", \"\")\n";
      $previousdir = $directory;
      }
    $xml .= " dbAdd ( false, \"".$file['filename']." Revision: ".$file['revision']."\",\"".$file['diff_url']."\",2,\"\",\"1\",\"".$file['author']."\",\"".$file['email']."\",\"".$file['log']."\",\"".$file['bugurl']."\",\"".$file['bugid']."\",\"".$file['bugpos']."\")\n";
    }

  $xml .= "</javascript>";
  $xml .= "</updates>";
  $xml .= "</cdash>";


// Now doing the xslt transition
generate_XSLT($xml,"viewUpdate");
?>
