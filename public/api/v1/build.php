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
include_once 'models/project.php';
include_once 'models/user.php';

init_api_request();
$response = array();

// Connect to database.
@$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

$build = get_request_build();

$method = $_SERVER['REQUEST_METHOD'];
// Make sure the user is an admin before procedding with non-read-only methods.
if ($method != 'GET') {
    $userid = $_SESSION['cdash']['loginid'];
    if (!isset($userid) || !is_numeric($userid)) {
        $response['error'] = 'Not a valid userid!';
        echo json_encode($response);
        return;
    }

    $Project = new Project;
    $User = new User;
    $User->Id = $userid;
    $Project->Id = $build->ProjectId;

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
        rest_delete();
        break;
    case 'POST':
        rest_post();
        break;
    case 'PUT':
        rest_put();
        break;
    case 'GET':
    default:
        rest_get();
        break;
}

/* Handle DELETE requests */
function rest_delete()
{
    global $build;
    add_log('Build #' . $build->Id . ' removed manually', 'buildAPI');
    remove_build($build->Id);
}

/* Handle POST requests */
function rest_post()
{
    /** @var Build $build */
    global $build;

    $buildtype = $build->Type;
    $buildname = $build->Name;
    $siteid = $build->SiteId;

    // Should we change whether or not this build is expected?
    if (isset($_POST['expected']) && isset($_POST['groupid'])) {
        $expected = pdo_real_escape_numeric($_POST['expected']);
        $groupid = pdo_real_escape_numeric($_POST['groupid']);

        // If a rule already exists we update it.
        $build2groupexpected = pdo_query(
            "SELECT groupid FROM build2grouprule
                WHERE groupid='$groupid' AND buildtype='$buildtype' AND
                buildname='$buildname' AND siteid='$siteid' AND
                endtime='1980-01-01 00:00:00'");
        if (pdo_num_rows($build2groupexpected) > 0) {
            pdo_query(
                "UPDATE build2grouprule SET expected='$expected'
                    WHERE groupid='$groupid' AND buildtype='$buildtype' AND
                    buildname='$buildname' AND siteid='$siteid' AND
                    endtime='1980-01-01 00:00:00'");
        } elseif ($expected) {
            // we add the grouprule

            $now = gmdate(FMT_DATETIME);
            pdo_query(
                "INSERT INTO build2grouprule
                    (groupid, buildtype, buildname, siteid, expected,
                     starttime, endtime)
                    VALUES
                    ('$groupid','$buildtype','$buildname','$siteid','$expected',
                     '$now','1980-01-01 00:00:00')");
        }
    }

    // Should we move this build to a different group?
    if (isset($_POST['expected']) && isset($_POST['newgroupid'])) {
        $expected = pdo_real_escape_numeric($_POST['expected']);
        $newgroupid = pdo_real_escape_numeric($_POST['newgroupid']);

        // Remove the build from its previous group.
        pdo_query("DELETE FROM build2group WHERE buildid='$buildid'");

        // Insert it into the new group.
        pdo_query(
            "INSERT INTO build2group(groupid,buildid)
                VALUES ('$newgroupid','$buildid')");

        // Mark any previous buildgroup rule as finished as of this time.
        $now = gmdate(FMT_DATETIME);
        pdo_query(
            "UPDATE build2grouprule SET endtime='$now'
                WHERE buildtype='$buildtype' AND buildname='$buildname' AND
                siteid='$siteid' AND endtime='1980-01-01 00:00:00'");

        // Create the rule for the new buildgroup.
        // (begin time is set by default by mysql)
        pdo_query(
            "INSERT INTO build2grouprule(groupid, buildtype, buildname, siteid,
            expected, starttime, endtime)
                VALUES ('$newgroupid','$buildtype','$buildname','$siteid','$expected',
                    '$now','1980-01-01 00:00:00')");
    }

    // Should we change the 'done' setting for this build?
    if (isset($_POST['done'])) {
        $done = pdo_real_escape_numeric($_POST['done']);
        pdo_query("UPDATE build SET done='$done' WHERE id='{$build->Id}'");
    }
}

/* Handle PUT requests */
function rest_put()
{
    global $buildid;
}

/* Handle GET requests */
function rest_get()
{
    global $build;
    $response = array();

    // Are we looking for what went wrong with this build?
    if (isset($_GET['getproblems'])) {
        $response['hasErrors'] = false;
        $response['hasFailingTests'] = false;

        // Lookup some details about this build.
        $buildtype = $build->Type;
        $buildname = $build->Name;
        $siteid = $build->SiteId;
        $starttime = $build->StartTime;
        $projectid = $build->ProjectId;

        // Check if this build has errors.
        $buildHasErrors = $build->BuildErrorCount > 0;
        if ($buildHasErrors) {
            $response['hasErrors'] = true;
            // Find the last occurrence of this build that had no errors.
            $no_errors_result = pdo_query(
                "SELECT starttime FROM build
                WHERE siteid='$siteid' AND type='$buildtype' AND
                name='$buildname' AND projectid='$projectid' AND
                starttime<='$starttime' AND parentid<1 AND builderrors<1
                ORDER BY starttime DESC LIMIT 1");

            if (pdo_num_rows($no_errors_result) > 0) {
                $no_errors_row = pdo_fetch_array($no_errors_result);
                $gmtdate = strtotime($no_errors_row['starttime'] . ' UTC');
            } else {
                // Find the first build
                $firstbuild = pdo_single_row_query(
                        "SELECT starttime FROM build
                        WHERE siteid='$siteid' AND type='$buildtype' AND
                        name='$buildname' AND projectid='$projectid' AND
                        starttime<='$starttime'
                        ORDER BY starttime ASC LIMIT 1");
                $gmtdate = strtotime($firstbuild['starttime'] . ' UTC');
            }
            $response['daysWithErrors'] =
                round((strtotime($starttime) - $gmtdate) / (3600 * 24));
            $response['failingSince'] = date(FMT_DATETIMETZ, $gmtdate);
            $response['failingDate'] = substr($response['failingSince'], 0, 10);
        }

        // Check if this build has failed tests.
        $buildHasFailingTests = $build->TestFailedCount > 0;
        if ($buildHasFailingTests) {
            $response['hasFailingTests'] = true;
            // Find the last occurrence of this build that had no test failures.
            $no_fails_result = pdo_query(
                "SELECT starttime FROM build
                WHERE siteid='$siteid' AND type='$buildtype' AND
                name='$buildname' AND projectid='$projectid' AND
                starttime<='$starttime' AND parentid<1 AND testfailed<1
                ORDER BY starttime DESC LIMIT 1");

            if (pdo_num_rows($no_fails_result) > 0) {
                $no_fails_row = pdo_fetch_array($no_fails_result);
                $gmtdate = strtotime($no_fails_row['starttime'] . ' UTC');
            } else {
                // Find the first build
                $firstbuild = pdo_single_row_query(
                        "SELECT starttime FROM build
                        WHERE siteid='$siteid' AND type='$buildtype' AND
                        name='$buildname' AND projectid='$projectid' AND
                        starttime<='$starttime' AND parentid<1
                        ORDER BY starttime ASC LIMIT 1");
                $gmtdate = strtotime($firstbuild['starttime'] . ' UTC');
            }
            $response['daysWithFailingTests'] =
                round((strtotime($starttime) - $gmtdate) / (3600 * 24));
            $response['testsFailingSince'] = date(FMT_DATETIMETZ, $gmtdate);
            $response['testsFailingDate'] =
                substr($response['testsFailingSince'], 0, 10);
        }
        echo json_encode(cast_data_for_JSON($response));
    }
}
