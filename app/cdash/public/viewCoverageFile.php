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
include_once 'include/common.php';

use CDash\Model\CoverageFile;
use CDash\Model\CoverageFileLog;

@$buildid = $_GET['buildid'];
if ($buildid != null) {
    $buildid = pdo_real_escape_numeric($buildid);
}
@$fileid = $_GET['fileid'];
if ($fileid != null) {
    $fileid = pdo_real_escape_numeric($fileid);
}
@$date = $_GET['date'];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

// Checks
if (!isset($buildid) || !is_numeric($buildid)) {
    echo 'Not a valid buildid!';
    return;
}

@$userid = Auth::id();
if (!isset($userid)) {
    $userid = 0;
}

$build_array = pdo_fetch_array(pdo_query("SELECT starttime,projectid FROM build WHERE id='$buildid'"));
$projectid = $build_array['projectid'];
if (!isset($projectid) || $projectid == 0) {
    echo "This build doesn't exist. Maybe it has been deleted.";
    exit();
}

checkUserPolicy($userid, $projectid);

$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if (pdo_num_rows($project) == 0) {
    echo "This project doesn't exist.";
    exit();
}

$project_array = pdo_fetch_array($project);
$projectname = $project_array['name'];

$role = 0;
$user2project = pdo_query("SELECT role FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
if (pdo_num_rows($user2project) > 0) {
    $user2project_array = pdo_fetch_array($user2project);
    $role = $user2project_array['role'];
}
if (!$project_array['showcoveragecode'] && $role < 2) {
    echo "This project doesn't allow display of coverage code. Contact the administrator of the project.";
    exit();
}

list($previousdate, $currenttime, $nextdate) = get_dates($date, $project_array['nightlytime']);
$logoid = getLogoID($projectid);

$xml = begin_XML_for_XSLT();
$xml .= '<title>CDash : ' . $projectname . '</title>';

$xml .= get_cdash_dashboard_xml_by_name($projectname, $date);

// Build
$xml .= '<build>';
$build = pdo_query("SELECT * FROM build WHERE id='$buildid'");
$build_array = pdo_fetch_array($build);
$siteid = $build_array['siteid'];
$site_array = pdo_fetch_array(pdo_query("SELECT name FROM site WHERE id='$siteid'"));
$xml .= add_XML_value('site', $site_array['name']);
$xml .= add_XML_value('buildname', $build_array['name']);
$xml .= add_XML_value('buildid', $build_array['id']);
$xml .= add_XML_value('buildtime', $build_array['starttime']);
$xml .= '</build>';

// Load coverage file.
$coverageFile = new CoverageFile();
$coverageFile->Id = $fileid;
$coverageFile->Load();

$xml .= '<coverage>';
$xml .= add_XML_value('fullpath', $coverageFile->FullPath);

// Generating the html file
$file_array = explode('<br>', $coverageFile->File);
$i = 0;

// Load the coverage info.
$log = new CoverageFileLog();
$log->BuildId = $buildid;
$log->FileId = $fileid;
$log->Load();

// Detect if we have branch coverage or not.
$hasBranchCoverage = false;
if (!empty($log->Branches)) {
    $hasBranchCoverage = true;
}

foreach ($file_array as $line) {
    $linenumber = $i + 1;
    $line = htmlentities($line);

    $file_array[$i] = '<span class="warning">' . str_pad($linenumber, 5, ' ', STR_PAD_LEFT) . '</span>';

    if ($hasBranchCoverage) {
        if (array_key_exists("$i", $log->Branches)) {
            $code = $log->Branches["$i"];

            // Branch coverage data is stored as <# covered> / <total branches>.
            $branchCoverageData = explode('/', $code);
            if ($branchCoverageData[0] != $branchCoverageData[1]) {
                $file_array[$i] .= '<span class="error">';
            } else {
                $file_array[$i] .= '<span class="normal">';
            }
            $file_array[$i] .= str_pad($code, 5, ' ', STR_PAD_LEFT) . '</span>';
        } else {
            $file_array[$i] .= str_pad('', 5, ' ', STR_PAD_LEFT);
        }
    }

    if (array_key_exists($i, $log->Lines)) {
        $code = $log->Lines[$i];
        if ($code == 0) {
            $file_array[$i] .= '<span class="error">';
        } else {
            $file_array[$i] .= '<span class="normal">';
        }
        $file_array[$i] .= str_pad($code, 5, ' ', STR_PAD_LEFT) . ' | ' . $line;
        $file_array[$i] .= '</span>';
    } else {
        $file_array[$i] .= str_pad('', 5, ' ', STR_PAD_LEFT) . ' | ' . $line;
    }
    $i++;
}

$file = implode('<br>', $file_array);

$xml .= '<file>' . utf8_encode(htmlspecialchars($file)) . '</file>';
$xml .= '</coverage>';
$xml .= '</cdash>';

// Now doing the xslt transition
generate_XSLT($xml, 'viewCoverageFile');
