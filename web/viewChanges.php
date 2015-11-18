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
include(dirname(__DIR__)."/config/config.php");
require_once("include/pdo.php");
include('web/login.php');
include_once("include/common.php");
include_once("include/repository.php");
include("include/version.php");
require_once("include/bugurl.php");

// get_related_dates takes a projectname and basedate as input
// and produces an array of related dates and times based on:
// the input, the project's nightly start time, now
//
function get_related_dates($projectname, $basedate)
{
    include(dirname(__DIR__)."/config/config.php");
    require_once("include/pdo.php");

    $dates = array();

    $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
    pdo_select_db("$CDASH_DB_NAME", $db);

    $dbQuery = pdo_query("SELECT nightlytime FROM project WHERE name='$projectname'");
    if (pdo_num_rows($dbQuery)>0) {
        $project = pdo_fetch_array($dbQuery);
        $nightlytime = $project['nightlytime'];
    //echo "query result nightlytime: " . $nightlytime . "<br/>";
    } else {
        $nightlytime = "00:00:00";
    //echo "default nightlytime: " . $nightlytime . "<br/>";
    }

    if (!isset($basedate) || strlen($basedate)==0) {
        $basedate = gmdate(FMT_DATE);
    }

  // Convert the nightly time into GMT
  $nightlytime = gmdate(FMT_TIME, strtotime($nightlytime));

    $nightlyhour = time2hour($nightlytime);
    $nightlyminute = time2minute($nightlytime);
    $nightlysecond = time2second($nightlytime);
    $basemonth = date2month($basedate);
    $baseday = date2day($basedate);
    $baseyear = date2year($basedate);

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
    $currentgmdate = gmdate(FMT_DATE, $currentgmtime);

  // Find the most recently past nightly time:
  //
  $todaymonth = date2month($currentgmdate);
    $todayday = date2day($currentgmdate);
    $todayyear = date2year($currentgmdate);
    $currentnightly = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
    $todaymonth, $todayday, $todayyear);
    while ($currentnightly>$currentgmtime) {
        $todayday = $todayday - 1;
        $currentnightly = gmmktime($nightlyhour, $nightlyminute, $nightlysecond,
      $todaymonth, $todayday, $todayyear);
    }

    $dates['now'] = $currentgmtime;
    $dates['most-recent-nightly'] = $currentnightly;
    $dates['today_utc'] = $currentgmdate;
    $dates['basedate'] = gmdate(FMT_DATE, $dates['nightly-0']);

  // CDash equivalent of DART1's "last rollup time"
  if ($dates['basedate'] === $dates['today_utc']) {
      // If it's today, it's now:
    $dates['last-rollup-time'] = $dates['now'];
  } else {
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
    if ($d1<$d2) {
        return -1;
    }
    if ($d1>$d2) {
        return 1;
    }

  // Sort file names lexicographically in ascending order
  // (A, B, C, ... Z)
  //
  $f1 = $e1['filename'];
    $f2 = $e2['filename'];
    if ($f1<$f2) {
        return -1;
    }
    if ($f1>$f2) {
        return 1;
    }

  // Sort time stamps numerically in descending order
  // (newer changes before older changes)
  //
  $t1 = $e1['time'];
    $t2 = $e2['time'];
    if ($t1<$t2) {
        return 1;
    }
    if ($t1>$t2) {
        return -1;
    }

  // Identical entries:
  //
  return 0;
}

function get_updates_xml_from_commits($projectname, $projectid, $dates, $commits)
{
    $xml = "<updates>\n";
    $xml .= "<timestamp>".date(FMT_DATETIMETZ, $dates['nightly-0'])."</timestamp>";


  // Get revision numbers for the current day and "the last time it ran before that..."
  // Only works if the LIMIT 2 query below returns exactly 2 records and the date from
  // the most recent record matches the current 'nightly-0' date... If those criteria
  // are not met, the revision strings will be empty and no revision information will
  // be displayed on the resulting web page.
  //
  $revision_current = '';
    $revision_prior = '';

    $qry = "SELECT date, revision FROM dailyupdate ".
           "WHERE projectid='$projectid' ".
           "  AND date <= '".gmdate(FMT_DATE, $dates['nightly-0'])."' ".
           "ORDER BY date DESC LIMIT 2";
    $rows = pdo_all_rows_query($qry);
    if (count($rows) == 2) {
        if ($rows[0]['date'] == gmdate(FMT_DATE, $dates['nightly-0'])) {
            $revision_current = $rows[0]['revision'];
            $revision_prior = $rows[1]['revision'];
        }
    }

    $xml .= add_XML_value("revision", $revision_current);
    $xml .= add_XML_value("priorrevision", $revision_prior);
    $xml .= add_XML_value("revisionurl", get_revision_url($projectid, $revision_current, $revision_prior));
    $xml .= add_XML_value("revisiondiff", get_revision_url($projectid, $revision_prior, '')); // no prior prior revision...


  $xml .= "<javascript>\n";

  // Args to dbAdd : "true" means directory, "false" means file
  //
  $xml .= "dbAdd(true, \"Updated files  (".count($commits).")\", \"\", 0, \"\", \"1\", \"\", \"\", \"\", \"\", \"\")\n";

    $previousdir = "";

    usort($commits, "sort_by_directory_file_time");

    $projecturl = get_project_property($projectname, "cvsurl");

    foreach ($commits as $commit) {
        $directory = $commit['directory'];

        if ($directory != $previousdir) {
            $xml .= "dbAdd(true, \"".$directory."\", \"\", 1, \"\", \"1\", \"\", \"\", \"\", \"\", \"\")\n";
            $previousdir = $directory;
        }

        $filename = $commit['filename'];
        $revision = '';
        if ($commit['priorrevision'] != "-1") {
            $revision = $commit['revision'];
        }
        $time = gmdate(FMT_DATETIME, strtotime($commit['time']));
        $author = $commit['author'];

    // Only display email if the user is logged in
    if (isset($_SESSION['cdash'])) {
        if (isset($commit['email'])) {
            $email = $commit['email'];
        } else {
            $email = get_author_email($projectname, $author);
        }
    } else {
        // If the author is an email (git for instance) we remove everything after the @
      $posat = strpos($author, '@');
        if ($posat !== false) {
            $author = substr($author, 0, $posat);
        }
        $email = "";
    }
        $comment = $commit['comment'];
        $comment = str_replace("\n", " ", $comment);
    // Do this twice so that <something> ends up as
    // &amp;lt;something&amp;gt; because it gets sent to a
    // java script function not just displayed as html
    $comment = XMLStrFormat($comment);
        $comment = XMLStrFormat($comment);

        $diff_url = get_diff_url(get_project_id($projectname), $projecturl, $directory, $filename, $revision);
        $diff_url = XMLStrFormat($diff_url);

        $xml .= "dbAdd(false, \"".$filename."  Revision: ".$revision."\",\"".$diff_url."\",2,\"\",\"1\",\"".$author."\",\"".$email."\",\"".$comment."\",\"".$commit['bugurl']."\",\"".$commit['bugid']."\",\"".$commit['bugpos']."\")\n";
    }

    $xml .= "</javascript>\n";
    $xml .= "</updates>";

    return $xml;
}


// Repository nightly queries are for the 24 hours leading up to the
// nightly start time for "$projectname" on "$date"
@$projectname = $_GET["project"];
if ($projectname != null) {
    $projectname = htmlspecialchars(pdo_real_escape_string($projectname));
}

@$date = $_GET["date"];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

$project = pdo_query("SELECT id,nightlytime,bugtrackerurl,bugtrackerfileurl FROM project WHERE name='$projectname'");
$project_array = pdo_fetch_array($project);

$projectid = $project_array["id"];

checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid);

$dates = get_related_dates($projectname, $date);

$xml = begin_XML_for_XSLT();
$xml .= "<title>CDash : ".$projectname."</title>";

$gmdate = gmdate(FMT_DATE, $dates['nightly-0']);

$nightlytime = $project_array["nightlytime"];
$xml .= get_cdash_dashboard_xml_by_name($projectname, $date);
list($previousdate, $currentstarttime, $nextdate, $today) = get_dates($date, $nightlytime);
$xml .= "<menu>";
$xml .= add_XML_value("previous", "viewChanges.php?project=".urlencode($projectname)."&date=".$previousdate);
if ($date!="" && date(FMT_DATE, $currentstarttime)!=date(FMT_DATE)) {
    $xml .= add_XML_value("next", "viewChanges.php?project=".urlencode($projectname)."&date=".$nextdate);
} else {
    $xml .= add_XML_value("nonext", "1");
}
$xml .= add_XML_value("current", "viewChanges.php?project=".urlencode($projectname)."&date=");

$xml .= add_XML_value("back", "index.php?project=".urlencode($projectname)."&date=".$today);

$xml .= add_XML_value("back", "index.php?project=".urlencode($projectname)."&date=".get_dashboard_date_from_project($projectname, $date));
$xml .= "</menu>";

$dailyupdate = pdo_query("SELECT df.filename,df.revision,df.priorrevision,df.author,df.email,df.log,df.checkindate
                            FROM dailyupdatefile AS df,dailyupdate AS du
                            WHERE du.date='$gmdate' and du.projectid='$projectid'
                            AND df.dailyupdateid = du.id");
$commits = array();
while ($dailyupdate_array = pdo_fetch_array($dailyupdate)) {
    $commit = array();
    $current_directory = dirname($dailyupdate_array['filename']);
    $current_filename = basename($dailyupdate_array['filename']);
    $current_revision = $dailyupdate_array['revision'];

    if ($current_directory == '\\') {
        $current_directory = '/';
    }

    $baseurl = $project_array["bugtrackerfileurl"];
    if (empty($baseurl)) {
        $baseurl = $project_array["bugtrackerurl"];
    }

    $commit['directory'] = $current_directory;
    $commit['filename'] = $current_filename;
    $commit['revision'] = $current_revision;
    $commit['priorrevision'] = $dailyupdate_array['priorrevision'];
    $commit['time'] = $dailyupdate_array['checkindate'];
    $commit['author'] = $dailyupdate_array['author'];
    $commit['email'] = $dailyupdate_array['email'];
    $commit['comment'] = $dailyupdate_array['log'];
    $commit['bugurl'] = "";
    $commit['bugid'] = "";
    $commit['bugpos'] = "";

    $log = $commit['comment'];
    $bug = get_bug_from_log($log, $baseurl);
    if ($bug !== false) {
        $commit['bugurl'] = $bug[0];
        $commit['bugid'] = $bug[1];
        $commit['bugpos'] = $bug[2];
    }

    $commits[$current_directory . "/" . $current_filename . ";" . $current_revision] = $commit;
}

$xml .= get_updates_xml_from_commits($projectname, $projectid, $dates, $commits);

$xml .= "</cdash>";

generate_XSLT($xml, "viewChanges");
