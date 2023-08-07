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

namespace CDash\Api\v1\ManageBuildGroup;




use App\Services\PageTimer;
use CDash\Database;
use CDash\Model\BuildGroup;
use CDash\Model\Project;
use App\Models\Site;
use CDash\Model\UserProject;
use Illuminate\Support\Facades\Auth;

$pageTimer = new PageTimer();
$response = [];

// Checks
if (!Auth::check()) {
    $response['requirelogin'] = '1';
    echo json_encode($response);
    return;
}
$user = Auth::user();
$userid = $user->id;

$response = begin_JSON_response();
$response['backurl'] = 'user.php';
$response['menutitle'] = 'CDash';
$response['menusubtitle'] = 'Build Groups';
$response['title'] = 'Build Groups';
$response['hidenav'] = 1;

@$projectid = $_GET['projectid'];
if (isset($projectid)) {
    $projectid = intval($projectid);
}
$db = Database::getInstance();

// If the projectid is not set and there is only one project we go directly to the page
if (!isset($projectid)) {
    $project_array = $db->executePreparedSingleRow('SELECT id FROM project');
    if (!empty($project)) {
        $projectid = intval($project_array['id']);
    }
}

@$show = $_GET['show'];

if ($projectid && is_numeric($projectid)) {
    $user2project = new UserProject();
    $user2project->UserId = $userid;
    $user2project->ProjectId = $projectid;
    $user2project->FillFromUserId();
}

if (!$user->IsAdmin() && $user2project->Role <= 1) {
    $response['error'] = "You don't have the permissions to access this page";
    echo json_encode($response);
    return;
}

// List the available projects that this user has admin rights to.
$sql = 'SELECT id,name FROM project';
$params = [];
if (!$user->IsAdmin()) {
    $sql .= " WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid=? AND role>0)";
    $params[] = intval($userid);
}
$projects = $db->executePrepared($sql, $params);
$availableprojects = [];
foreach ($projects as $project_array) {
    $availableproject =[];
    $availableproject['id'] = $project_array['id'];
    $availableproject['name'] = $project_array['name'];
    if ($project_array['id'] == $projectid) {
        $availableproject['selected'] = '1';
    }
    $availableprojects[] = $availableproject;
}
$response['availableprojects'] = $availableprojects;

if (intval($projectid) < 1) {
    $pageTimer->end($response);
    echo json_encode($response);
    return;
}

// Find sites that have recently submitted to this project.
$currentUTCTime = gmdate(FMT_DATETIME);
$beginUTCTime = gmdate(FMT_DATETIME, time() - 3600 * 7 * 24); // 7 days

$stmt = $db->prepare(
    'SELECT DISTINCT b.siteid, s.name
    FROM build b
    JOIN site s ON (b.siteid=s.id)
    WHERE projectid=:projectid AND
    starttime BETWEEN :start AND :end AND
    parentid IN (-1, 0)');
$stmt->bindParam(':projectid', $projectid);
$stmt->bindParam(':start', $beginUTCTime);
$stmt->bindParam(':end', $currentUTCTime);
if (!$db->execute($stmt)) {
    $response['error'] = 'Database error during site lookup';
}

$sites = [];
while ($row = $stmt->fetch()) {
    $site = [];
    $site['id'] = $row['siteid'];
    $site['name'] = $row['name'];
    $sites[] = $site;
}

$response['sites'] = $sites;

// Get the BuildGroups for this Project.
$Project = new Project();
$Project->Id = $projectid;
$buildgroups = $Project->GetBuildGroups();
$buildgroups_response = array();
$dynamics_response = array();
/** @var BuildGroup $buildgroup */
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

    if ($buildgroup->GetType() != 'Daily') {
        // Get the rules associated with this dynamic group.
        $dynamic_response = $buildgroup_response;
        $rules = $buildgroup->GetRules();
        $rules_response = [];
        foreach ($rules as $rule) {
            $rule_response = [];
            $match = $rule->BuildName;
            if (!empty($match)) {
                $match = trim($match, '%');
                $match = str_replace('%', '*', $match);
            }
            $rule_response['match'] = $match;

            $siteid = $rule->SiteId;
            if (empty($siteid)) {
                $rule_response['sitename'] = 'Any';
                $rule_response['siteid'] = 0;
            } else {
                $rule_response['siteid'] = $siteid;
                $found = false;
                foreach ($sites as $site) {
                    if ($site['id'] == $siteid) {
                        $rule_response['sitename'] = $site['name'];
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $site = Site::find($siteid);
                    $rule_response['sitename'] = $site->name;
                }
            }

            $parentgroupid = $rule->ParentGroupId;
            if (empty($parentgroupid)) {
                $rule_response['parentgroupname'] = 'Any';
                $rule_response['parentgroupid'] = 0;
            } else {
                foreach ($buildgroups as $buildgroup) {
                    if ($buildgroup->GetId() == $parentgroupid) {
                        $rule_response['parentgroupname'] = $buildgroup->GetName();
                        $rule_response['parentgroupid'] = $parentgroupid;
                        break;
                    }
                }
            }

            $rules_response[] = $rule_response;
        }
        if (!empty($rules_response)) {
            $dynamic_response['rules'] = $rules_response;
        }
        $dynamics_response[] = $dynamic_response;
    }
}
$response['buildgroups'] = $buildgroups_response;
$response['dynamics'] = $dynamics_response;

// Store some additional details about this project.
get_dashboard_JSON($Project->GetName(), null, $response);

// Generate response for any wildcard groups.
$wildcards = $db->executePrepared("
                 SELECT
                     bg.name,
                     bg.id,
                     b2gr.buildtype,
                     b2gr.buildname
                 FROM
                     build2grouprule AS b2gr,
                     buildgroup AS bg
                 WHERE
                     b2gr.buildname LIKE '\%%\%'
                     AND b2gr.groupid = bg.id
                     AND bg.type = 'Daily'
                     AND bg.projectid=?
                     AND b2gr.endtime = '1980-01-01 00:00:00'
             ", [intval($projectid)]);
$err = pdo_error();
if (!empty($err)) {
    $response['error'] = $err;
}

$wildcards_response = array();
foreach ($wildcards as $wildcard_array) {
    $wildcard_response = array();
    $wildcard_response['buildgroupname'] = $wildcard_array['name'];
    $wildcard_response['buildgroupid'] = $wildcard_array['id'];
    $wildcard_response['buildtype'] = $wildcard_array['buildtype'];

    $match = $wildcard_array['buildname'];
    $match = trim($match, '%');
    $match = str_replace('%', '*', $match);
    $wildcard_response['match'] = $match;

    $wildcards_response[] = $wildcard_response;
}

$response['wildcards'] = $wildcards_response;

$pageTimer->end($response);
echo json_encode(cast_data_for_JSON($response));
