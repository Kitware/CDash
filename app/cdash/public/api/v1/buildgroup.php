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

namespace CDash\Api\v1\BuildGroup;

require_once 'include/api_common.php';
require_once 'include/version.php';

use App\Services\PageTimer;
use CDash\Model\Build;
use CDash\Model\BuildGroup;
use CDash\Model\BuildGroupRule;
use App\Models\Site;

// Require administrative access to view this page.
init_api_request();

if (array_key_exists('projectid', $_REQUEST)) {
    $projectid = pdo_real_escape_numeric($_REQUEST['projectid']);
} else {
    $project = get_project_from_request();
    $projectid = $project->Id;
}

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
        return rest_post($pdo, $projectid);
    case 'PUT':
        rest_put($projectid);
        break;
    case 'GET':
    default:
        rest_get($pdo, $projectid);
        break;
}

/** Handle GET requests */
function rest_get($pdo, $projectid)
{
    if (!isset($_GET['buildgroupid'])) {
        abort(400, 'buildgroupid not specified');
    }
    $buildgroupid = pdo_real_escape_numeric($_GET['buildgroupid']);

    $pageTimer = new PageTimer();
    $response = begin_JSON_response();
    $response['projectid'] = $projectid;
    $response['buildgroupid'] = $buildgroupid;

    $BuildGroup = new BuildGroup();
    $BuildGroup->SetId($buildgroupid);
    $response['name'] = $BuildGroup->GetName();
    $response['group'] = $BuildGroup->GetGroupId();

    $stmt = $pdo->prepare(
        "SELECT id, name FROM buildgroup
        WHERE projectid = ? AND endtime = '1980-01-01 00:00:00'");
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

    $pageTimer->end($response);
    echo json_encode(cast_data_for_JSON($response));
}

/** Handle DELETE requests */
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
        // Soft delete a wildcard build group rule.
        $wildcard = json_decode($_GET['wildcard'], true);
        $buildgrouprule = new BuildGroupRule();
        $buildgrouprule->SiteId = -1;
        $buildgrouprule->BuildType = $wildcard['buildtype'];
        $buildgrouprule->BuildName =
            isset($wildcard['match']) ? convert_wildcards($wildcard['match']) : '';
        $buildgrouprule->GroupId = $wildcard['buildgroupid'];
        if (!$buildgrouprule->Delete(true)) {
            abort(500, 'Something went wrong...');
        }
    }

    if (isset($_GET['dynamic'])) {
        // Soft delete a dynamic build group rule.
        $buildgrouprule = new BuildGroupRule();

        $dynamic = json_decode($_GET['dynamic'], true);
        $buildgrouprule->GroupId = $dynamic['id'];

        $rule = json_decode($_GET['rule'], true);
        $buildgrouprule->BuildName = $rule['match'] ?? '';

        $siteid = $rule['siteid'];
        if ($siteid > 0) {
            $buildgrouprule->SiteId = $siteid;
        }

        $parentgroupid = $rule['parentgroupid'];
        if ($parentgroupid > 0) {
            $buildgrouprule->ParentGroupId = $parentgroupid;
        }

        if (!$buildgrouprule->Delete(true)) {
            abort(500, 'Something went wrong...');
        }
    }
}

/** Handle POST requests */
function rest_post($pdo, $projectid)
{
    $now = gmdate(FMT_DATETIME);
    $error_msg = '';

    if (isset($_POST['newbuildgroup'])) {
        // Create a new buildgroup or return an existing one.
        $BuildGroup = new BuildGroup();
        $BuildGroup->SetProjectId($projectid);

        $name = htmlspecialchars(pdo_real_escape_string($_POST['newbuildgroup']));
        $BuildGroup->SetName($name);

        if ($BuildGroup->Exists()) {
            $status_code = 200;
        } else {
            $status_code = 201;
            $type = htmlspecialchars(pdo_real_escape_string($_POST['type']));
            $BuildGroup->SetType($type);
            $BuildGroup->Save();
        }

        // Respond with a JSON representation of this buildgroup.
        $response = [];
        $response['id'] = $BuildGroup->GetId();
        $response['name'] = $BuildGroup->GetName();
        $response['autoremovetimeframe'] = $BuildGroup->GetAutoRemoveTimeFrame();
        return response()->json($response, $status_code);
    }

    if (isset($_POST['newLayout'])) {
        // Update the order of the buildgroups for this project.
        $inputRows = $_POST['newLayout'];
        if (count($inputRows) > 0) {
            // Remove old build group layout for this project.
            if (config('database.default') == 'pgsql') {
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
            abort(400, $error_msg);
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
                'UPDATE build2group
                SET groupid = :groupid
                WHERE groupid = :prevgroupid AND
                      buildid = :buildid');
            pdo_execute($stmt, [$groupid, $prevgroupid, $buildid]);

            // Soft delete any previous rules.
            $buildgrouprule = new BuildGroupRule($Build);
            $buildgrouprule->Delete(true);

            // Add the new rule.
            $buildgrouprule->GroupId = $groupid;
            $buildgrouprule->Expected = $expected;
            $buildgrouprule->StartTime = $now;
            $buildgrouprule->EndTime = '1980-01-01 00:00:00';
            if (!$buildgrouprule->Save()) {
                abort(500, 'Error saving rule');
            }
        }
    }

    if (isset($_POST['nameMatch'])) {
        // Define a BuildGroup by Build name.
        $group = $_POST['group'];
        $groupid = $group['id'];
        if ($groupid < 1) {
            $error_msg = 'Please select a BuildGroup to define.';
            abort(400, $error_msg);
        }

        $nameMatch = convert_wildcards($_POST['nameMatch']);
        $type = $_POST['type'];

        $buildgrouprule = new BuildGroupRule();
        $buildgrouprule->GroupId = $groupid;
        $buildgrouprule->BuildType = $type;
        $buildgrouprule->BuildName = $nameMatch;
        $buildgrouprule->SiteId = -1;
        $buildgrouprule->StartTime = $now;
        if (!$buildgrouprule->Save()) {
            abort(500, 'Error saving rule');
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

        $sql_match = $match = isset($_POST['match']) ? $_POST['match'] : '';
        if (!empty($match)) {
            $sql_match = $_POST['match'];
        }

        $buildgrouprule = new BuildGroupRule();
        $buildgrouprule->GroupId = $groupid;
        $buildgrouprule->BuildName = $sql_match;
        $buildgrouprule->SiteId = $siteid;
        $buildgrouprule->ParentGroupId = $parentgroupid;
        $buildgrouprule->StartTime = $now;
        if (!$buildgrouprule->Save()) {
            abort(500, 'Error saving rule');
        }

        // Respond with a JSON representation of this new rule.
        $response = [];
        $response['match'] = $match;
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
    }
}

/** Handle PUT requests */
function rest_put($projectid)
{
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
            abort(500, 'Failed to save BuildGroup');
        }
    }

    if (isset($_REQUEST['dynamiclist']) && !empty($_REQUEST['dynamiclist'])) {
        // Update a list of dynamic builds.
        $buildgroupid = pdo_real_escape_numeric($_REQUEST['buildgroupid']);
        $build_group = new BuildGroup();
        $build_group->SetId($buildgroupid);
        $old_rules = $build_group->GetRules();

        $new_rules_request = $_REQUEST['dynamiclist'];
        $now = gmdate(FMT_DATETIME);
        foreach ($new_rules_request as $new_rule_request) {
            // Populate a model of the requested rule.
            $new_rule = new BuildGroupRule();
            $new_rule->GroupId = $buildgroupid;
            $new_rule->ProjectId = $projectid;
            $new_rule->BuildName =
                isset($new_rule_request['match']) ?
                convert_wildcards($new_rule_request['match']) : '';
            $parentgroupid = $new_rule_request['parentgroupid'];
            if ($parentgroupid > 0) {
                $new_rule->ParentGroupId = $parentgroupid;
            }
            $sitename = $new_rule_request['site'];
            if ($sitename === 'Any') {
                $siteid = 0;
            } else {
                $site = Site::where(['name' => $sitename])->first();
                $siteid = $site->id ?? 0;
            }
            if ($siteid > 0) {
                $new_rule->SiteId = $siteid;
            }

            if ($new_rule->Exists()) {
                // We already have this rule, no need to add it again.
                // Remove it from our list of old rules to soft-delete.
                $idx_to_unset = -1;
                foreach ($old_rules as $idx => $old_rule) {
                    if ($old_rule->BuildName == $new_rule->BuildName &&
                            $old_rule->ParentGroupId == $new_rule->ParentGroupId &&
                            $old_rule->SiteId == $new_rule->SiteId) {
                        $idx_to_unset = $idx;
                        break;
                    }
                }
                if ($idx_to_unset > -1) {
                    unset($old_rules[$idx_to_unset]);
                }
            } else {
                // Save this new rule.
                $new_rule->StartTime = $now;
                $new_rule->Save();
            }
        }

        foreach ($old_rules as $old_rule) {
            // Soft-delete any old rules that weren't included in the new set.
            $old_rule->Delete(true);
        }
    }
}

/** Convert wildcard characters to SQL format */
function convert_wildcards($match)
{
    if (empty($match)) {
        return $match;
    }
    if (strpos($match, '*') !== false) {
        $match = str_replace('*', '%', $match);
    }
    return '%' . trim($match, '%') . '%';
}
