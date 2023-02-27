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

use CDash\Config;
use CDash\Database;
use CDash\Model\Project;
use CDash\Model\SubProject;

$config = Config::getInstance();

@$projectname = $_GET['project'];
if ($projectname != null) {
    $projectname = htmlspecialchars(pdo_real_escape_string($projectname));
}

@$date = $_GET['date'];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

$projectid = get_project_id($projectname);

if ($projectid == 0) {
    echo 'Invalid project';
    return;
}

$db = Database::getInstance();
$project_array = $db->executePreparedSingleRow('SELECT * FROM project WHERE id=?', [$projectid]);
if (!empty($project_array)) {
    $svnurl = make_cdash_url(htmlentities($project_array['cvsurl']));
    $homeurl = make_cdash_url(htmlentities($project_array['homeurl']));
    $bugurl = make_cdash_url(htmlentities($project_array['bugtrackerurl']));
    $googletracker = htmlentities($project_array['googletracker']);
    $docurl = make_cdash_url(htmlentities($project_array['documentationurl']));
    $projectpublic = $project_array['public'];
    $projectname = $project_array['name'];
} else {
    $projectname = 'NA';
}

$policy = checkUserPolicy($project_array['id']);
if ($policy !== true) {
    return $policy;
}

$xml = begin_XML_for_XSLT();
$xml .= '<title>CDash - SubProject dependencies - ' . $projectname . '</title>';

list($previousdate, $currentstarttime, $nextdate) = get_dates($date, $project_array['nightlytime']);
$logoid = getLogoID(intval($projectid));

// Main dashboard section
$xml .=
    '<dashboard>
  <datetime>' . date('l, F d Y H:i:s T', time()) . '</datetime>
  <date>' . $date . '</date>
  <unixtimestamp>' . $currentstarttime . '</unixtimestamp>
  <svn>' . $svnurl . '</svn>
  <bugtracker>' . $bugurl . '</bugtracker>
  <googletracker>' . $googletracker . '</googletracker>
  <documentation>' . $docurl . '</documentation>
  <logoid>' . $logoid . '</logoid>
  <projectid>' . $projectid . '</projectid>
  <projectname>' . $projectname . '</projectname>
  <projectname_encoded>' . urlencode($projectname) . '</projectname_encoded>
  <previousdate>' . $previousdate . '</previousdate>
  <projectpublic>' . $projectpublic . '</projectpublic>
  <nextdate>' . $nextdate . '</nextdate>';

if (empty($project_array['homeurl'])) {
    $xml .= '<home>index.php?project=' . urlencode($projectname) . '</home>';
} else {
    $xml .= '<home>' . $homeurl . '</home>';
}
if ($currentstarttime > time()) {
    $xml .= '<future>1</future>';
} else {
    $xml .= '<future>0</future>';
}
$xml .= '</dashboard>';

// Menu definition
$xml .= '<menu>';
if (!isset($date) || strlen($date) < 8 || date(FMT_DATE, $currentstarttime) == date(FMT_DATE)) {
    $xml .= add_XML_value('nonext', '1');
}
$xml .= '</menu>';

// Check the builds
$beginning_timestamp = $currentstarttime;
$end_timestamp = $currentstarttime + 3600 * 24;

$beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
$end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

$Project = new Project();
$Project->Id = $projectid;
$subprojectids = $Project->GetSubProjects();

sort($subprojectids);

$row = 0;
foreach ($subprojectids as $subprojectid) {
    $xml .= '<subproject>';
    $SubProject = new SubProject();
    $SubProject->SetId($subprojectid);

    if ($row == 0) {
        $xml .= add_XML_value('bgcolor', '#EEEEEE');
        $row = 1;
    } else {
        $xml .= add_XML_value('bgcolor', '#DDDDDD');
        $row = 0;
    }

    $xml .= add_XML_value('name', $SubProject->GetName());
    $xml .= add_XML_value('name_encoded', urlencode($SubProject->GetName()));

    $dependencies = $SubProject->GetDependencies($date);
    foreach ($subprojectids as $subprojectid2) {
        $xml .= '<dependency>';
        if (in_array($subprojectid2, $dependencies) || $subprojectid == $subprojectid2) {
            $xml .= add_XML_value('id', $subprojectid);
        }
        $xml .= '</dependency>';
    }
    $xml .= '</subproject>';
}
$xml .= '</cdash>';

// Now doing the xslt transition
generate_XSLT($xml, 'viewSubProjectDependencies');
