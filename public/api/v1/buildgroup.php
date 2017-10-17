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

$noforcelogin = 1;
include 'public/login.php';

require_once 'include/api_common.php';
require_once 'include/version.php';
require_once 'models/buildgroup.php';

// Require administrative access to view this page.
init_api_request();
$projectid = pdo_real_escape_numeric($_REQUEST['projectid']);
if (!can_administrate_project($projectid)) {
    return;
}

$pdo = get_link_identifier()->getPdo();

// Route based on what type of request this is.
$method = $_SERVER['REQUEST_METHOD'];

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

/* Handle GET requests */
function rest_get()
{
    global $pdo, $projectid;

    if (!isset($_GET['buildgroupid'])) {
        json_error_response(['error' => 'buildgroupid not specified'], 400);
    }
    $buildgroupid = pdo_real_escape_numeric($_GET['buildgroupid']);

    $start = microtime_float();
    $response = begin_JSON_response();
    $response['projectid'] = $projectid;
    $response['buildgroupid'] = $buildgroupid;

    $BuildGroup = new BuildGroup();
    $BuildGroup->SetId($buildgroupid);
    $response['name'] = $BuildGroup->GetName();
    $response['group'] = $BuildGroup->GetGroupId();

    $stmt = $pdo->prepare(
        "SELECT id, name FROM buildgroup
        WHERE projectid = ? AND endtime='1980-01-01 00:00:00'");
    pdo_execute($stmt, [$projectid]);

    $dependencies = $BuildGroup->GetDependencies();
    $dependencies_response = [];
    $available_dependencies_response = [];

    while ($row = $stmt->fetch()) {
        if ($row['id'] == $buildgroupid) {
            continue;
        }
        if (is_array($dependencies) && in_array($row['id'], $dependencies)) {
            $dep = [];
            $dep['id'] = $row['id'];
            $dep['name'] = $row['name'];
            $dependencies_response[] = $dep;
        } else {
            $avail = [];
            $avail['id'] = $row['id'];
            $avail['name'] = $row['name'];
            $available_dependencies_response[] = $avail;
        }
    }

    $response['dependencies'] = $dependencies_response;
    $response['available_dependencies'] = $available_dependencies_response;

    $end = microtime_float();
    $response['generationtime'] = round($end - $start, 3);
    echo json_encode(cast_data_for_JSON($response));
}

/* Handle DELETE requests */
function rest_delete()
{
    global $pdo;
    if (isset($_GET['buildgroupid'])) {
        // Delete the specified BuildGroup.
        $buildgroupid = pdo_real_escape_numeric($_GET['buildgroupid']);
        $Group = new BuildGroup();
        $Group->SetId($buildgroupid);
        $Group->Delete();
        return;
    }

    if (isset($_GET['wildcard'])) {
        // Delete a wildcard build group rule.
        $wildcard = json_decode($_GET['wildcard'], true);
        $buildgroupid = $wildcard['buildgroupid'];
        $match = $wildcard['match'];
        if (!empty($match)) {
            $match = "%$match%";
        }
        $buildtype = $wildcard['buildtype'];

        $stmt = $pdo->prepare(
            'DELETE FROM build2grouprule
            WHERE groupid = ? AND buildtype = ? AND buildname = ?');
        if (!pdo_execute($stmt, [$buildgroupid, $buildtype, $match])) {
            json_error_response(['error' => pdo_error()], 500);
        }
    }

    if (isset($_GET['dynamic'])) {
        // Delete a dynamic build group rule.
        $dynamic = json_decode($_GET['dynamic'], true);
        $buildgroupid = $dynamic['id'];

        $rule = json_decode($_GET['rule'], true);
        $match = $rule['match'];
        if (!empty($match)) {
            $match = "%$match%";
        }
        $parentgroupid = $rule['parentgroupid'];
        $siteid = $rule['siteid'];

        $sql =
            'DELETE FROM build2grouprule WHERE groupid = ? AND buildname = ?';
        $params = [$buildgroupid, $match];
        if ($siteid > 0) {
            $sql .= ' AND siteid = ?';
            $params[] = $siteid;
        }
        if ($parentgroupid > 0) {
            $sql .= ' AND parentgroupid = ?';
            $params[] = $parentgroupid;
        }

        $stmt = $pdo->prepare($sql);
        if (!pdo_execute($stmt, $params)) {
            json_error_response(['error' => pdo_error()], 500);
        }
    }
}

/* Handle POST requests */
function rest_post()
{
    global $pdo, $projectid;

    if (isset($_POST['newbuildgroup'])) {
        // Create a new buildgroup
        $BuildGroup = new BuildGroup();
        $BuildGroup->SetProjectId($projectid);

        $name = htmlspecialchars(pdo_real_escape_string($_POST['newbuildgroup']));

        // Avoid creating a group that uses one of the default names.
        if ($name == 'Nightly' || $name == 'Experimental' || $name == 'Continuous') {
            $error_msg =
                "You cannot create a group named 'Nightly','Experimental' or 'Continuous'";
            json_error_response(['error' => $error_msg], 400);
        }

        $type = htmlspecialchars(pdo_real_escape_string($_POST['type']));

        $BuildGroup->SetName($name);
        $BuildGroup->SetType($type);
        $BuildGroup->Save();

        // Respond with a JSON representation of this new buildgroup
        $response = [];
        $response['id'] = $BuildGroup->GetId();
        $response['name'] = $BuildGroup->GetName();
        $response['autoremovetimeframe'] = $BuildGroup->GetAutoRemoveTimeFrame();
        echo json_encode(cast_data_for_JSON($response));
        return;
    }

    if (isset($_POST['newLayout'])) {
        // Update the order of the buildgroups for this project.
        $inputRows = $_POST['newLayout'];
        if (count($inputRows) > 0) {
            // Remove old build group layout for this project.

            global $CDASH_DB_TYPE;
            if (isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE == 'pgsql') {
                // We use a subquery here because postgres doesn't support
                // JOINs in a DELETE statement.
                $sql = "
                    DELETE FROM buildgroupposition WHERE buildgroupid IN
                    (SELECT bgp.buildgroupid FROM buildgroupposition AS bgp
                    LEFT JOIN buildgroup AS bg ON (bgp.buildgroupid = bg.id)
                    WHERE bg.projectid = ?)";
            } else {
                $sql = "
                    DELETE bgp FROM buildgroupposition AS bgp
                    LEFT JOIN buildgroup AS bg ON (bgp.buildgroupid = bg.id)
                    WHERE bg.projectid = ?";
            }
            $stmt = $pdo->prepare($sql);
            pdo_execute($stmt, [$projectid]);

            // Construct query to insert the new layout.
            $params = [];
            $query = 'INSERT INTO buildgroupposition (buildgroupid, position) VALUES ';
            foreach ($inputRows as $inputRow) {
                $query .= '(?, ?), ';
                $params[] = $inputRow['id'];
                $params[] = $inputRow['position'];
            }

            // Remove the trailing comma and space, then insert our new values.
            $query = rtrim($query, ', ');
            $stmt = $pdo->prepare($query);
            pdo_execute($stmt, $params);
        }
        return;
    }

    if (isset($_POST['builds'])) {
        // Move builds to a new group.
        $group = $_POST['group'];
        if ($group['id'] < 1) {
            $error_msg = 'Please select a group for these builds';
            json_error_response(['error' => $error_msg], 400);
        }

        $builds = $_POST['builds'];
        if (array_key_exists('expected', $_POST)) {
            $expected = $_POST['expected'];
        } else {
            $expected = 0;
        }

        foreach ($builds as $buildinfo) {
            $groupid = $group['id'];

            $Build = new Build();
            $buildid = pdo_real_escape_numeric($buildinfo['id']);
            $Build->Id = $buildid;
            $Build->FillFromId($Build->Id);
            $prevgroupid = $Build->GroupId;

            // Change the group for this build.
            $stmt = $pdo->prepare(
                'UPDATE build2group SET groupid = ?
                WHERE groupid = ? AND buildid = ?');
            pdo_execute($stmt, [$groupid, $prevgroupid, $buildid]);

            // Delete any previous rules
            $stmt = $pdo->prepare(
                'DELETE FROM build2grouprule
                WHERE groupid = ? AND buildtype = ? AND buildname = ? AND
                siteid =  ?');
            pdo_execute($stmt,
                [$prevgroupid, $Build->Type, $Build->Name, $Build->SiteId]);

            // Add the new rule
            $stmt = $pdo->prepare(
                'INSERT INTO build2grouprule
                    (groupid, buildtype, buildname, siteid, expected,
                     starttime, endtime)
                VALUES (?, ?, ?, ?, ?, ?, ?)');
            pdo_execute($stmt,
                [$groupid, $Build->Type, $Build->Name, $Build->SiteId,
                 $expected, '1980-01-01 00:00:00', '1980-01-01 00:00:00']);
        }
    }

    if (isset($_POST['nameMatch'])) {
        // Define a BuildGroup by Build name.
        $group = $_POST['group'];
        $groupid = $group['id'];
        if ($groupid < 1) {
            $error_msg = 'Please select a BuildGroup to define.';
            json_error_response(['error' => $error_msg], 400);
        }

        $nameMatch = '%' . $_POST['nameMatch'] . '%';
        $type = $_POST['type'];
        $stmt = $pdo->prepare(
            'INSERT INTO build2grouprule (groupid, buildtype, buildname, siteid)
            VALUES (?, ?, ?, ?)');
        if (!pdo_execute($stmt, [$groupid, $type, $nameMatch, -1])) {
            json_error_response(['error' => pdo_error()], 500);
        }
    }

    if (isset($_POST['dynamic']) && !empty($_POST['dynamic'])) {
        // Add a build row to a dynamic group
        $groupid = $_POST['dynamic']['id'];

        if (empty($_POST['buildgroup'])) {
            $parentgroupid = 0;
        } else {
            $parentgroupid = $_POST['buildgroup']['id'];
        }

        if (empty($_POST['site'])) {
            $siteid = 0;
        } else {
            $siteid = $_POST['site']['id'];
        }

        if (empty($_POST['match'])) {
            $match = '';
        } else {
            $match = '%' . $_POST['match'] . '%';
        }

        $stmt = $pdo->prepare(
            'INSERT INTO build2grouprule
            (groupid, buildname, siteid, parentgroupid)
            VALUES (?, ?, ?, ?)');
       if (!pdo_execute($stmt, [$groupid, $match, $siteid, $parentgroupid])) {
            json_error_response(['error' => pdo_error()], 500);
       }

       // Respond with a JSON representation of this new rule.
       $response = [];
       $response['match'] = $_POST['match'];
       $response['siteid'] = $siteid;
       if ($siteid > 0) {
           $response['sitename'] = $_POST['site']['name'];
       } else {
           $response['sitename'] = 'Any';
       }
       $response['parentgroupid'] = $parentgroupid;
       if ($parentgroupid > 0) {
           $response['parentgroupname'] = $_POST['buildgroup']['name'];
       } else {
           $response['parentgroupname'] = 'Any';
       }
       echo json_encode(cast_data_for_JSON($response));
       return;
    }
}

/* Handle PUT requests */
function rest_put()
{
    global $projectid;

    if (isset($_GET['buildgroup'])) {
        // Modify an existing buildgroup.
        $buildgroup = json_decode($_GET['buildgroup'], true);

        // Deal with the fact that unchecked checkboxes will not be included
        // in the above array.
        if (!array_key_exists('emailcommitters', $buildgroup)) {
            $buildgroup['emailcommitters'] = 0;
        }
        if (!array_key_exists('includesubprojecttotal', $buildgroup)) {
            $buildgroup['includesubprojecttotal'] = 0;
        }

        $BuildGroup = new BuildGroup();
        $BuildGroup->SetId(pdo_real_escape_numeric($buildgroup['id']));
        $BuildGroup->SetName(pdo_real_escape_string($buildgroup['name']));
        $BuildGroup->SetDescription(
            pdo_real_escape_string($buildgroup['description']));
        $BuildGroup->SetSummaryEmail(
            pdo_real_escape_numeric($buildgroup['summaryemail']));
        $BuildGroup->SetEmailCommitters(
            pdo_real_escape_numeric($buildgroup['emailcommitters']));
        $BuildGroup->SetIncludeSubProjectTotal(
            pdo_real_escape_numeric($buildgroup['includesubprojecttotal']));
        $BuildGroup->SetAutoRemoveTimeFrame(
            pdo_real_escape_numeric($buildgroup['autoremovetimeframe']));

        if (!$BuildGroup->Save()) {
            json_error_response(['error' => 'Failed to save BuildGroup'], 500);
        }
    }
}
