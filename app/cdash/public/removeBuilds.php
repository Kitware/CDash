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
require_once 'include/common.php';

use CDash\Config;
use \CDash\Database;

$config = Config::getInstance();

@set_time_limit(0);

$policy = checkUserPolicy(0); // only admin
if ($policy !== true) {
    return $policy;
}

@$projectid = $_GET['projectid'];
if ($projectid != null) {
    $projectid = intval($projectid);
}

$xml = begin_XML_for_XSLT();

$db = Database::getInstance();

//get date info here
@$dayTo = intval($_POST['dayFrom']);
if (empty($dayTo)) {
    $time = strtotime('2000-01-01 00:00:00');

    if (isset($projectid)) {
        // find the first and last builds

        $startttime = $db->executePreparedSingleRow('
                          SELECT starttime
                          FROM build
                          WHERE projectid=?
                          ORDER BY starttime ASC
                          LIMIT 1
                      ', [intval($projectid)]);
        if (!empty($startttime)) {
            $time = strtotime($startttime['starttime']);
        }
    }
    $dayFrom = date('d', $time);
    $monthFrom = date('m', $time);
    $yearFrom = date('Y', $time);
    $dayTo = date('d');
    $yearTo = date('Y');
    $monthTo = date('m');
} else {
    $dayFrom = intval($_POST['dayFrom']);
    $monthFrom = intval($_POST['monthFrom']);
    $yearFrom = intval($_POST['yearFrom']);
    $dayTo = intval($_POST['dayTo']);
    $monthTo = intval($_POST['monthTo']);
    $yearTo = intval($_POST['yearTo']);
}

$xml = '<cdash>';
$xml .= '<cssfile>' . $config->get('CDASH_CSS_FILE') . '</cssfile>';
$xml .= '<version>' . $config->get('CDASH_VERSION') . '</version>';
$xml .= '<title>CDash - Remove Builds</title>';
$xml .= '<menutitle>CDash</menutitle>';
$xml .= '<menusubtitle>Remove Builds</menusubtitle>';
$xml .= '<backurl>manageBackup.php</backurl>';

// List the available projects
$projects = $db->executePrepared('SELECT id, name FROM project');
foreach ($projects as $projects_array) {
    $xml .= '<availableproject>';
    $xml .= add_XML_value('id', $projects_array['id']);
    $xml .= add_XML_value('name', $projects_array['name']);
    if ($projects_array['id'] == $projectid) {
        $xml .= add_XML_value('selected', '1');
    }
    $xml .= '</availableproject>';
}

$xml .= '<dayFrom>' . $dayFrom . '</dayFrom>';
$xml .= '<monthFrom>' . $monthFrom . '</monthFrom>';
$xml .= '<yearFrom>' . $yearFrom . '</yearFrom>';
$xml .= '<dayTo>' . $dayTo . '</dayTo>';
$xml .= '<monthTo>' . $monthTo . '</monthTo>';
$xml .= '<yearTo>' . $yearTo . '</yearTo>';

@$submit = $_POST['Submit'];

// Delete the builds
if (isset($submit)) {
    $build = $db->executePrepared("
                 SELECT id
                 FROM build
                 WHERE
                     projectid=?
                     AND parentid IN (0, -1)
                     AND starttime<=?||'-'||?||'-'||?||' 00:00:00'
                     AND starttime>=?||'-'||?||'-'||?||' 00:00:00'
                 ORDER BY starttime ASC
             ", [
                 intval($projectid),
                 $yearTo,
                 $monthTo,
                 $dayTo,
                 $yearFrom,
                 $monthFrom,
                 $dayFrom
             ]);

    $builds = array();
    foreach ($build as $build_array) {
        $builds[] = intval($build_array['id']);
    }

    remove_build_chunked($builds);
    $xml .= add_XML_value('alert', 'Removed ' . count($builds) . ' builds.');
}

$xml .= '</cdash>';
generate_XSLT($xml, 'removeBuilds');
