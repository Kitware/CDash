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
include 'include/version.php';
require_once 'models/build.php';
require_once 'models/buildusernote.php';
require_once 'models/user.php';

$start = microtime_float();
$response = array();

$build = get_request_build();

$date = null;
if (isset($_GET['date'])) {
    $date = $_GET['date'];
}

$buildid = $build->Id;
$projectid = $build->ProjectId;
$siteid = $build->SiteId;

if (!can_access_project($projectid)) {
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

$userid = get_userid_from_session(false);
if ($userid) {
    $user = new User();
    $user->Id = $userid;
    $user->Fill();
    $user_response['id'] = $userid;
    $user_response['admin'] = $user->Admin;
    $response['user'] = $user_response;
}


// Notes added by users.
$notes_response = array();
$notes = BuildUserNote::getNotesForBuild($buildid);
foreach ($notes as $note) {
    $note_response = $note->marshal();
    $notes_response[] = $note_response;
}
$response['notes'] = $notes_response;

// Build
$build_response = array();

$site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
$build_response['site'] = $site_array['name'];
$build_response['sitename_encoded'] = urlencode($site_array['name']);
$build_response['siteid'] = $siteid;

$build_response['name'] = $build->Name;
$build_response['id'] = $buildid;
$build_response['stamp'] = $build->GetStamp();
$build_response['time'] = date(FMT_DATETIMETZ, strtotime($build->StartTime . ' UTC'));
$build_response['type'] = $build->Type;

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

$build_response['generator'] = $build->Generator;
$build_response['command'] = $build->Command;
$build_response['starttime'] = date(FMT_DATETIMETZ, strtotime($build->StartTime . ' UTC'));
$build_response['endtime'] = date(FMT_DATETIMETZ, strtotime($build->EndTime . ' UTC'));

$build_response['lastsubmitbuild'] = $previous_buildid;
$build_response['lastsubmitdate'] = $lastsubmitdate;

$e_errors = $build->GetErrors(['type' => Build::TYPE_ERROR]);
$e_warnings = $build->GetErrors(['type' => Build::TYPE_WARN]);

$f_errors = $build->GetFailures(['type' => Build::TYPE_ERROR]);
$f_warnings = $build->GetFailures(['type' => Build::TYPE_WARN]);

$nerrors = count($e_errors) + count($f_errors);
$nwarnings = count($e_warnings) + count($f_warnings);

$build_response['error'] = $nerrors;

$build_response['nerrors'] = $nerrors;
$build_response['nwarnings'] = $nwarnings;

// Display the build errors

$errors_response = array();

foreach ($e_errors as $error_array) {
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

foreach ($f_errors as $error_array) {
    $error_response = array();
    $error_response['sourcefile'] = $error_array['sourcefile'];
    $error_response['stdoutput'] = format_for_iphone($error_array['stdoutput']);
    $error_response['stderror'] = $error_array['stderror'];
    $errors_response[] = $error_response;
}

$build_response['errors'] = $errors_response;

// Display the warnings
$warnings_response = array();

foreach ($e_warnings as $error_array) {
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

foreach ($f_warnings as $error_array) {
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
$configure = pdo_query(
        "SELECT * FROM configure c
        JOIN build2configure b2c ON b2c.configureid=c.id
        WHERE b2c.buildid='$buildid'");
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

    $configure = pdo_query(
            "SELECT * FROM configure c
            JOIN build2configure b2c ON b2c.configureid=c.id
            WHERE b2c.buildid='$previous_buildid'");
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
