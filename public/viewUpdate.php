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

require_once dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';
$noforcelogin = 1;
include 'public/login.php';
require_once 'include/common.php';
require_once 'include/repository.php';
require_once 'include/version.php';
require_once 'include/bugurl.php';
require_once 'models/build.php';
require_once 'models/buildupdate.php';
require_once 'models/buildupdatefile.php';
require_once 'models/project.php';
require_once 'models/site.php';

// Make sure we have a valid build was specified.
if (!isset($_GET['buildid']) || !is_numeric($_GET['buildid'])) {
    echo 'Not a valid buildid!';
    return;
}
$buildid = $_GET['buildid'];
$build = new Build();
$build->Id = $buildid;
if (!$build->Exists()) {
    echo 'This build does not exist. Maybe it has been deleted.';
    return;
}

$build->FillFromId($build->Id);
$project = new Project();
$project->Id = $build->ProjectId;
$project->Fill();
checkUserPolicy(@$_SESSION['cdash']['loginid'], $project->Id);

$xml = begin_XML_for_XSLT();
$xml .= '<title>CDash : ' . $project->Name . '</title>';

$date = get_dashboard_date_from_build_starttime($build->StartTime, $project->NightlyTime);

// Menu
$xml .= '<menu>';
$xml .= add_XML_value('back', 'index.php?project=' . urlencode($project->Name) . '&date=' . $date);

$build = new Build();
$build->Id = $buildid;
$previous_buildid = $build->GetPreviousBuildId();
$current_buildid = $build->GetCurrentBuildId();
$next_buildid = $build->GetNextBuildId();

if ($previous_buildid > 0) {
    $xml .= add_XML_value('previous', "viewUpdate.php?buildid=$previous_buildid");
} else {
    $xml .= add_XML_value('noprevious', '1');
}

$xml .= add_XML_value('current', "viewUpdate.php?buildid=$current_buildid");

if ($next_buildid > 0) {
    $xml .= add_XML_value('next', "viewUpdate.php?buildid=$next_buildid");
} else {
    $xml .= add_XML_value('nonext', '1');
}
$xml .= '</menu>';

$xml .= get_cdash_dashboard_xml_by_name($project->Name, $date);

// Build
$site = new Site();
$site->Id = $build->SiteId;
$site_name = $site->GetName();
$xml .= '<build>';
$xml .= add_XML_value('site', $site_name);
$xml .= add_XML_value('siteid', $site->Id);
$xml .= add_XML_value('buildname', $build->Name);
$xml .= add_XML_value('buildid', $build->Id);
$xml .= add_XML_value('buildtime', date('D, d M Y H:i:s T', strtotime($build->StartTime . ' UTC')));
$xml .= '</build>';

// Update
$update = new BuildUpdate();
$update->BuildId = $build->Id;
$update->FillFromBuildId();
$xml .= '<updates>';
if (strlen($update->Status) > 0 && $update->Status != '0') {
    $xml .= add_XML_value('status', $update->Status);
} else {
    $xml .= add_XML_value('status', ''); // empty status
}
$xml .= add_XML_value('revision', $update->Revision);
$xml .= add_XML_value('priorrevision', $update->PriorRevision);
$xml .= add_XML_value('path', $update->Path);
$xml .= add_XML_value('revisionurl',
    get_revision_url($project->Id, $update->Revision, $update->PriorRevision));
$xml .= add_XML_value('revisiondiff',
    get_revision_url($project->Id, $update->PriorRevision, '')); // no prior prior revision...

$xml .= '<javascript>';

function sort_array_by_directory($a, $b)
{
    return $a > $b ? 1 : 0;
}

function sort_array_by_filename($a, $b)
{
    // Extract directory
    $filenamea = $a['filename'];
    $filenameb = $b['filename'];
    return $filenamea > $filenameb ? 1 : 0;
}

$directoryarray = array();
$updatearray1 = array();
// Create an array so we can sort it
foreach ($update->GetFiles() as $update_file) {
    $file = array();
    $file['filename'] = $update_file->Filename;
    $file['author'] = $update_file->Author;
    $file['status'] = $update_file->Status;

    // Only display email if the user is logged in
    if (isset($_SESSION['cdash'])) {
        if ($update_file->Email == '') {
            $file['email'] = get_author_email($project->Name, $file['author']);
        } else {
            $file['email'] = $update_file->Email;
        }
    } else {
        $file['email'] = '';
    }

    $file['log'] = $update_file->Log;
    $file['revision'] = $update_file->Revision;
    $updatearray1[] = $file;
    $directoryarray[] = substr($update_file->Filename, 0, strrpos($update_file->Filename, '/'));
}

$directoryarray = array_unique($directoryarray);
usort($directoryarray, 'sort_array_by_directory');
usort($updatearray1, 'sort_array_by_filename');

$updatearray = array();

foreach ($directoryarray as $directory) {
    foreach ($updatearray1 as $update) {
        $filename = $update['filename'];
        if (substr($filename, 0, strrpos($filename, '/')) == $directory) {
            $updatearray[] = $update;
        }
    }
}


$locallymodified = array();
$conflictingfiles = array();
$updatedfiles = array();

// locally cached query result same as get_project_property($project->Name, "cvsurl");
foreach ($updatearray as $file) {
    $filename = $file['filename'];
    $filename = str_replace('\\', '/', $filename);
    $directory = substr($filename, 0, strrpos($filename, '/'));

    $pos = strrpos($filename, '/');
    if ($pos !== false) {
        $filename = substr($filename, $pos + 1);
    }

    $baseurl = $project->BugTrackerFileUrl;
    if (empty($baseurl)) {
        $baseurl = $project->BugTrackerUrl;
    }

    $author = $file['author'];
    $email = $file['email'];
    $log = $file['log'];
    $status = $file['status'];
    $revision = $file['revision'];
    $log = str_replace("\r", ' ', $log);
    $log = str_replace("\n", ' ', $log);
    // Do this twice so that <something> ends up as
    // &amp;lt;something&amp;gt; because it gets sent to a
    // java script function not just displayed as html
    $log = XMLStrFormat($log); // Apparently no need to do this twice anymore
    $log = XMLStrFormat($log);

    $log = trim($log);

    $file['directory'] = $directory;
    $file['author'] = $author;
    $file['email'] = $email;
    $file['log'] = $log;
    $file['revision'] = $revision;
    $file['filename'] = $filename;
    $file['bugurl'] = '';
    $file['bugid'] = '0';
    $file['bugpos'] = '0';

    $bug = get_bug_from_log($log, $baseurl);
    if ($bug !== false) {
        $file['bugurl'] = $bug[0];
        $file['bugid'] = $bug[1];
        $file['bugpos'] = $bug[2];
    }

    if ($status == 'UPDATED') {
        $diff_url = get_diff_url($project->Id, $project->CvsUrl, $directory, $filename, $revision);
        $diff_url = XMLStrFormat($diff_url);
        $file['diff_url'] = $diff_url;
        $updatedfiles[] = $file;
    } elseif ($status == 'MODIFIED') {
        $diff_url = get_diff_url($project->Id, $project->CvsUrl, $directory, $filename);
        $diff_url = XMLStrFormat($diff_url);
        $file['diff_url'] = $diff_url;
        $locallymodified[] = $file;
    } else {
        //CONFLICTED

        $diff_url = get_diff_url($project->Id, $project->CvsUrl, $directory, $filename);
        $diff_url = XMLStrFormat($diff_url);
        $file['diff_url'] = $diff_url;
        $conflictingfiles[] = $file;
    }
}

// Updated files
$xml .= 'dbAdd (true, "' . $project->Name . ' Updated files  (' . count($updatedfiles) . ")\", \"\", 0, \"\", \"1\", \"\", \"\", \"\", \"\", \"\", \"\")\n";
$previousdir = '';
foreach ($updatedfiles as $file) {
    $directory = $file['directory'];
    if ($previousdir == '' || $directory != $previousdir) {
        $xml .= ' dbAdd (true, "' . $directory . "\", \"\", 1, \"\", \"1\", \"\", \"\", \"\", \"\", \"\", \"\")\n";
        $previousdir = $directory;
    }
    $xml .= ' dbAdd ( false, "' . $file['filename'] . ' Revision: ' . $file['revision'] . '","' . $file['diff_url'] . '",2,"","1","' . $file['author'] . '","' . $file['email'] . '","' . $file['log'] . '","' . $file['bugurl'] . '","' . $file['bugid'] . '","' . $file['bugpos'] . "\")\n";
}

// Modified files
$xml .= 'dbAdd (true, "Modified files  (' . count($locallymodified) . ")\", \"\", 0, \"\", \"1\", \"\", \"\", \"\", \"\", \"\", \"\")\n";
$previousdir = '';
foreach ($locallymodified as $file) {
    $directory = $file['directory'];
    if ($previousdir == '' || $directory != $previousdir) {
        $xml .= ' dbAdd (true, "' . $directory . "\", \"\", 1, \"\", \"1\", \"\", \"\", \"\", \"\", \"\", \"\")\n";
        $previousdir = $directory;
    }
    $xml .= ' dbAdd ( false, "' . $file['filename'] . '","' . $file['diff_url'] . '",2,"","1","' . $file['author'] . '","' . $file['email'] . '","' . $file['log'] . '","' . $file['bugurl'] . '","' . $file['bugid'] . '","' . $file['bugpos'] . "\")\n";
}

// Conflicting files
$xml .= 'dbAdd (true, "Conflicting files  (' . count($conflictingfiles) . ")\", \"\", 0, \"\", \"1\", \"\", \"\", \"\", \"\", \"\", \"\")\n";
$previousdir = '';
foreach ($conflictingfiles as $file) {
    $directory = $file['directory'];
    if ($previousdir == '' || $directory != $previousdir) {
        $xml .= ' dbAdd (true, "' . $directory . "\", \"\", 1, \"\", \"1\", \"\", \"\", \"\", \"\", \"\")\n";
        $previousdir = $directory;
    }
    $xml .= ' dbAdd ( false, "' . $file['filename'] . ' Revision: ' . $file['revision'] . '","' . $file['diff_url'] . '",2,"","1","' . $file['author'] . '","' . $file['email'] . '","' . $file['log'] . '","' . $file['bugurl'] . '","' . $file['bugid'] . '","' . $file['bugpos'] . "\")\n";
}

$xml .= '</javascript>';
$xml .= '</updates>';
$xml .= '</cdash>';

// Now doing the xslt transition
generate_XSLT($xml, 'viewUpdate');
