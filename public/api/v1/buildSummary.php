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

$noforcelogin = 1;
include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
include 'public/login.php';
include_once 'include/common.php';
include 'include/version.php';
require_once 'models/build.php';

$start = microtime_float();
$response = array();

// Checks
if (!isset($_GET['buildid']) || !is_numeric($_GET['buildid'])) {
    $response['error'] = 'Invalid buildid specified.';
    echo json_encode($response);
    http_response_code(400);
    return;
}
$buildid = pdo_real_escape_numeric($_GET['buildid']);

$date = null;
if (isset($_GET['date'])) {
    $date = $_GET['date'];
}

$build = new Build();
$build->Id = $buildid;
$build->FillFromId($build->Id);

$projectid = $build->ProjectId;
if (!isset($projectid) || $projectid < 1) {
    $response['error'] = 'This build doesnot exist.  Maybe it has been deleted.';
    echo json_encode($response);
    http_response_code(400);
    return;
}
$siteid = $build->SiteId;

$logged_in = false;
if (isset($_SESSION['cdash']) && isset($_SESSION['cdash']['loginid'])) {
    $logged_in = true;
}

if (!checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid, 1)) {
    if ($logged_in) {
        $response['error'] = 'You do not have permission to access this page.';
        echo json_encode($response);
        http_response_code(403);
    } else {
        $response['requirelogin'] = 1;
        echo json_encode($response);
        http_response_code(401);
    }
    return;
}

// Format the text to fit the iPhone
function format_for_iphone($text)
{
    global $FormatTextForIphone;
    if (!isset($FormatTextForIphone)) {
        return $text;
    }
    $text = str_replace("\n", '<br/>', $text);
    return $text;
}

$response = begin_JSON_response();
$projectname = get_project_name($projectid);
$response['title'] = "CDash : $projectname";

$previous_buildid = $build->GetPreviousBuildId();
$current_buildid = $build->GetCurrentBuildId();
$next_buildid = $build->GetNextBuildId();

$menu = array();
$nightlytime = get_project_property($projectname, 'nightlytime');
$menu['back'] = 'index.php?project=' . urlencode($projectname) . '&date=' . get_dashboard_date_from_build_starttime($build->StartTime, $nightlytime);

if ($previous_buildid > 0) {
    $menu['previous'] = "buildSummary.php?buildid=$previous_buildid";

    // Find the last submit date.
    $previous_build = new Build();
    $previous_build->Id = $previous_buildid;
    $previous_build->FillFromId($previous_build->Id);
    $lastsubmitdate = date(FMT_DATETIMETZ, strtotime($previous_build->StartTime . ' UTC'));
} else {
    $menu['noprevious'] = '1';
    $lastsubmitdate = 0;
}

$menu['current'] = "buildSummary.php?buildid=$current_buildid";

if ($next_buildid > 0) {
    $menu['next'] = "buildSummary.php?buildid=$next_buildid";
} else {
    $menu['nonext'] = '1';
}

$response['menu'] = $menu;

get_dashboard_JSON($projectname, $date, $response);

// User
if ($logged_in) {
    $user_response = array();
    $userid = $_SESSION['cdash']['loginid'];
    $user = pdo_query('SELECT admin FROM ' . qid('user') . " WHERE id='$userid'");
    $user_array = pdo_fetch_array($user);
    $user_response['id'] = $userid;
    $user_response['admin'] = $user_array['admin'];
    $response['user'] = $user_response;
}

// Notes
$notes_response = array();
$note = pdo_query("SELECT * FROM buildnote WHERE buildid='$buildid' ORDER BY timestamp ASC");
while ($note_array = pdo_fetch_array($note)) {
    $note_response = array();
    $userid = $note_array['userid'];
    $user_array = pdo_fetch_array(pdo_query('SELECT firstname,lastname FROM ' . qid('user') . " WHERE id='$userid'"));
    $timestamp = strtotime($note_array['timestamp'] . ' UTC');
    $usernote = $user_array['firstname'] . ' ' . $user_array['lastname'];
    switch ($note_array['status']) {
        case 0:
            $status = '[note]';
            break;
        case 1:
            $status = '[fix in progress]';
            break;
        case 2:
            $status = '[fixed]';
            break;
    }
    $note_response['status'] = $status;
    $note_response['user'] = $usernote;
    $note_response['date'] = date('H:i:s T', $timestamp);
    $note_response['text'] = $note_array['note'];
    $notes_response[] = $note_response;
}
$response['notes'] = $notes_response;

// Build
$build_response = array();
$build = pdo_query("SELECT * FROM build WHERE id='$buildid'");
$build_array = pdo_fetch_array($build);
$site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
$build_response['site'] = $site_array['name'];
$build_response['sitename_encoded'] = urlencode($site_array['name']);
$build_response['siteid'] = $siteid;
$build_response['name'] = $build_array['name'];
$build_response['id'] = $build_array['id'];
$build_response['stamp'] = $build_array['stamp'];
$build_response['time'] = date(FMT_DATETIMETZ, strtotime($build_array['starttime'] . ' UTC'));
$build_response['type'] = $build_array['type'];

$note = pdo_query("SELECT count(buildid) AS c FROM build2note WHERE buildid='$buildid'");
$note_array = pdo_fetch_array($note);
$build_response['note'] = $note_array['c'];

// Find the OS and compiler information
$buildinformation = pdo_query("SELECT * FROM buildinformation WHERE buildid='$buildid'");
if (pdo_num_rows($buildinformation) > 0) {
    $buildinformation_array = pdo_fetch_array($buildinformation);
    if ($buildinformation_array['osname'] != '') {
        $build_response['osname'] = $buildinformation_array['osname'];
    }
    if ($buildinformation_array['osplatform'] != '') {
        $build_response['osplatform'] = $buildinformation_array['osplatform'];
    }
    if ($buildinformation_array['osrelease'] != '') {
        $build_response['osrelease'] = $buildinformation_array['osrelease'];
    }
    if ($buildinformation_array['osversion'] != '') {
        $build_response['osversion'] = $buildinformation_array['osversion'];
    }
    if ($buildinformation_array['compilername'] != '') {
        $build_response['compilername'] = $buildinformation_array['compilername'];
    }
    if ($buildinformation_array['compilerversion'] != '') {
        $build_response['compilerversion'] = $buildinformation_array['compilerversion'];
    }
}

$build_response['generator'] = $build_array['generator'];
$build_response['command'] = $build_array['command'];
$build_response['starttime'] = date(FMT_DATETIMETZ, strtotime($build_array['starttime'] . ' UTC'));
$build_response['endtime'] = date(FMT_DATETIMETZ, strtotime($build_array['endtime'] . ' UTC'));

$build_response['lastsubmitbuild'] = $previous_buildid;
$build_response['lastsubmitdate'] = $lastsubmitdate;

// Number of errors and warnings
$builderror = pdo_query("SELECT count(*) FROM builderror WHERE buildid='$buildid' AND type='0'");
$builderror_array = pdo_fetch_array($builderror);
$nerrors = $builderror_array[0];
$builderror = pdo_query(
    "SELECT count(*) FROM buildfailure AS bf
     LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
     WHERE bf.buildid='$buildid' AND bfd.type='0'");
$builderror_array = pdo_fetch_array($builderror);
$nerrors += $builderror_array[0];

$build_response['error'] = $nerrors;
$buildwarning = pdo_query("SELECT count(*) FROM builderror WHERE buildid='$buildid' AND type='1'");
$buildwarning_array = pdo_fetch_array($buildwarning);
$nwarnings = $buildwarning_array[0];
$buildwarning = pdo_query(
    "SELECT count(*) FROM buildfailure AS bf
     LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
     WHERE bf.buildid='$buildid' AND bfd.type='1'");
$buildwarning_array = pdo_fetch_array($buildwarning);
$nwarnings += $buildwarning_array[0];

$build_response['nerrors'] = $nerrors;
$build_response['nwarnings'] = $nwarnings;

// Display the build errors
$errors_response = array();
$errors = pdo_query("SELECT * FROM builderror WHERE buildid='$buildid' and type='0'");
while ($error_array = pdo_fetch_array($errors)) {
    $error_response = array();
    $error_response['logline'] = $error_array['logline'];
    $error_response['text'] = format_for_iphone($error_array['text']);
    $error_response['sourcefile'] = $error_array['sourcefile'];
    $error_response['sourceline'] = $error_array['sourceline'];
    $error_response['precontext'] = format_for_iphone($error_array['precontext']);
    $error_response['postcontext'] = format_for_iphone($error_array['postcontext']);
    $errors_response[] = $error_response;
}

// Display the build failure error
$errors = pdo_query(
    "SELECT bf.sourcefile, bfd.stdoutput, bfd.stderror FROM buildfailure AS bf
     LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
     WHERE bf.buildid='$buildid' AND bfd.type='0'");
while ($error_array = pdo_fetch_array($errors)) {
    $error_response = array();
    $error_response['sourcefile'] = $error_array['sourcefile'];
    $error_response['stdoutput'] = format_for_iphone($error_array['stdoutput']);
    $error_response['stderror'] = $error_array['stderror'];
    $errors_response[] = $error_response;
}
$build_response['errors'] = $errors_response;

// Display the warnings
$warnings_response = array();
$errors = pdo_query("SELECT * FROM builderror WHERE buildid='$buildid' and type='1'");
while ($error_array = pdo_fetch_array($errors)) {
    $warning_response = array();
    $warning_response['logline'] = $error_array['logline'];
    $warning_response['text'] = format_for_iphone($error_array['text']);
    $warning_response['sourcefile'] = $error_array['sourcefile'];
    $warning_response['sourceline'] = $error_array['sourceline'];
    $warning_response['precontext'] = format_for_iphone($error_array['precontext']);
    $warning_response['postcontext'] = format_for_iphone($error_array['postcontext']);
    $warnings_response[] = $warning_response;
}

// Display the build failure warnings
$errors = pdo_query(
    "SELECT bf.sourcefile, bfd.stdoutput, bfd.stderror FROM buildfailure AS bf
     LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
     WHERE bf.buildid='$buildid' AND bfd.type='1'");
while ($error_array = pdo_fetch_array($errors)) {
    $warning_response = array();
    $warning_response['sourcefile'] = $error_array['sourcefile'];
    $warning_response['stdoutput'] = format_for_iphone($error_array['stdoutput']);
    $warning_response['stderror'] = $error_array['stderror'];
    $warnings_response[] = $warning_response;
}
$build_response['warnings'] = $warnings_response;
$response['build'] = $build_response;

// Update
$update_response = array();
$buildupdate = pdo_query('SELECT * FROM buildupdate AS u ,build2update AS b2u WHERE b2u.updateid=u.id AND b2u.buildid=' . qnum($buildid));

if (pdo_num_rows($buildupdate) > 0) {
    // show the update only if we have one
    $response['hasupdate'] = true;
    $update_array = pdo_fetch_array($buildupdate);
    // Checking for locally modify files
    $updatelocal = pdo_query('SELECT updatefile.updateid FROM updatefile,build2update AS b2u WHERE updatefile.updateid=b2u.updateid AND b2u.buildid=' . qnum($buildid)
        . " AND author='Local User'");
    $nerrors = pdo_num_rows($updatelocal);

    // Check also if the status is not zero
    if (strlen($update_array['status']) > 0 && $update_array['status'] != '0') {
        $nerrors += 1;
        $update_response['status'] = $update_array['status'];
    }
    $nwarnings = 0;
    $update_response['nerrors'] = $nerrors;
    $update_response['nwarnings'] = $nwarnings;

    $update = pdo_query('SELECT updatefile.updateid FROM updatefile,build2update AS b2u WHERE updatefile.updateid=b2u.updateid AND b2u.buildid=' . qnum($buildid));
    $nupdates = pdo_num_rows($update);
    $update_response['nupdates'] = $nupdates;

    $update_response['command'] = $update_array['command'];
    $update_response['type'] = $update_array['type'];
    $update_response['starttime'] = date(FMT_DATETIMETZ, strtotime($update_array['starttime'] . ' UTC'));
    $update_response['endtime'] = date(FMT_DATETIMETZ, strtotime($update_array['endtime'] . ' UTC'));
} else {
    $response['hasupdate'] = false;
    $update_response['nerrors'] = 0;
    $update_response['nwarnings'] = 0;
}
$response['update'] = $update_response;

// Configure
$configure_response = array();
$configure = pdo_query("SELECT * FROM configure WHERE buildid='$buildid'");
$configure_array = pdo_fetch_array($configure);

$nerrors = 0;
if ($configure_array['status'] != 0) {
    $nerrors = 1;
}

$configure_response['nerrors'] = $nerrors;
$configure_response['nwarnings'] = $configure_array['warnings'];

$configure_response['status'] = $configure_array['status'];
$configure_response['command'] = $configure_array['command'];
$configure_response['output'] = format_for_iphone($configure_array['log']);
$configure_response['starttime'] = date(FMT_DATETIMETZ, strtotime($configure_array['starttime'] . ' UTC'));
$configure_response['endtime'] = date(FMT_DATETIMETZ, strtotime($configure_array['endtime'] . ' UTC'));
$response['configure'] = $configure_response;

// Test
$test_response = array();
$nerrors = 0;
$nwarnings = 0;
$test_response['nerrors'] = $nerrors;
$test_response['nwarnings'] = $nwarnings;

$npass_array = pdo_fetch_array(pdo_query("SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND status='passed'"));
$npass = $npass_array[0];
$nnotrun_array = pdo_fetch_array(pdo_query("SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND status='notrun'"));
$nnotrun = $nnotrun_array[0];
$nfail_array = pdo_fetch_array(pdo_query("SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND status='failed'"));
$nfail = $nfail_array[0];

$test_response['npassed'] = $npass;
$test_response['nnotrun'] = $nnotrun;
$test_response['nfailed'] = $nfail;

$response['test'] = $test_response;

// Coverage
$response['hascoverage'] = false;
$coverage_array = pdo_fetch_array(pdo_query("SELECT * FROM coveragesummary WHERE buildid='$buildid'"));
if ($coverage_array) {
    $coverage_percent = round($coverage_array['loctested'] /
        ($coverage_array['loctested'] + $coverage_array['locuntested']) * 100, 2);
    $response['coverage'] = $coverage_percent;
    $response['hascoverage'] = true;
}

// Previous build
// Find the previous build
if ($previous_buildid > 0) {
    $previous_response = array();
    $previous_response['buildid'] = $previous_buildid;

    // Find if the build has any errors
    $builderror = pdo_query("SELECT count(*) FROM builderror WHERE buildid='$previous_buildid' AND type='0'");
    $builderror_array = pdo_fetch_array($builderror);
    $npreviousbuilderrors = $builderror_array[0];
    $builderror = pdo_query(
        "SELECT count(*) FROM buildfailure AS bf
       LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
       WHERE bf.buildid='$previous_buildid' AND bfd.type='0'");
    $builderror_array = pdo_fetch_array($builderror);
    $npreviousbuilderrors += $builderror_array[0];

    // Find if the build has any warnings
    $buildwarning = pdo_query("SELECT count(*) FROM builderror WHERE buildid='$previous_buildid' AND type='1'");
    $buildwarning_array = pdo_fetch_array($buildwarning);
    $npreviousbuildwarnings = $buildwarning_array[0];
    $buildwarning = pdo_query(
        "SELECT count(*) FROM buildfailure AS bf
       LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
       WHERE bf.buildid='$previous_buildid' AND bfd.type='1'");
    $buildwarning_array = pdo_fetch_array($buildwarning);
    $npreviousbuildwarnings += $buildwarning_array[0];

    // Find if the build has any test failings
    $nfail_array = pdo_fetch_array(pdo_query("SELECT count(testid) FROM build2test WHERE buildid='$previous_buildid' AND status='failed'"));
    $npreviousfailingtests = $nfail_array[0];
    $nfail_array = pdo_fetch_array(pdo_query("SELECT count(testid) FROM build2test WHERE buildid='$previous_buildid' AND status='notrun'"));
    $npreviousnotruntests = $nfail_array[0];

    $updatelocal = pdo_query('SELECT updatefile.updateid FROM updatefile,build2update AS b2u WHERE updatefile.updateid=b2u.updateid AND b2u.buildid=' . qnum($previous_buildid) .
        " AND author='Local User'");
    $nupdateerrors = pdo_num_rows($updatelocal);
    $nupdatewarnings = 0;
    $previous_response['nupdateerrors'] = $nupdateerrors;
    $previous_response['nupdatewarnings'] = $nupdatewarnings;

    $configure = pdo_query("SELECT * FROM configure WHERE buildid='$previous_buildid'");
    $configure_array = pdo_fetch_array($configure);

    $nconfigureerrors = 0;
    if ($configure_array['status'] != 0) {
        $nconfigureerrors = 1;
    }
    $previous_response['nconfigureerrors'] = $nconfigureerrors;
    $previous_response['nconfigurewarnings'] = $configure_array['warnings'];

    $previous_response['nerrors'] = $npreviousbuilderrors;
    $previous_response['nwarnings'] = $npreviousbuildwarnings;

    $previous_response['ntestfailed'] = $npreviousfailingtests;
    $previous_response['ntestnotrun'] = $npreviousnotruntests;

    $response['previousbuild'] = $previous_response;
}

$end = microtime_float();
$response['generationtime'] = round($end - $start, 3);
echo json_encode(cast_data_for_JSON($response));
