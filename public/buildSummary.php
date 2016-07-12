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
include dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';
include 'public/login.php';
include_once 'include/common.php';
include 'include/version.php';
require_once 'models/build.php';

// Checks
@$buildid = pdo_real_escape_numeric($_GET['buildid']);
if (!isset($buildid) || !is_numeric($buildid)) {
    echo 'Not a valid buildid!';
    return;
}

@$date = $_GET['date'];
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

$build = new Build();
$build->Id = $buildid;
$build->FillFromId($build->Id);

$projectid = $build->ProjectId;
if (!isset($projectid) || $projectid < 1) {
    echo "This build doesn't exist. Maybe it has been deleted.";
    exit();
}
$siteid = $build->SiteId;

checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid);

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

$xml = begin_XML_for_XSLT();
$projectname = get_project_name($projectid);
$xml .= '<title>CDash : ' . $projectname . '</title>';

$previous_buildid = $build->GetPreviousBuildId();
$current_buildid = $build->GetCurrentBuildId();
$next_buildid = $build->GetNextBuildId();

$xml .= '<menu>';
$nightlytime = get_project_property($projectname, 'nightlytime');
$xml .= add_XML_value('back', 'index.php?project=' . urlencode($projectname) . '&date=' . get_dashboard_date_from_build_starttime($build->StartTime, $nightlytime));

if ($previous_buildid > 0) {
    $xml .= add_XML_value('previous', "buildSummary.php?buildid=$previous_buildid");

    // Find the last submit date.
    $previous_build = new Build();
    $previous_build->Id = $previous_buildid;
    $previous_build->FillFromId($previous_build->Id);
    $lastsubmitdate = date(FMT_DATETIMETZ, strtotime($previous_build->StartTime . ' UTC'));
} else {
    $xml .= add_XML_value('noprevious', '1');
    $lastsubmitdate = 0;
}

$xml .= add_XML_value('current', "buildSummary.php?buildid=$current_buildid");

if ($next_buildid > 0) {
    $xml .= add_XML_value('next', "buildSummary.php?buildid=$next_buildid");
} else {
    $xml .= add_XML_value('nonext', '1');
}

$xml .= '</menu>';

$xml .= get_cdash_dashboard_xml($projectname, $date);

// User
if (isset($_SESSION['cdash']) && isset($_SESSION['cdash']['loginid'])) {
    $xml .= '<user>';
    $userid = $_SESSION['cdash']['loginid'];
    $user = pdo_query('SELECT admin FROM ' . qid('user') . " WHERE id='$userid'");
    $user_array = pdo_fetch_array($user);
    $xml .= add_XML_value('id', $userid);
    $xml .= add_XML_value('admin', $user_array['admin']);
    $xml .= '</user>';
}

// Notes
$note = pdo_query("SELECT * FROM buildnote WHERE buildid='$buildid' ORDER BY timestamp ASC");
while ($note_array = pdo_fetch_array($note)) {
    $xml .= '<note>';
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
    $xml .= add_XML_value('status', $status);
    $xml .= add_XML_value('user', $usernote);
    $xml .= add_XML_value('date', date('H:i:s T', $timestamp));
    $xml .= add_XML_value('text', $note_array['note']);
    $xml .= '</note>';
}

// Build
$xml .= '<build>';
$build = pdo_query("SELECT * FROM build WHERE id='$buildid'");
$build_array = pdo_fetch_array($build);
$site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
$xml .= add_XML_value('site', $site_array['name']);
$xml .= add_XML_value('sitename_encoded', urlencode($site_array['name']));
$xml .= add_XML_value('siteid', $siteid);
$xml .= add_XML_value('name', $build_array['name']);
$xml .= add_XML_value('id', $build_array['id']);
$xml .= add_XML_value('stamp', $build_array['stamp']);
$xml .= add_XML_value('time', date(FMT_DATETIMETZ, strtotime($build_array['starttime'] . ' UTC')));
$xml .= add_XML_value('type', $build_array['type']);

$note = pdo_query("SELECT count(buildid) AS c FROM build2note WHERE buildid='$buildid'");
$note_array = pdo_fetch_array($note);
$xml .= add_XML_value('note', $note_array['c']);

// Compute a related builds link. Most useful in projects with sub-projects
// defined, but maybe still useful for monolithic projects. Displays all
// builds (using filters) that share the same build name, site name *and*
// build stamp. (Other "related" links that might be useful: all with same
// (or similar?) build name, all with same site name, all with same stamp.)
//
$filterString =
    '&filtercount=3&showfilters=1&filtercombine=and' .
    '&field1=buildname/string&compare1=61&value1=' .
    urlencode($build_array['name']) .
    '&field2=site/string&compare2=61&value2=' .
    urlencode($site_array['name']) .
    '&field3=buildstamp/string&compare3=61&value3=' .
    urlencode($build_array['stamp']);

$relatedBuildsLink = 'index.php?project=' . urlencode($projectname) .
    '&display=project' . $filterString;

$xml .= add_XML_value('relatedBuildsLink', $relatedBuildsLink);

// For the filter (we display 1 week)
$xml .= add_XML_value('filterstarttime', date('Y-m-d', strtotime($build_array['starttime'] . ' UTC -7 days')));
$xml .= add_XML_value('filterendtime', date('Y-m-d', strtotime($build_array['starttime'] . ' UTC')));

// Find the OS and compiler information
$buildinformation = pdo_query("SELECT * FROM buildinformation WHERE buildid='$buildid'");
if (pdo_num_rows($buildinformation) > 0) {
    $buildinformation_array = pdo_fetch_array($buildinformation);
    if ($buildinformation_array['osname'] != '') {
        $xml .= add_XML_value('osname', $buildinformation_array['osname']);
    }
    if ($buildinformation_array['osplatform'] != '') {
        $xml .= add_XML_value('osplatform', $buildinformation_array['osplatform']);
    }
    if ($buildinformation_array['osrelease'] != '') {
        $xml .= add_XML_value('osrelease', $buildinformation_array['osrelease']);
    }
    if ($buildinformation_array['osversion'] != '') {
        $xml .= add_XML_value('osversion', $buildinformation_array['osversion']);
    }
    if ($buildinformation_array['compilername'] != '') {
        $xml .= add_XML_value('compilername', $buildinformation_array['compilername']);
    }
    if ($buildinformation_array['compilerversion'] != '') {
        $xml .= add_XML_value('compilerversion', $buildinformation_array['compilerversion']);
    }
}

$xml .= add_XML_value('generator', $build_array['generator']);
$xml .= add_XML_value('command', $build_array['command']);
$xml .= add_XML_value('starttime', date(FMT_DATETIMETZ, strtotime($build_array['starttime'] . ' UTC')));
$xml .= add_XML_value('endtime', date(FMT_DATETIMETZ, strtotime($build_array['endtime'] . ' UTC')));

$xml .= add_XML_value('lastsubmitdate', $lastsubmitdate);

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

$xml .= add_XML_value('error', $nerrors);
$buildwarning = pdo_query("SELECT count(*) FROM builderror WHERE buildid='$buildid' AND type='1'");
$buildwarning_array = pdo_fetch_array($buildwarning);
$nwarnings = $buildwarning_array[0];
$buildwarning = pdo_query(
    "SELECT count(*) FROM buildfailure AS bf
     LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
     WHERE bf.buildid='$buildid' AND bfd.type='1'");
$buildwarning_array = pdo_fetch_array($buildwarning);
$nwarnings += $buildwarning_array[0];

$xml .= add_XML_value('nerrors', $nerrors);
$xml .= add_XML_value('nwarnings', $nwarnings);

// Display the build errors
$errors = pdo_query("SELECT * FROM builderror WHERE buildid='$buildid' and type='0'");
while ($error_array = pdo_fetch_array($errors)) {
    $xml .= '<error>';
    $xml .= add_XML_value('logline', $error_array['logline']);
    $xml .= add_XML_value('text', format_for_iphone($error_array['text']));
    $xml .= add_XML_value('sourcefile', $error_array['sourcefile']);
    $xml .= add_XML_value('sourceline', $error_array['sourceline']);
    $xml .= add_XML_value('precontext', format_for_iphone($error_array['precontext']));
    $xml .= add_XML_value('postcontext', format_for_iphone($error_array['postcontext']));
    $xml .= '</error>';
}

// Display the build failure error
$errors = pdo_query(
    "SELECT bf.sourcefile, bfd.stdoutput, bfd.stderror FROM buildfailure AS bf
     LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
     WHERE bf.buildid='$buildid' AND bfd.type='0'");
while ($error_array = pdo_fetch_array($errors)) {
    $xml .= '<error>';
    $xml .= add_XML_value('sourcefile', $error_array['sourcefile']);
    $xml .= add_XML_value('stdoutput', format_for_iphone($error_array['stdoutput']));
    $xml .= add_XML_value('stderror', $error_array['stderror']);
    $xml .= '</error>';
}

// Display the warnings
$errors = pdo_query("SELECT * FROM builderror WHERE buildid='$buildid' and type='1'");
while ($error_array = pdo_fetch_array($errors)) {
    $xml .= '<warning>';
    $xml .= add_XML_value('logline', $error_array['logline']);
    $xml .= add_XML_value('text', format_for_iphone($error_array['text']));
    $xml .= add_XML_value('sourcefile', $error_array['sourcefile']);
    $xml .= add_XML_value('sourceline', $error_array['sourceline']);
    $xml .= add_XML_value('precontext', format_for_iphone($error_array['precontext']));
    $xml .= add_XML_value('postcontext', format_for_iphone($error_array['postcontext']));
    $xml .= '</warning>';
}

// Display the build failure error
$errors = pdo_query(
    "SELECT bf.sourcefile, bfd.stdoutput, bfd.stderror FROM buildfailure AS bf
     LEFT JOIN buildfailuredetails AS bfd ON (bfd.id=bf.detailsid)
     WHERE bf.buildid='$buildid' AND bfd.type='1'");
while ($error_array = pdo_fetch_array($errors)) {
    $xml .= '<warning>';
    $xml .= add_XML_value('sourcefile', $error_array['sourcefile']);
    $xml .= add_XML_value('stdoutput', format_for_iphone($error_array['stdoutput']));
    $xml .= add_XML_value('stderror', $error_array['stderror']);
    $xml .= '</warning>';
}

$xml .= '</build>';

// Update
$buildupdate = pdo_query('SELECT * FROM buildupdate AS u ,build2update AS b2u WHERE b2u.updateid=u.id AND b2u.buildid=' . qnum($buildid));

if (pdo_num_rows($buildupdate) > 0) {
    // show the update only if we have one

    $xml .= '<update>';
    $update_array = pdo_fetch_array($buildupdate);
    // Checking for locally modify files
    $updatelocal = pdo_query('SELECT updatefile.updateid FROM updatefile,build2update AS b2u WHERE updatefile.updateid=b2u.updateid AND b2u.buildid=' . qnum($buildid)
        . " AND author='Local User'");
    $nerrors = pdo_num_rows($updatelocal);

    // Check also if the status is not zero
    if (strlen($update_array['status']) > 0 && $update_array['status'] != '0') {
        $nerrors += 1;
        $xml .= add_XML_value('status', $update_array['status']);
    }
    $nwarnings = 0;
    $xml .= add_XML_value('nerrors', $nerrors);
    $xml .= add_XML_value('nwarnings', $nwarnings);

    $update = pdo_query('SELECT updatefile.updateid FROM updatefile,build2update AS b2u WHERE updatefile.updateid=b2u.updateid AND b2u.buildid=' . qnum($buildid));
    $nupdates = pdo_num_rows($update);
    $xml .= add_XML_value('nupdates', $nupdates);

    $xml .= add_XML_value('command', $update_array['command']);
    $xml .= add_XML_value('type', $update_array['type']);
    $xml .= add_XML_value('starttime', date(FMT_DATETIMETZ, strtotime($update_array['starttime'] . ' UTC')));
    $xml .= add_XML_value('endtime', date(FMT_DATETIMETZ, strtotime($update_array['endtime'] . ' UTC')));
    $xml .= '</update>';
}

// Configure
$xml .= '<configure>';
$configure = pdo_query(
        "SELECT * FROM configure c
        JOIN build2configure b2c ON b2c.configureid=c.id
        WHERE b2c.buildid='$buildid'");
$configure_array = pdo_fetch_array($configure);

$nerrors = 0;
if ($configure_array['status'] != 0) {
    $nerrors = 1;
}

$xml .= add_XML_value('nerrors', $nerrors);
$xml .= add_XML_value('nwarnings', $configure_array['warnings']);

$xml .= add_XML_value('status', $configure_array['status']);
$xml .= add_XML_value('command', $configure_array['command']);
$xml .= add_XML_value('output', format_for_iphone($configure_array['log']));
$xml .= add_XML_value('starttime', date(FMT_DATETIMETZ, strtotime($configure_array['starttime'] . ' UTC')));
$xml .= add_XML_value('endtime', date(FMT_DATETIMETZ, strtotime($configure_array['endtime'] . ' UTC')));
$xml .= '</configure>';

// Test
$xml .= '<test>';
$nerrors = 0;
$nwarnings = 0;
$xml .= add_XML_value('nerrors', $nerrors);
$xml .= add_XML_value('nwarnings', $nwarnings);

$npass_array = pdo_fetch_array(pdo_query("SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND status='passed'"));
$npass = $npass_array[0];
$nnotrun_array = pdo_fetch_array(pdo_query("SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND status='notrun'"));
$nnotrun = $nnotrun_array[0];
$nfail_array = pdo_fetch_array(pdo_query("SELECT count(testid) FROM build2test WHERE buildid='$buildid' AND status='failed'"));
$nfail = $nfail_array[0];

$xml .= add_XML_value('npassed', $npass);
$xml .= add_XML_value('nnotrun', $nnotrun);
$xml .= add_XML_value('nfailed', $nfail);

$xml .= '</test>';

// Coverage
$coverage_array = pdo_fetch_array(pdo_query("SELECT * FROM coveragesummary WHERE buildid='$buildid'"));
if ($coverage_array) {
    $coverage_percent = round($coverage_array['loctested'] /
        ($coverage_array['loctested'] + $coverage_array['locuntested']) * 100, 2);
    $xml .= add_XML_value('coverage', $coverage_percent);
}

// Previous build
// Find the previous build
if ($previous_buildid > 0) {
    $xml .= '<previousbuild>';
    $xml .= add_XML_value('buildid', $previous_buildid);

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
    $xml .= add_XML_value('nupdateerrors', $nupdateerrors);
    $xml .= add_XML_value('nupdatewarnings', $nupdatewarnings);

    $configure = pdo_query(
            "SELECT * FROM configure c
            JOIN build2configure b2c ON b2c.configureid=c.id
            WHERE b2c.buildid='$previous_buildid'");
    $configure_array = pdo_fetch_array($configure);

    $nconfigureerrors = 0;
    if ($configure_array['status'] != 0) {
        $nconfigureerrors = 1;
    }
    $xml .= add_XML_value('nconfigureerrors', $nconfigureerrors);
    $xml .= add_XML_value('nconfigurewarnings', $configure_array['warnings']);

    $xml .= add_XML_value('nerrors', $npreviousbuilderrors);
    $xml .= add_XML_value('nwarnings', $npreviousbuildwarnings);

    $xml .= add_XML_value('ntestfailed', $npreviousfailingtests);
    $xml .= add_XML_value('ntestnotrun', $npreviousnotruntests);

    $xml .= '</previousbuild>';
}
$xml .= '</cdash>';

// Now doing the xslt transition
if (!isset($NoXSLGenerate)) {
    generate_XSLT($xml, 'buildSummary');
}
