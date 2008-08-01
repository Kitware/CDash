<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id: viewChanges.php,v $
  Language:  PHP
  Date:      $Date: 2007-10-29 15:37:28 -0400 (Mon, 29 Oct 2007) $
  Version:   $Revision: 67 $

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
$noforcelogin = 1;
include("config.php");
require_once("pdo.php");
include('login.php');
include_once("common.php");
include("version.php");

// get_related_dates takes a projectname and basedate as input
// and produces an array of related dates and times based on:
// the input, the project's nightly start time, now
//
function get_related_dates($projectname, $basedate)
{
  include("config.php");
  require_once("pdo.php");

  $dates = array();

  $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
  pdo_select_db("$CDASH_DB_NAME", $db);

  $dbQuery = pdo_query("SELECT nightlytime FROM project WHERE name='$projectname'");
  if(pdo_num_rows($dbQuery)>0)
    {
    $project = pdo_fetch_array($dbQuery);
    $nightlytime = $project['nightlytime'];
    //echo "query result nightlytime: " . $nightlytime . "<br/>";
    }
  else
    {
    $nightlytime = "00:00:00";
    //echo "default nightlytime: " . $nightlytime . "<br/>";
    }

  if(!isset($basedate) || strlen($basedate)==0)
    {
    $basedate = gmdate("Ymd");
    }

  // Convert the nightly time into GMT
  $nightlytime = gmdate("H:i:s",strtotime($nightlytime)); 

  $nightlyhour = substr($nightlytime,0,2);
  $nightlyminute = substr($nightlytime,3,2);
  $nightlysecond = substr($nightlytime,6,2);
  $basemonth = substr($basedate,4,2);
  $baseday = substr($basedate,6,2);
  $baseyear = substr($basedate,0,4);

  $dates['nightly+2'] = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
    $basemonth, $baseday+2, $baseyear);
  $dates['nightly+1'] = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
    $basemonth, $baseday+1, $baseyear);
  $dates['nightly-0'] = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
    $basemonth, $baseday, $baseyear);
  $dates['nightly-1'] = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
    $basemonth, $baseday-1, $baseyear);
  $dates['nightly-2'] = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
    $basemonth, $baseday-2, $baseyear);

  // Snapshot of "now"
  //
  $currentgmtime = time();
  $currentgmdate = gmdate("Ymd", $currentgmtime);

  // Find the most recently past nightly time:
  //
  $todaymonth = substr($currentgmdate,4,2);
  $todayday = substr($currentgmdate,6,2);
  $todayyear = substr($currentgmdate,0,4);
  $currentnightly = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
    $todaymonth, $todayday, $todayyear);
  while ($currentnightly>$currentgmtime)
  {
    $todayday = $todayday - 1;
    $currentnightly = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
      $todaymonth, $todayday, $todayyear);
  }

  $dates['now'] = $currentgmtime;
  $dates['most-recent-nightly'] = $currentnightly;
  $dates['today_utc'] = $currentgmdate;
  $dates['basedate'] = gmdate("Ymd", $dates['nightly-0']);

  // CDash equivalent of DART1's "last rollup time"
  if ($dates['basedate'] === $dates['today_utc'])
  {
    // If it's today, it's now:
    $dates['last-rollup-time'] = $dates['now'];
  }
  else
  {
    // If it's not today, it's the nightly time on the basedate:
    $dates['last-rollup-time'] = $dates['nightly-0'];
  }

  return $dates;
}

function sort_by_directory_file_time($e1, $e2)
{
  // Sort directory names lexicographically in ascending order:
  // (A, B, C, ... Z)
  //
  $d1 = $e1['directory'];
  $d2 = $e2['directory'];
  if ($d1<$d2)
  {
    return -1;
  }
  if ($d1>$d2)
  {
    return 1;
  }

  // Sort file names lexicographically in ascending order
  // (A, B, C, ... Z)
  //
  $f1 = $e1['filename'];
  $f2 = $e2['filename'];
  if ($f1<$f2)
  {
    return -1;
  }
  if ($f1>$f2)
  {
    return 1;
  }

  // Sort time stamps numerically in descending order
  // (newer changes before older changes)
  //
  $t1 = $e1['time'];
  $t2 = $e2['time'];
  if ($t1<$t2)
  {
    return 1;
  }
  if ($t1>$t2)
  {
    return -1;
  }

  // Identical entries:
  //
  return 0;
}

function get_updates_xml_from_commits($projectname, $dates, $commits)
{
  $xml = "<updates>\n";
  $xml .= "<timestamp>" . date("Y-m-d H:i:s T", $dates['nightly-0']." GMT") ."</timestamp>";
  $xml .= "<javascript>\n";

  // Args to dbAdd : "true" means directory, "false" means file
  //
  $xml .= "dbAdd(true, \"Updated files  (".count($commits).")\", \"\", 0, \"\", \"1\", \"\", \"\", \"\")\n";

  $previousdir = "";

  usort($commits, "sort_by_directory_file_time");

  $projecturl = get_project_property($projectname, "cvsurl");

  foreach($commits as $commit)
    {
    $directory = $commit['directory'];

    if($directory != $previousdir)
      {
      $xml .= "dbAdd(true, \"".$directory."\", \"\", 1, \"\", \"1\", \"\", \"\", \"\")\n";
      $previousdir = $directory;
      }

    $filename = $commit['filename'];
    $revision = $commit['revision'];
    $time = gmdate("Y-m-d H:i:s", $commit['time']);
    $author = $commit['author'];
    $email = get_author_email($projectname, $author);

    $comment = $commit['comment'];
    $comment = str_replace("\n", " ", $comment); 
    // Do this twice so that <something> ends up as
    // &amp;lt;something&amp;gt; because it gets sent to a 
    // java script function not just displayed as html
    $comment = XMLStrFormat($comment);
    $comment = XMLStrFormat($comment);
        
    $diff_url = get_diff_url(get_project_id($projectname),$projecturl, $directory, $filename, $revision);
    $diff_url = XMLStrFormat($diff_url);

    $xml .= "dbAdd(false, \"".$filename."  Revision: ".$revision."\",\"".$diff_url."\",2,\"\",\"1\",\"".$author."\",\"".$email."\",\"".$comment."\")\n";
    }

  $xml .= "</javascript>\n";
  $xml .= "</updates>";

  return $xml;
}


// Repository nightly queries are for the 24 hours leading up to the
// nightly start time for "$projectname" on "$date"
@$projectname = $_GET["project"];
@$date = $_GET["date"];

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$projectname = pdo_real_escape_string($projectname);
$project = pdo_query("SELECT id FROM project WHERE name='$projectname'");
$project_array = pdo_fetch_array($project);

$projectid = $project_array["id"];

checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid);

$dates = get_related_dates($projectname, $date);

$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";

$gmdate = gmdate("Ymd",$dates['nightly-0']);

$xml .= get_cdash_dashboard_xml_by_name($projectname, $date);
$xml .= "<menu>";
$xml .= add_XML_value("back","index.php?project=".$projectname."&date=".get_dashboard_date_from_project($projectname,$date));
$xml .= "</menu>";

$dailyupdate = pdo_query("SELECT * FROM dailyupdatefile,dailyupdate 
                            WHERE dailyupdate.date='$gmdate' and projectid='$projectid'
                            AND dailyupdatefile.dailyupdateid = dailyupdate.id");
$commits = array();
while($dailyupdate_array = pdo_fetch_array($dailyupdate))
  {
  $commit = array();
  $current_directory = dirname($dailyupdate_array['filename']);
  $current_filename = basename($dailyupdate_array['filename']);
  $current_revision = $dailyupdate_array['revision'];
  $commit['directory'] = $current_directory;
  $commit['filename'] = $current_filename;
  $commit['revision'] = $current_revision;
  $commit['time'] = $dailyupdate_array['time'];
  $commit['author'] = $dailyupdate_array['author'];
  $commit['comment'] = $dailyupdate_array['log'];
  $commits[$current_directory . "/" . $current_filename . ";" . $current_revision] = $commit;
  }

$xml .= get_updates_xml_from_commits($projectname, $dates, $commits);

$xml .= "</cdash>";

generate_XSLT($xml, "viewChanges");
?>
