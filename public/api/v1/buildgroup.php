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
    global $projectid;

    $buildgroupid = get_buildgroupid();
    if ($buildgroupid === false) {
        return;
    }

    $start = microtime_float();
    $response = begin_JSON_response();
    $response['projectid'] = $projectid;
    $response['buildgroupid'] = $buildgroupid;

    $BuildGroup = new BuildGroup();
    $BuildGroup->SetId($buildgroupid);
    $response['name'] = $BuildGroup->GetName();
    $response['group'] = $BuildGroup->GetGroupId();

    $query = pdo_query('
    SELECT id, name FROM buildgroup WHERE projectid=' . qnum($projectid) . "
    AND endtime='1980-01-01 00:00:00'");

    if (!$query) {
        add_last_sql_error('getBuildGroup Select');
        return false;
    }

    $dependencies = $BuildGroup->GetDependencies();
    $dependencies_response = array();
    $available_dependencies_response = array();

    while ($row = pdo_fetch_array($query)) {
        if ($row['id'] == $buildgroupid) {
            continue;
        }
        if (is_array($dependencies) && in_array($row['id'], $dependencies)) {
            $dep = array();
            $dep['id'] = $row['id'];
            $dep['name'] = $row['name'];
            $dependencies_response[] = $dep;
        } else {
            $avail = array();
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
        $buildgroupid = pdo_real_escape_numeric($wildcard['buildgroupid']);
        $match = htmlspecialchars(pdo_real_escape_string($wildcard['match']));
        $buildtype =
            htmlspecialchars(pdo_real_escape_string($wildcard['buildtype']));

        $sql =
            "DELETE FROM build2grouprule
       WHERE groupid='$buildgroupid' AND buildtype = '$buildtype' AND
             buildname = '%$match%'";
        if (!pdo_query($sql)) {
            echo_error(pdo_error());
        }
    }

    if (isset($_GET['dynamic'])) {
        // Delete a dynamic build group rule.
        $dynamic = json_decode($_GET['dynamic'], true);
        $buildgroupid = pdo_real_escape_numeric($dynamic['id']);

        $rule = json_decode($_GET['rule'], true);
        $match = htmlspecialchars(pdo_real_escape_string($rule['match']));
        if (!empty($match)) {
            $match = "%$match%";
        }
        $parentgroupid = pdo_real_escape_numeric($rule['parentgroupid']);
        $siteid = pdo_real_escape_numeric($rule['siteid']);

        $sql =
            "DELETE FROM build2grouprule
            WHERE groupid='$buildgroupid' AND buildname = '$match'";
        if ($siteid > 0) {
            $sql .= " AND siteid = '$siteid'";
        }
        if ($parentgroupid > 0) {
            $sql .= " AND parentgroupid = '$parentgroupid'";
        }

        if (!pdo_query($sql)) {
            echo_error(pdo_error());
        }
    }
}

/* Handle POST requests */
function rest_post()
{
    global $projectid;

    if (isset($_POST['newbuildgroup'])) {
        // Create a new buildgroup
        $BuildGroup = new BuildGroup();
        $BuildGroup->SetProjectId($projectid);

        $name = htmlspecialchars(pdo_real_escape_string($_POST['newbuildgroup']));

        // Avoid creating a group that uses one of the default names.
        if ($name == 'Nightly' || $name == 'Experimental' || $name == 'Continuous') {
            echo_error("You cannot create a group named 'Nightly','Experimental' or 'Continuous'");
            return;
        }

        $type = htmlspecialchars(pdo_real_escape_string($_POST['type']));

        $BuildGroup->SetName($name);
        $BuildGroup->SetType($type);
        $BuildGroup->Save();

        // Respond with a JSON representation of this new buildgroup
        $response = array();
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
             WHERE bg.projectid = '$projectid')";
            } else {
                $sql = "
          DELETE bgp FROM buildgroupposition AS bgp
          LEFT JOIN buildgroup AS bg ON (bgp.buildgroupid = bg.id)
          WHERE bg.projectid = '$projectid'";
            }
            pdo_query($sql);
            add_last_sql_error('manageBuildGroup::newLayout::DELETE', $projectid);

            // construct query to insert the new layout
            $query = 'INSERT INTO buildgroupposition (buildgroupid, position) VALUES ';
            foreach ($inputRows as $inputRow) {
                $query .= '(' .
                    qnum(pdo_real_escape_numeric($inputRow['id'])) . ', ' .
                    qnum(pdo_real_escape_numeric($inputRow['position'])) . '), ';
            }

            // remove the trailing comma and space, then insert our new values
            $query = rtrim($query, ', ');
            pdo_query($query);
            add_last_sql_error('API::buildgroup::newLayout::INSERT', $projectid);
        }
        return;
    }

    if (isset($_POST['builds'])) {
        // Move builds to a new group.
        $group = $_POST['group'];
        if ($group['id'] < 1) {
            echo_error('Please select a group for these builds');
            return;
        }

        $builds = $_POST['builds'];
        if (array_key_exists('expected', $_POST)) {
            $expected = $_POST['expected'];
        } else {
            $expected = 0;
        }

        foreach ($builds as $buildinfo) {
            $groupid = pdo_real_escape_numeric($group['id']);

            $Build = new Build();
            $buildid = pdo_real_escape_numeric($buildinfo['id']);
            $Build->Id = $buildid;
            $Build->FillFromId($Build->Id);
            $prevgroupid = $Build->GroupId;

            // Change the group for this build.
            pdo_query(
                "UPDATE build2group SET groupid='$groupid'
         WHERE groupid='$prevgroupid' AND buildid='$buildid'");

            // Delete any previous rules
            pdo_query("
        DELETE FROM build2grouprule
        WHERE groupid='$prevgroupid' AND buildtype='$Build->Type' AND
              buildname='$Build->Name' AND siteid='$Build->SiteId'");

            // Add the new rule
            pdo_query("
        INSERT INTO build2grouprule
          (groupid,buildtype,buildname,siteid,expected,starttime,endtime)
        VALUES
          ('$groupid','$Build->Type','$Build->Name','$Build->SiteId',
           '$expected','1980-01-01 00:00:00','1980-01-01 00:00:00')");
        }
    }

    if (isset($_POST['nameMatch'])) {
        // Define a BuildGroup by Build name.
        $group = $_POST['group'];
        $groupid = $group['id'];
        if ($groupid < 1) {
            echo_error('Please select a BuildGroup to define.');
            return;
        }

        $nameMatch = '%' .
            htmlspecialchars(pdo_real_escape_string($_POST['nameMatch'])) . '%';
        $type = htmlspecialchars(pdo_real_escape_string($_POST['type']));
        $sql =
            "INSERT INTO build2grouprule (groupid, buildtype, buildname, siteid)
       VALUES ('$groupid', '$type', '$nameMatch', '-1')";
        if (!pdo_query($sql)) {
            echo_error(pdo_error());
        }
    }

    if (isset($_POST['dynamic']) && !empty($_POST['dynamic'])) {
        // Add a build row to a dynamic group
        $groupid = pdo_real_escape_numeric($_POST['dynamic']['id']);

        if (empty($_POST['buildgroup'])) {
            $parentgroupid = 0;
        } else {
            $parentgroupid = pdo_real_escape_numeric($_POST['buildgroup']['id']);
        }

        if (empty($_POST['site'])) {
            $siteid = 0;
        } else {
            $siteid = pdo_real_escape_numeric($_POST['site']['id']);
        }

        if (empty($_POST['match'])) {
            $match = '';
        } else {
            $match = '%' .
                htmlspecialchars(pdo_real_escape_string($_POST['match'])) . '%';
        }

        $sql =
            "INSERT INTO build2grouprule (groupid, buildname, siteid, parentgroupid)
       VALUES ('$groupid', '$match', '$siteid', '$parentgroupid')";
        if (!pdo_query($sql)) {
            echo_error(pdo_error());
        } else {
            // Respond with a JSON representation of this new rule.
            $response = array();
            $response['match'] =
                htmlspecialchars(pdo_real_escape_string($_POST['match']));
            $response['siteid'] = $siteid;
            if ($siteid > 0) {
                $response['sitename'] =
                    htmlspecialchars(pdo_real_escape_string($_POST['site']['name']));
            } else {
                $response['sitename'] = 'Any';
            }
            $response['parentgroupid'] = $parentgroupid;
            if ($parentgroupid > 0) {
                $response['parentgroupname'] =
                    htmlspecialchars(pdo_real_escape_string($_POST['buildgroup']['name']));
            } else {
                $response['parentgroupname'] = 'Any';
            }
            echo json_encode(cast_data_for_JSON($response));
            return;
        }
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
            echo_error('Failed to save BuildGroup');
        }
        return;
    }
}

function get_buildgroupid()
{
    if (!isset($_GET['buildgroupid'])) {
        echo_error('buildgroupid not specified.');
        return false;
    }
    $buildgroupid = pdo_real_escape_numeric($_GET['buildgroupid']);
    return $buildgroupid;
}

function echo_error($msg)
{
    $response = array();
    $response['error'] = $msg;
    echo json_encode($response);
}
