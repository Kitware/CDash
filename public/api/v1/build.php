<?php

$noforcelogin = 1;
include(dirname(dirname(dirname(__DIR__)))."/config/config.php");
require_once('include/pdo.php');
include_once('include/common.php');
include('public/login.php');
include_once('models/project.php');
include_once('models/user.php');

$response = array();

// Connect to database.
@$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

// Check that a buildid was specified.
$buildid_ok = false;
@$buildid = $_GET['buildid'];
if (!isset($buildid)) {
    $rest_json = file_get_contents("php://input");
    $_POST  = json_decode($rest_json, true);
    @$buildid = $_POST['buildid'];
}
if (isset($buildid)) {
    $buildid = pdo_real_escape_numeric($buildid);
    if (is_numeric($buildid)) {
        $buildid_ok = true;
    }
}
if (!$buildid_ok) {
    $response['error'] = 'buildid not specified.';
    echo json_encode($response);
    return;
}

// Make sure this build actually exists.
$build_array = pdo_fetch_array(pdo_query(
            "SELECT * FROM build WHERE id='$buildid'"));
$projectid = $build_array["projectid"];
if (!isset($projectid) || $projectid == 0) {
    $response['error'] = "This build doesn't exist. Maybe it has been deleted.";
    echo json_encode($response);
    return;
}

// And that the user has access to it.
if (!checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid, 1)) {
    $response['requirelogin'] = 1;
    echo json_encode($response);
    return;
}

$method = $_SERVER['REQUEST_METHOD'];
// Make sure the user is an admin before procedding with non-read-only methods.
if ($method != 'GET') {
    if (!$session_OK) {
        $response['error'] = 'No session found.';
        echo json_encode($response);
        return;
    }
    $userid = $_SESSION['cdash']['loginid'];
    if (!isset($userid) || !is_numeric($userid)) {
        $response['error'] = 'Not a valid userid!';
        echo json_encode($response);
        return;
    }

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
    global $buildid;
    add_log("Build #".$buildid." removed manually", "buildAPI");
    remove_build($buildid);
}

/* Handle POST requests */
function rest_post()
{
    global $buildid;

    // Lookup some details about this build.
    $build = pdo_query(
            "SELECT name, type, siteid, projectid FROM build WHERE id='$buildid'");
    $build_array = pdo_fetch_array($build);
    $buildtype = $build_array["type"];
    $buildname = $build_array["name"];
    $siteid = $build_array["siteid"];
    $projectid = $build_array["projectid"];

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
        $prevgroup = pdo_fetch_array(pdo_query(
                    "SELECT groupid as id FROM build2group WHERE buildid='$buildid'"));
        $prevgroupid = $prevgroup["id"];
        pdo_query(
                "DELETE FROM build2group
                WHERE groupid='$prevgroupid' AND buildid='$buildid'");

        // Insert it into the new group.
        pdo_query(
                "INSERT INTO build2group(groupid,buildid)
                VALUES ('$newgroupid','$buildid')");

        // Mark any previous buildgroup rule as finished as of this time.
        $now = gmdate(FMT_DATETIME);
        pdo_query(
                "UPDATE build2grouprule SET endtime='$now'
                WHERE groupid='$prevgroupid' AND buildtype='$buildtype' AND
                buildname='$buildname' AND siteid='$siteid' AND
                endtime='1980-01-01 00:00:00'");

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
        pdo_query("UPDATE build SET done='$done' WHERE id='$buildid'");
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
    global $buildid;
}
