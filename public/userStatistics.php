<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

$noforcelogin = 1;
include_once(dirname(__DIR__)."/config/config.php");
require_once("include/pdo.php");
include('public/login.php');
include('include/version.php');

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

@$projectid = $_GET["projectid"];
if ($projectid != null) {
    $projectid = pdo_real_escape_numeric($projectid);
}

if (!isset($projectid) || !is_numeric($projectid)) {
    echo "Project name is not set";
    return 0;
}

checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid);
$projectname = get_project_name($projectid);
$project_array = pdo_fetch_array(pdo_query("SELECT name,nightlytime FROM project WHERE id='$projectid'"));

$xml = begin_XML_for_XSLT();
$xml .= "<backurl>user.php</backurl>";
$xml .= "<title>CDash - Developpers Statistics</title>";
$xml .= "<menutitle>User Statistics</menutitle>";
$xml .= "<menusubtitle>".$projectname."</menusubtitle>";

$project = pdo_query("SELECT id,name FROM project WHERE id='$projectid'");
$project_array = pdo_fetch_array($project);
$xml .= "<project>";
$xml .= add_XML_value("id", $project_array['id']);
$xml .= add_XML_value("name", $project_array['name']);
$xml .= "</project>";

$range = "thisweek";
if (isset($_POST["range"])) {
    $range = $_POST["range"];
    $xml .= add_XML_value("datarange", $range);
}

// Find the list of the best submitters for the project
$now = time();

if ($range=="thisweek") {
    // find the current day of the week
  $day = date("w");
    $end = $now;
    $beginning = $now-$day*3600*24;
} elseif ($range=="lastweek") {
    // find the current day of the week
  $day = date("w");
    $end = $now-$day*3600*24;
    $beginning = $end-7*3600*24;
} elseif ($range=="thismonth") {
    // find the current day of the month
  $day = date("j");
    $end = $now;
    $beginning = $now-$day*3600*24;
} elseif ($range=="lastmonth") {
    // find the current day of the month
  $day = date("j");
    $end = $now-$day*3600*24;
    $beginning = $end-30*3600*24; // assume 30 days months
} elseif ($range=="thisyear") {
    // find the current day of the month
  $day = date("z");
    $beginning = $now-$day*3600*24;
    $end = $now;
} elseif ($range=="lastyear") {
    $currentyear = date("Y");
    $beginning = mktime(0, 0, 0, 1, 1, $currentyear-1);
    $end = mktime(0, 0, 0, 12, 31, $currentyear-1);
}

$beginning_UTCDate = gmdate(FMT_DATETIME, $beginning);
$end_UTCDate = gmdate(FMT_DATETIME, $end);

$endselect = "SELECT f.userid, f.checkindate, f.totalbuilds, f.nfixedwarnings,
                     f.nfailedwarnings, f.nfixederrors, f.nfailederrors,
                     f.nfixedtests, f.nfailedtests, f.totalupdatedfiles
  FROM (
     select userid, max(checkindate) as checkindate
     from userstatistics WHERE checkindate<'$end_UTCDate' AND checkindate>='$beginning_UTCDate' AND projectid='$projectid' group by userid
  ) as x inner join userstatistics as f on f.userid=x.userid AND f.checkindate=x.checkindate";

$startselect = "SELECT f.userid, f.checkindate, f.totalbuilds, f.nfixedwarnings,
                         f.nfailedwarnings, f.nfixederrors, f.nfailederrors,
                         f.nfixedtests, f.nfailedtests, f.totalupdatedfiles
  FROM (
     select userid, max(checkindate) as checkindate
     from userstatistics WHERE checkindate<'$beginning_UTCDate' AND projectid='$projectid' group by userid
  ) as x inner join userstatistics as f on f.userid=x.userid AND f.checkindate=x.checkindate";

// First loop through the endselect
$users = array();
$endquery = pdo_query($endselect);
while ($endquery_array = pdo_fetch_array($endquery)) {
    $user = array();
    $user['nfailedwarnings'] = $endquery_array['nfailedwarnings'];
    $user['nfixedwarnings'] = $endquery_array['nfixedwarnings'];
    $user['nfailederrors'] = $endquery_array['nfailederrors'];
    $user['nfixederrors'] = $endquery_array['nfixederrors'];
    $user['nfailedtests'] = $endquery_array['nfailedtests'];
    $user['nfixedtests'] = $endquery_array['nfixedtests'];
    $user['totalbuilds'] = $endquery_array['totalbuilds'];
    $user['totalupdatedfiles'] = $endquery_array['totalupdatedfiles'];
    $users[$endquery_array['userid']] = $user;
}

$startquery = pdo_query($startselect);
while ($startquery_array = pdo_fetch_array($startquery)) {
    if (isset($users[$startquery_array['userid']])) {
        $users[$startquery_array['userid']]['nfailedwarnings'] -= $startquery_array['nfailedwarnings'];
        $users[$startquery_array['userid']]['nfixedwarnings'] -= $startquery_array['nfixedwarnings'];
        $users[$startquery_array['userid']]['nfailederrors'] -= $startquery_array['nfailederrors'];
        $users[$startquery_array['userid']]['nfixederrors'] -= $startquery_array['nfixederrors'];
        $users[$startquery_array['userid']]['nfailedtests'] -= $startquery_array['nfailedtests'];
        $users[$startquery_array['userid']]['nfixedtests'] -= $startquery_array['nfixedtests'];
        $users[$startquery_array['userid']]['totalbuilds'] -= $startquery_array['totalbuilds'];
        $users[$startquery_array['userid']]['totalupdatedfiles'] -= $startquery_array['totalupdatedfiles'];
    }
}

// Compute the total score
$alpha_warning = 0.3;
$alpha_error = 0.4;
$alpha_test = 0.3;

$weight = (1-$alpha_warning)+(1-$alpha_error)+(1-$alpha_test);

$max['nfailederrors'] = 1;
$max['nfixederrors'] = 1;
$max['nfailedwarnings'] = 1;
$max['nfixedwarnings'] = 1;
$max['nfailedtests'] = 1;
$max['nfixedtests'] = 1;

foreach ($users as $key=>$user) {
    if ($user['totalbuilds']==0) {
        $users[$key]['totalbuilds'] = 1;
    }
    $users[$key]['nfailederrors'] = abs(round($user['nfailederrors']/$user['totalbuilds']));
    $users[$key]['nfixederrors'] = abs(round($user['nfixederrors']/$user['totalbuilds']));
    $users[$key]['nfailedwarnings'] = abs(round($user['nfailedwarnings']/$user['totalbuilds']));
    $users[$key]['nfixedwarnings'] = abs(round($user['nfixedwarnings']/$user['totalbuilds']));
    $users[$key]['nfailedtests'] = abs(round($user['nfailedtests']/$user['totalbuilds']));
    $users[$key]['nfixedtests'] = abs(round($user['nfixedtests']/$user['totalbuilds']));
    $users[$key]['totalupdatedfiles'] = abs(round($user['totalupdatedfiles']/$user['totalbuilds']));

    foreach ($max as $mk => $mv) {
        if ($mv<$users[$key][$mk]) {
            $max[$mk]=$users[$key][$mk];
        }
    }
}

foreach ($users as $key=>$user) {
    $xml .= "<user>";
    $user_array = pdo_fetch_array(pdo_query("SELECT firstname,lastname FROM ".qid("user")." WHERE id=".qnum($key)));

    $xml .= add_XML_value("name", $user_array['firstname']." ".$user_array['lastname']);
    $xml .= add_XML_value("id", $key);
    $scorep=$alpha_test*$user['nfixedtests']/$max['nfixedtests'];
    $scorep+=$alpha_error*$user['nfixederrors']/$max['nfixederrors'];
    $scorep+=$alpha_warning*$user['nfixedwarnings']/$max['nfixedwarnings'];
  // weights for scorep should be 1

  $scoren=(1-$alpha_test)*$user['nfailedtests']/$max['nfailedtests'];
    $scoren+=(1-$alpha_error)*$user['nfailederrors']/$max['nfailederrors'];
    $scoren+=(1-$alpha_warning)*$user['nfailedwarnings']/$max['nfailedwarnings'];
    $score = $scorep-$scoren/$weight;

    $xml .= add_XML_value("score", round($score, 3));
    $xml .= add_XML_value("failed_errors", $user['nfailederrors']);
    $xml .= add_XML_value("fixed_errors", $user['nfixederrors']);
    $xml .= add_XML_value("failed_warnings", $user['nfailedwarnings']);
    $xml .= add_XML_value("fixed_warnings", $user['nfixedwarnings']);
    $xml .= add_XML_value("failed_tests", $user['nfailedtests']);
    $xml .= add_XML_value("fixed_tests", $user['nfixedtests']);
    $xml .= add_XML_value("totalupdatedfiles", $user['totalupdatedfiles']);
    $xml .= "</user>";
}

// order by score by default
$xml .= add_XML_value("sortlist", "{sortlist: [[1,1]]}"); // score
$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml, "userStatistics");
