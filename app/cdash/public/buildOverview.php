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

use CDash\Database;

$projectname = htmlspecialchars($_GET['project'] ?? '');

if (strlen($projectname) === 0) {
    die("Error: project not specified<br>\n");
}

$date = htmlspecialchars($_GET['date'] ?? '');

$xml = begin_XML_for_XSLT();
$xml .= '<title>' . $projectname . ' : Build Overview</title>';
$xml .= get_cdash_dashboard_xml_by_name($projectname, $date);

$db = Database::getInstance();

// Get some information about the specified project
$project_array = $db->executePreparedSingleRow('SELECT id, nightlytime FROM project WHERE name = ?', [$projectname]);
if (empty($project_array)) {
    die("Error:  project $projectname not found<br>\n");
}

$policy = checkUserPolicy(intval($project_array['id']));
if ($policy !== true) {
    return $policy;
}

$projectid = intval($project_array['id']);
$nightlytime = $project_array['nightlytime'];

// We select the builds
list($previousdate, $currentstarttime, $nextdate, $today) = get_dates($date, $nightlytime);
$xml .= '<menu>';
$xml .= add_XML_value('previous', 'buildOverview.php?project=' . urlencode($projectname) . '&date=' . $previousdate);
if (has_next_date($date, $currentstarttime)) {
    $xml .= add_XML_value('next', 'buildOverview.php?project=' . urlencode($projectname) . '&date=' . $nextdate);
} else {
    $xml .= add_XML_value('nonext', '1');
}
$xml .= add_XML_value('current', 'buildOverview.php?project=' . urlencode($projectname) . '&date=');

$xml .= add_XML_value('back', 'index.php?project=' . urlencode($projectname) . '&date=' . $today);
$xml .= '</menu>';

// Return the available groups
$groupSelection = $_POST['groupSelection'] ?? 0;
$groupSelection = intval($groupSelection);

$buildgroup = $db->executePrepared('SELECT id, name FROM buildgroup WHERE projectid=?', [$projectid]);
foreach ($buildgroup as $buildgroup_array) {
    $xml .= '<group>';
    $xml .= add_XML_value('id', $buildgroup_array['id']);
    $xml .= add_XML_value('name', $buildgroup_array['name']);
    if ($groupSelection === intval($buildgroup_array['id'])) {
        $xml .= add_XML_value('selected', '1');
    }
    $xml .= '</group>';
}

// Check the builds
$beginning_timestamp = $currentstarttime;
$end_timestamp = $currentstarttime + 3600 * 24;

$beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
$end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

$groupSelectionSQL = '';
$params = [];
if ($groupSelection > 0) {
    $groupSelectionSQL = " AND b2g.groupid=? ";
    $params[] = $groupSelection;
}

$builds = $db->executePrepared("
              SELECT
                  s.name,
                  b.name AS buildname,
                  be.type,
                  be.sourcefile,
                  be.sourceline,
                  be.text
              FROM
                  build AS b,
                  builderror as be,
                  site AS s,
                  build2group AS b2g
              WHERE
                  b.starttime<?
                  AND b.starttime>?
                  AND b.projectid=?
                  AND be.buildid=b.id
                  AND s.id=b.siteid
                  AND b2g.buildid=b.id
                  $groupSelectionSQL
              ORDER BY
                  be.sourcefile ASC,
                  be.type ASC,
                  be.sourceline ASC
          ", array_merge([$end_UTCDate, $beginning_UTCDate, $projectid], $params));

echo pdo_error();

if (count($builds) === 0) {
    $xml .= '<message>No warnings or errors today!</message>';
}

$current_file = 'ThisIsMyFirstFile';
foreach ($builds as $build_array) {
    if ($build_array['sourcefile'] != $current_file) {
        if ($current_file != 'ThisIsMyFirstFile') {
            $xml .= '</sourcefile>';
        }
        $xml .= '<sourcefile>';
        $xml .= '<name>' . $build_array['sourcefile'] . '</name>';
        $current_file = $build_array['sourcefile'];
    }

    if (intval($build_array['type']) === 0) {
        $xml .= '<error>';
    } else {
        $xml .= '<warning>';
    }
    $xml .= '<line>' . $build_array['sourceline'] . '</line>';
    $textarray = explode("\n", $build_array['text']);
    foreach ($textarray as $text) {
        if (strlen($text) > 0) {
            $xml .= add_XML_value('text', $text);
        }
    }
    $xml .= '<sitename>' . $build_array['name'] . '</sitename>';
    $xml .= '<buildname>' . $build_array['buildname'] . '</buildname>';
    if ($build_array['type'] == 0) {
        $xml .= '</error>';
    } else {
        $xml .= '</warning>';
    }
}

if (count($builds) > 0) {
    $xml .= '</sourcefile>';
}
$xml .= '</cdash>';

generate_XSLT($xml, 'buildOverview');
