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

use CDash\Database;
use CDash\Model\BuildGroup;
use CDash\Model\BuildGroupRule;

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
$buildgroup = new BuildGroup();
if (!$buildgroup->SetId($buildgroupid)) {
    json_error_response(
        ['error' => "Could not find project for buildgroup #$buildgroupid"]);
}
$projectid = $buildgroup->GetProjectId();
if (!can_access_project($projectid)) {
    return;
}

$method = $_SERVER['REQUEST_METHOD'];

// Make sure the user is an admin before proceeding with non-read-only methods.
if ($method != 'GET' && !can_administrate_project($projectid)) {
    return;
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
    $rule = new BuildGroupRule();
    $rule->SiteId = $siteid;
    $rule->GroupId = $buildgroupid;
    $rule->BuildName = $buildname;
    $rule->BuildType = $buildtype;
    $rule->Delete();
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
    $db = Database::getInstance();
    $stmt = $db->prepare(
        'SELECT starttime FROM build
        WHERE siteid    = :siteid AND
              type      = :buildtype AND
              name      = :buildname AND
              projectid = :projectid AND
              starttime <= :starttime
        ORDER BY starttime DESC LIMIT 1');
    $query_params = [
        ':siteid'    => $siteid,
        ':buildtype' => $buildtype,
        ':buildname' => $buildname,
        ':projectid' => $projectid,
        ':starttime' => $currentUTCtime
    ];
    $db->execute($stmt, $query_params);
    $lastBuildDate = $stmt->fetchColumn();
    if ($lastBuildDate === false) {
        $response['lastSubmission'] = -1;
        echo json_encode($response);
        return;
    }

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

    $rule = new BuildGroupRule();
    $rule->SiteId = $siteid;
    $rule->GroupId = $buildgroupid;
    $rule->BuildName = $buildname;
    $rule->BuildType = $buildtype;
    $rule->ChangeGroup($newgroupid);
}
