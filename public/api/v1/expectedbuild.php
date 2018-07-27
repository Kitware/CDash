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

include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
require_once 'include/api_common.php';

use CDash\Model\Project;
use CDash\Model\User;

// Check that required params were specified.
$rest_json = json_decode(file_get_contents('php://input'), true);
if (!is_null($rest_json)) {
    $_REQUEST = array_merge($_REQUEST, $rest_json);
}
$required_params = array('siteid', 'groupid', 'name', 'type');
foreach ($required_params as $param) {
    if (!array_key_exists($param, $_REQUEST)) {
        $response['error'] = "$param not specified.";
        echo json_encode($response);
        return;
    }
}
$siteid = pdo_real_escape_numeric($_REQUEST['siteid']);
$buildgroupid = pdo_real_escape_numeric($_REQUEST['groupid']);
$buildname = htmlspecialchars(pdo_real_escape_string($_REQUEST['name']));
$buildtype = htmlspecialchars(pdo_real_escape_string($_REQUEST['type']));

// Make sure the user has access to this project.
$row = pdo_single_row_query(
        "SELECT projectid FROM buildgroup WHERE id='$buildgroupid'");
if (!$row || !array_key_exists('projectid', $row)) {
    $response['error'] =
        "Could not find project for buildgroup #$buildgroupid";
    echo json_encode($response);
    return;
}
$projectid = $row['projectid'];
if (!can_access_project($projectid)) {
    return;
}

$method = $_SERVER['REQUEST_METHOD'];

// Make sure the user is an admin before proceeding with non-read-only methods.
if ($method != 'GET') {
    if (!isset($_SESSION['cdash']) || !isset($_SESSION['cdash']['loginid'])) {
        $response['error'] = 'No session found.';
        echo json_encode($response);
        return;
    }
    $userid = pdo_real_escape_numeric($_SESSION['cdash']['loginid']);

    $Project = new Project;
    $User = new User;
    $User->Id = $userid;
    $Project->Id = $projectid;

    $role = $Project->GetUserRole($userid);
    if ($User->IsAdmin() === false && $role <= 1) {
        $response['error'] = 'You do not have permission to access this page';
        echo json_encode($response);
        return;
    }
}

// Route based on what type of request this is.
switch ($method) {
    case 'DELETE':
        rest_delete($siteid, $buildgroupid, $buildname, $buildtype);
        break;
    case 'GET':
        rest_get($siteid, $buildgroupid, $buildname, $buildtype, $projectid);
        break;
    case 'POST':
        rest_post($siteid, $buildgroupid, $buildname, $buildtype);
        break;
    default:
        add_log("Unhandled method: $method", 'expectedBuildAPI', LOG_WARNING);
        break;
}

/* Handle DELETE requests */
function rest_delete($siteid, $buildgroupid, $buildname, $buildtype)
{
    pdo_query(
        "DELETE FROM build2grouprule
        WHERE groupid='$buildgroupid' AND
        buildtype='$buildtype' AND
        buildname='$buildname' AND siteid='$siteid' AND
        endtime='1980-01-01 00:00:00'");
}

/* Handle GET requests */
function rest_get($siteid, $buildgroupid, $buildname, $buildtype, $projectid)
{
    $response = array();

    if (!array_key_exists('currenttime', $_REQUEST)) {
        $response['error'] = "currenttime not specified.";
        echo json_encode($response);
        return;
    }
    $currenttime = pdo_real_escape_numeric($_REQUEST['currenttime']);
    $currentUTCtime = gmdate(FMT_DATETIME, $currenttime);

    // Find the last time this expected build submitted.
    $last_build_row = pdo_single_row_query(
            "SELECT starttime FROM build
            WHERE siteid='$siteid' AND type='$buildtype' AND name='$buildname'
            AND projectid='$projectid' AND starttime<='$currentUTCtime'
            ORDER BY starttime DESC LIMIT 1");
    if (!$last_build_row || !array_key_exists('starttime', $last_build_row)) {
        $response['lastSubmission'] = -1;
        echo json_encode($response);
        return;
    }

    $lastBuildDate = $last_build_row['starttime'];
    $gmtime = strtotime($lastBuildDate . ' UTC');
    $response['lastSubmission'] = date('M j, Y ', $gmtime);
    $response['lastSubmissionDate'] = date('Y-m-d', $gmtime);
    $response['daysSinceLastBuild'] =
        round(($currenttime - strtotime($lastBuildDate)) / (3600 * 24));

    echo json_encode(cast_data_for_JSON($response));
}

/* Handle POST requests */
function rest_post($siteid, $buildgroupid, $buildname, $buildtype)
{
    if (!array_key_exists('newgroupid', $_REQUEST)) {
        $response = array();
        $response['error'] = 'newgroupid not specified.';
        echo json_encode($response);
        return;
    }

    $newgroupid =
        htmlspecialchars(pdo_real_escape_string($_REQUEST['newgroupid']));

    // Change the group that this rule points to.
    pdo_query(
        "UPDATE build2grouprule SET groupid='$newgroupid'
        WHERE groupid='$buildgroupid' AND
        buildtype='$buildtype' AND
        buildname='$buildname' AND siteid='$siteid' AND
        endtime='1980-01-01 00:00:00'");

    // Move any builds that follow this rule to the new group.
    pdo_query(
        "UPDATE build2group SET groupid='$newgroupid'
        WHERE groupid='$buildgroupid' AND buildid IN
        (SELECT id FROM build WHERE siteid='$siteid' AND
         name='$buildname' AND type='$buildtype')");
}
