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
include(dirname(dirname(dirname(__DIR__)))."/config/config.php");
require_once("include/pdo.php");
include_once("include/common.php");
include('public/login.php');
include('include/version.php');
include("models/project.php");

$start = microtime_float();
$response = array();

@$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

$userid = $_SESSION['cdash']['loginid'];
// Checks
if (!isset($userid) || !is_numeric($userid)) {
    $response['requirelogin'] = '1';
    echo json_encode($response);
    return;
}

$response = begin_JSON_response();
$response['backurl'] = 'user.php';
$response['menutitle'] = 'CDash';
$response['menusubtitle'] = 'Build Groups';
$response['hidenav'] = 1;

@$projectid = $_GET["projectid"];
if ($projectid != null) {
    $projectid = pdo_real_escape_numeric($projectid);
}

// If the projectid is not set and there is only one project we go directly to the page
if (!isset($projectid)) {
    $project = pdo_query("SELECT id FROM project");
    if (pdo_num_rows($project)==1) {
        $project_array = pdo_fetch_array($project);
        $projectid = $project_array["id"];
    }
}

@$show = $_GET["show"];

$role=0;

$user_array = pdo_fetch_array(pdo_query(
  "SELECT admin FROM ".qid("user")." WHERE id='$userid'"));
if ($projectid && is_numeric($projectid)) {
    $user2project = pdo_query(
    "SELECT role FROM user2project
     WHERE userid='$userid' AND projectid='$projectid'");
    if (pdo_num_rows($user2project)>0) {
        $user2project_array = pdo_fetch_array($user2project);
        $role = $user2project_array["role"];
    }
}

if ($user_array["admin"]!=1 && $role<=1) {
    $response['error'] = "You don't have the permissions to access this page";
    echo json_encode($response);
    return;
}

// List the available projects that this user has admin rights to.
$sql = "SELECT id,name FROM project";
if ($user_array["admin"] != 1) {
    $sql .= " WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid='$userid' AND role>0)";
}
$projects = pdo_query($sql);
$availableprojects = array();
while ($project_array = pdo_fetch_array($projects)) {
    $availableproject = array();
    $availableproject['id'] = $project_array['id'];
    $availableproject['name'] = $project_array['name'];
    if ($project_array['id'] == $projectid) {
        $availableproject['selected'] = '1';
    }
    $availableprojects[] = $availableproject;
}
$response['availableprojects'] = $availableprojects;

if ($projectid < 1) {
    $end = microtime_float();
    $response['generationtime'] = round($end - $start, 3);
    echo json_encode($response);
    return;
}

// Find the recent builds for this project
$currentUTCTime =  gmdate(FMT_DATETIME);
$beginUTCTime = gmdate(FMT_DATETIME, time()-3600*7*24); // 7 days

$sql = "";
if ($show>0) {
    $sql = "AND g.id='$show'";
}

$builds = pdo_query("
  SELECT b.id, s.name AS sitename, s.id AS siteid, b.name, b.type,
         g.name as groupname, g.id as groupid
  FROM build AS b, build2group AS b2g, buildgroup AS g,
       buildgroupposition AS gp, site as s
  WHERE b.starttime<'$currentUTCTime' AND b.starttime>'$beginUTCTime' AND
        b.projectid='$projectid' AND b2g.buildid=b.id AND
        gp.buildgroupid=g.id AND b2g.groupid=g.id AND
        s.id = b.siteid ".$sql."
  ORDER BY b.name ASC");

$err = pdo_error();
if (!empty($err)) {
    $response['error'] = $err;
}

$build_names = array();
$currentbuilds = array();
$sites = array();
while ($build_array = pdo_fetch_array($builds)) {
    // Avoid adding the same build twice
  $build_name = $build_array['sitename'] . $build_array['name'] .
    $build_array['type'];
    if (!in_array($build_name, $build_names)) {
        $build_names[] = $build_name;

        $currentbuild = array();
        $currentbuild['id'] =  $build_array['id'];
        $currentbuild['name'] = $build_array['sitename'] . " " .
      $build_array['name'] . " [" .  $build_array['type'] . "] " .
      $build_array['groupname'];
        $currentbuild['groupid'] = $build_array['groupid'];
        $currentbuilds[] = $currentbuild;
    }
    $site = array();
    $site['id'] = $build_array['siteid'];
    $site['name'] = $build_array['sitename'];
    if (!in_array($site, $sites)) {
        $sites[] = $site;
    }
}

// Add expected builds
$builds = pdo_query(
  "SELECT b.id, s.name AS sitename, s.id AS siteid, b.name, b.type,
          g.name as groupname, g.id as groupid
   FROM site AS s, build AS b, build2group AS b2g, buildgroup AS g,
          build2grouprule AS b2gr
   WHERE g.id = b2g.groupid AND b2g.buildid = b.id AND
         b2gr.expected = 1 AND b2gr.groupid = g.id AND
         g.endtime='1980-01-01 00:00:00' AND b.projectid='$projectid' AND
         s.id = b.siteid ".$sql."
   ORDER BY b.name ASC");
$err = pdo_error();
if (!empty($err)) {
    $response['error'] = $err;
}

while ($build_array = pdo_fetch_array($builds)) {
    $build_name = $build_array['sitename'] . $build_array['name'] .
    $build_array['type'];
  // Avoid adding the same build twice
  if (!in_array($build_name, $build_names)) {
      $build_names[] = $build_name;
      $currentbuild = array();
      $currentbuild['id'] =  $build_array['id'];
      $currentbuild['name'] = $build_array['sitename'] . " " .
      $build_array['name'] . " [" . $build_array['type'] . "] " .
      $build_array['groupname']." (expected)";
      $currentbuilds[] = $currentbuild;
  }
    $site = array();
    $site['id'] = $build_array['siteid'];
    $site['name'] = $build_array['sitename'];
    if (!in_array($site, $sites)) {
        $sites[] = $site;
    }
}
$response['currentbuilds'] = $currentbuilds;
$response['sites'] = $sites;

// Get the BuildGroups for this Project.
$Project = new Project();
$Project->Id = $projectid;
$buildgroups = $Project->GetBuildGroups();
$buildgroups_response = array();
$dynamics_response = array();
foreach ($buildgroups as $buildgroup) {
    $buildgroup_response = array();

    if ($show == $buildgroup->GetId()) {
        $buildgroup_response['selected'] = '1';
    }

    $buildgroup_response['id'] = $buildgroup->GetId();
    $buildgroup_response['name'] = $buildgroup->GetName();
    $buildgroup_response['description'] = $buildgroup->GetDescription();
    $buildgroup_response['type'] = $buildgroup->GetType();
    $buildgroup_response['summaryemail'] = $buildgroup->GetSummaryEmail();
    $buildgroup_response['emailcommitters'] = $buildgroup->GetEmailCommitters();
    $buildgroup_response['includesubprojecttotal'] =
    $buildgroup->GetIncludeSubProjectTotal();
    $buildgroup_response['position'] = $buildgroup->GetPosition();
    $buildgroup_response['startdate'] = $buildgroup->GetStartTime();
    $buildgroup_response['autoremovetimeframe'] =
    $buildgroup->GetAutoRemoveTimeFrame();

    $buildgroups_response[] = $buildgroup_response;

    if ($buildgroup->GetType() != "Daily") {
        // Get the rules associated with this dynamic group.
    $dynamic_response = $buildgroup_response;
        $rules_result = pdo_query("
      SELECT * FROM build2grouprule
      WHERE groupid='".$dynamic_response['id']."'");
        $err = pdo_error();
        if (!empty($err)) {
            $response['error'] = $err;
        }

        $rules = array();
        while ($rule_array = pdo_fetch_array($rules_result)) {
            $rule = array();
            $match = $rule_array['buildname'];
            if (!empty($match)) {
                $match = trim($match, "%");
            }
            $rule['match'] = $match;

            $siteid = $rule_array['siteid'];
            if (empty($siteid)) {
                $rule['sitename'] = "Any";
                $rule['siteid'] = 0;
            } else {
                foreach ($sites as $site) {
                    if ($site['id'] == $siteid) {
                        $rule['sitename'] = $site['name'];
                        $rule['siteid'] = $site['id'];
                        break;
                    }
                }
            }

            $parentgroupid = $rule_array['parentgroupid'];
            if (empty($parentgroupid)) {
                $rule['parentgroupname'] = "Any";
                $rule['parentgroupid'] = 0;
            } else {
                foreach ($buildgroups as $buildgroup) {
                    if ($buildgroup->GetId() == $parentgroupid) {
                        $rule['parentgroupname'] = $buildgroup->GetName();
                        $rule['parentgroupid'] = $parentgroupid;
                        break;
                    }
                }
            }

            $rules[] = $rule;
        }
        if (!empty($rules)) {
            $dynamic_response['rules'] = $rules;
        }
        $dynamics_response[] = $dynamic_response;
    }
}
$response['buildgroups'] = $buildgroups_response;
$response['dynamics'] = $dynamics_response;

// Store some additional details about this project.
$project_response = array();
$project_response['id'] = $projectid;
$project_name = $Project->GetName();
$project_response['name'] = $project_name;
$project_response['name_encoded'] = urlencode($project_name);
$response['project'] = $project_response;

// Generate response for any wildcard groups.
$wildcards = pdo_query("
  SELECT bg.name, bg.id, b2gr.buildtype, b2gr.buildname
  FROM build2grouprule AS b2gr, buildgroup AS bg
  WHERE b2gr.buildname LIKE '\%%\%' AND b2gr.groupid = bg.id AND
        bg.type = 'Daily' AND bg.projectid='$projectid'");
$err = pdo_error();
if (!empty($err)) {
    $response['error'] = $err;
}

$wildcards_response = array();
while ($wildcard_array = pdo_fetch_array($wildcards)) {
    $wildcard_response = array();
    $wildcard_response['buildgroupname'] = $wildcard_array['name'];
    $wildcard_response['buildgroupid'] = $wildcard_array['id'];
    $wildcard_response['buildtype'] = $wildcard_array['buildtype'];

    $match = $wildcard_array['buildname'];
    $match = str_replace("%", "", $match);
    $wildcard_response['match'] = $match;

    $wildcards_response[] = $wildcard_response;
}

$response['wildcards'] = $wildcards_response;

$end = microtime_float();
$response['generationtime'] = round($end - $start, 3);
echo json_encode(cast_data_for_JSON($response));

?>
