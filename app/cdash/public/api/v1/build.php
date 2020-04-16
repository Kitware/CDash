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
require_once 'include/pdo.php';
require_once 'include/api_common.php';

use CDash\Database;
use CDash\Model\BuildGroupRule;
use CDash\Model\Project;

init_api_request();
$response = [];
$build = get_request_build();

if (is_null($build)) {
    return;
}

$method = $_SERVER['REQUEST_METHOD'];
// Make sure the user is an admin before proceeding with non-read-only methods.
if ($method != 'GET' && !can_administrate_project($build->ProjectId)) {
    return;
}

// Route based on what type of request this is.
switch ($method) {
    case 'DELETE':
        rest_delete($build);
        break;
    case 'POST':
        rest_post($build);
        break;
    case 'PUT':
        rest_put($build);
        break;
    case 'GET':
    default:
        rest_get($build);
        break;
}

/* Handle DELETE requests */
function rest_delete($build)
{
    add_log('Build #' . $build->Id . ' removed manually', 'buildAPI');
    remove_build($build->Id);
}

/* Handle POST requests */
function rest_post($build)
{
    $pdo = Database::getInstance();
    $buildgrouprule = new BuildGroupRule($build);

    // Should we change whether or not this build is expected?
    if (isset($_POST['expected']) && isset($_POST['groupid'])) {
        $buildgrouprule->Expected = $_POST['expected'];
        $buildgrouprule->GroupId = $_POST['groupid'];
        $buildgrouprule->SetExpected();
    }

    // Should we move this build to a different group?
    if (isset($_POST['expected']) && isset($_POST['newgroupid'])) {
        $expected = $_POST['expected'];
        $newgroupid = $_POST['newgroupid'];

        // Remove the build from its previous group.
        $delete_stmt = $pdo->prepare(
            'DELETE FROM build2group WHERE buildid = :buildid');
        $pdo->execute($delete_stmt, [':buildid' => $build->Id]);

        // Insert it into the new group.
        $insert_stmt = $pdo->prepare(
            'INSERT INTO build2group(groupid, buildid)
            VALUES (:groupid, :buildid)');
        $pdo->execute($insert_stmt,
                [':groupid' => $newgroupid, ':buildid' => $build->Id]);

        // Mark any previous buildgroup rule as finished as of this time.
        $now = gmdate(FMT_DATETIME);
        $buildgrouprule->SoftDeleteExpiredRules($now);

        // Create the rule for the newly assigned buildgroup.
        $buildgrouprule->GroupId = $newgroupid;
        $buildgrouprule->Expected = $expected;
        $buildgrouprule->StartTime = $now;
        $buildgrouprule->Save();
    }

    // Should we change the 'done' setting for this build?
    if (isset($_POST['done'])) {
        $build->MarkAsDone($_POST['done']);
    }
}

/* Handle PUT requests */
function rest_put($build)
{
}

/* Handle GET requests */
function rest_get($build)
{
    $pdo = Database::getInstance()->getPdo();
    $response = [];

    // Are we looking for what went wrong with this build?
    if (isset($_GET['getproblems'])) {
        $response['hasErrors'] = false;
        $response['hasFailingTests'] = false;

        // Details about this build that will be used in SQL queries below.
        $query_params = [
            ':siteid'    => $build->SiteId,
            ':type'      => $build->Type,
            ':name'      => $build->Name,
            ':projectid' => $build->ProjectId,
            ':starttime' => $build->StartTime
        ];

        // Prepared statement to find the oldest submission for this build.
        // We do this here because it is potentially used multiple times below.
        $oldest_build_stmt = $pdo->prepare(
            'SELECT starttime FROM build
            WHERE siteid = :siteid AND type = :type AND
                  name = :name AND projectid = :projectid AND
                  starttime <= :starttime
            ORDER BY starttime ASC LIMIT 1');
        $first_submit = null;

        // Check if this build has errors.
        $buildHasErrors = $build->BuildErrorCount > 0;
        if ($buildHasErrors) {
            $response['hasErrors'] = true;
            // Find the last occurrence of this build that had no errors.
            $no_errors_stmt = $pdo->prepare(
                'SELECT starttime FROM build
                WHERE siteid = :siteid AND type = :type AND name = :name AND
                      projectid = :projectid AND starttime <= :starttime AND
                      parentid < 1 AND builderrors < 1
                ORDER BY starttime DESC LIMIT 1');
            pdo_execute($no_errors_stmt, $query_params);
            $last_good_submit = $no_errors_stmt->fetchColumn();
            if ($last_good_submit !== false) {
                $gmtdate = strtotime($last_good_submit . ' UTC');
            } else {
                // Find the oldest submission for this build.
                pdo_execute($oldest_build_stmt, $query_params);
                $first_submit = $oldest_build_stmt->fetchColumn();
                $gmtdate = strtotime($first_submit . ' UTC');
            }
            $response['daysWithErrors'] =
                round((strtotime($build->StartTime) - $gmtdate) / (3600 * 24));
            $response['failingSince'] = date(FMT_DATETIMETZ, $gmtdate);
            $response['failingDate'] = substr($response['failingSince'], 0, 10);
        }

        // Check if this build has failed tests.
        $buildHasFailingTests = $build->TestFailedCount > 0;
        if ($buildHasFailingTests) {
            $response['hasFailingTests'] = true;
            // Find the last occurrence of this build that had no test failures.
            $no_fails_stmt = $pdo->prepare(
                'SELECT starttime FROM build
                WHERE siteid = :siteid AND type = :type AND
                        name = :name AND projectid = :projectid AND
                        starttime <= :starttime AND parentid < 1 AND
                        testfailed < 1
                ORDER BY starttime DESC LIMIT 1');
            pdo_execute($no_fails_stmt, $query_params);
            $last_good_submit = $no_fails_stmt->fetchColumn();
            if ($last_good_submit !== false) {
                $gmtdate = strtotime($last_good_submit . ' UTC');
            } else {
                // Find the oldest submission for this build.
                if (is_null($first_submit)) {
                    pdo_execute($oldest_build_stmt, $query_params);
                    $first_submit = $oldest_build_stmt->fetchColumn();
                }
                $gmtdate = strtotime($first_submit . ' UTC');
            }
            $response['daysWithFailingTests'] =
                round((strtotime($build->StartTime) - $gmtdate) / (3600 * 24));
            $response['testsFailingSince'] = date(FMT_DATETIMETZ, $gmtdate);
            $response['testsFailingDate'] =
                substr($response['testsFailingSince'], 0, 10);
        }
        echo json_encode(cast_data_for_JSON($response));
    }
}
