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

$config = Config::getInstance();

@$projectname = $_GET['project'];
if ($projectname != null) {
    $projectname = htmlspecialchars($projectname);
}

@$date = $_GET['date'];
if ($date != null) {
    $date = htmlspecialchars($date);
}

$projectid = get_project_id($projectname);

if ($projectid == -1) {
    echo 'Wrong project name';
    return;
}

$policy = checkUserPolicy($projectid);
if ($policy !== true) {
    return $policy;
}

$xml = begin_XML_for_XSLT();
$xml .= '<title>CDash : Sites map for ' . $projectname . '</title>';
$xml .= '<backurl>index.php?project=' . urlencode($projectname) . "&#38;date=$date</backurl>";
$xml .= '<menutitle>CDash</menutitle>';
$xml .= '<menusubtitle>Build location</menusubtitle>';

$xml .= '<dashboard>';
$xml .= '<title>CDash</title>';
$xml .= '<date>' . $date . '</date>';

$apikey = config('cdash.google_map_api_key');

$xml .= add_XML_value('googlemapkey', $apikey);
$xml .= add_XML_value('projectname', $projectname);
$xml .= add_XML_value('projectname_encoded', urlencode($projectname));
$xml .= '</dashboard>';

$db = Database::getInstance();

$project_array = $db->executePreparedSingleRow('SELECT * FROM project WHERE id=?', [$projectid]);

list($previousdate, $currenttime, $nextdate) = get_dates($date, $project_array['nightlytime']);

$nightlytime = strtotime($project_array['nightlytime']);

$nightlyhour = gmdate('H', $nightlytime);
$nightlyminute = gmdate('i', $nightlytime);
$nightlysecond = gmdate('s', $nightlytime);

$end_timestamp = $currenttime - 1; // minus 1 second when the nightly start time is midnight exactly

$beginning_timestamp = gmmktime($nightlyhour, $nightlyminute, $nightlysecond, gmdate('m', $end_timestamp), gmdate('d', $end_timestamp), gmdate('Y', $end_timestamp));
if ($end_timestamp < $beginning_timestamp) {
    $beginning_timestamp = gmmktime($nightlyhour, $nightlyminute, $nightlysecond, gmdate('m', $end_timestamp - 24 * 3600), gmdate('d', $end_timestamp - 24 * 3600), gmdate('Y', $end_timestamp - 24 * 3600));
}

$beginning_UTCDate = gmdate(FMT_DATETIME, $beginning_timestamp);
$end_UTCDate = gmdate(FMT_DATETIME, $end_timestamp);

if (config('database.default') == 'pgsql') {
    $site = $db->executePrepared('
                SELECT
                    s.id,
                    s.name,
                    si.processorclockfrequency,
                    si.description,
                    si.numberphysicalcpus,
                    s.ip,
                    s.latitude,
                    s.longitude,
                    u.firstname,
                    u.lastname,
                    u.id AS userid
                FROM
                    build AS b,
                    siteinformation AS si,
                    site as s
                LEFT JOIN site2user ON (site2user.siteid=s.id)
                LEFT JOIN ' . qid('user') . ' AS u ON (site2user.userid=u.id)
                WHERE
                    s.id=b.siteid
                    AND b.starttime<?
                    AND b.starttime>?
                    AND si.siteid=s.id
                    AND b.projectid=?
                GROUP BY
                    s.id,
                    s.name,
                    si.processorclockfrequency,
                    si.description,
                    si.numberphysicalcpus,
                    s.ip,
                    s.latitude,
                    s.longitude,
                    u.firstname,
                    u.lastname,
                    u.id
            ', [$end_UTCDate, $beginning_UTCDate, $projectid]);
} else {
    $site = $db->executePrepared('
                SELECT
                    s.id,
                    s.name,
                    si.processorclockfrequency,
                    si.description,
                    si.numberphysicalcpus,
                    s.ip,
                    s.latitude,
                    s.longitude,
                    u.firstname,
                    u.lastname,
                    u.id AS userid
                FROM
                    build AS b,
                    siteinformation AS si,
                    site as s
                LEFT JOIN site2user ON (site2user.siteid=s.id)
                LEFT JOIN ' . qid('user') . ' AS u ON (site2user.userid=u.id)
                WHERE
                    s.id=b.siteid
                    AND b.starttime<?
                    AND b.starttime>?
                    AND si.siteid=s.id
                    AND b.projectid=?
                GROUP BY s.id
            ', [$end_UTCDate, $beginning_UTCDate, $projectid]);
}

echo pdo_error();

foreach ($site as $site_array) {
    $xml .= '<site>';
    $xml .= add_XML_value('name', $site_array['name']);
    $xml .= add_XML_value('id', $site_array['id']);
    $xml .= add_XML_value('description', $site_array['description']);
    $xml .= add_XML_value('processor_speed', getByteValueWithExtension($site_array['processorclockfrequency'] * 1024 * 1024));
    $xml .= add_XML_value('numberphysicalcpus', $site_array['numberphysicalcpus']);
    $xml .= add_XML_value('latitude', $site_array['latitude']);
    $xml .= add_XML_value('longitude', $site_array['longitude']);
    $xml .= add_XML_value('longitude', $site_array['longitude']);
    $xml .= add_XML_value('maintainer_name', $site_array['firstname'] . ' ' . $site_array['lastname']);
    $xml .= add_XML_value('maintainer_id', $site_array['userid']);
    $xml .= '</site>';
}

$xml .= '</cdash>';

// Now doing the xslt transition
generate_XSLT($xml, 'viewMap');
