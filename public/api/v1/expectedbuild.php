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
require_once 'include/common.php';
include_once 'models/project.php';
include_once 'models/user.php';
$noforcelogin = 1;
include 'public/login.php';

// Connect to the database.
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

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

// Make sure the user has admin access to this project.
if (!isset($_SESSION['cdash']) || !isset($_SESSION['cdash']['loginid'])) {
    $response['requirelogin'] = 1;
    echo json_encode($response);
    return;
}
$row = pdo_single_row_query(
        "SELECT projectid FROM buildgroup WHERE id='$buildgroupid'");
if (!$row || !array_key_exists('projectid', $row)) {
    $response['error'] =
        "Could not find project for buildgroup #$buildgroupid";
    echo json_encode($response);
    return;
}
$projectid = $row['projectid'];
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

// Route based on what type of request this is.
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'DELETE':
        rest_delete();
        break;
    case 'POST':
        rest_post();
        break;
    default:
        add_log("Unhandled method: $method", 'expectedBuildAPI', LOG_WARNING);
        break;
}

/* Handle DELETE requests */
function rest_delete()
{
    global $siteid;
    global $buildgroupid;
    global $buildname;
    global $buildtype;

    pdo_query(
        "DELETE FROM build2grouprule
        WHERE groupid='$buildgroupid' AND
        buildtype='$buildtype' AND
        buildname='$buildname' AND siteid='$siteid' AND
        endtime='1980-01-01 00:00:00'");
}

/* Handle POST requests */
function rest_post()
{
    global $siteid;
    global $buildgroupid;
    global $buildname;
    global $buildtype;

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
