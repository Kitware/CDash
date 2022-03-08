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

$apikey = null;

// Find the correct google map key
foreach ($config->get('CDASH_GOOGLE_MAP_API_KEY') as $key => $value) {
    if (strstr($_SERVER['HTTP_HOST'], $key) !== false) {
        $apikey = $value;
        break;
    }
}
$xml .= add_XML_value('googlemapkey', $apikey);
$xml .= add_XML_value('projectname', $projectname);
$xml .= add_XML_value('projectname_encoded', urlencode($projectname));
$xml .= '</dashboard>';

$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
$project_array = pdo_fetch_array($project);

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

if ($config->get('CDASH_DB_TYPE') == 'pgsql') {
    $site = pdo_query('SELECT s.id,s.name,si.processorclockfrequency,
                     si.description,
                     si.numberphysicalcpus,s.ip,s.latitude,s.longitude,
                     ' . qid('user') . '.firstname,' . qid('user') . '.lastname,' . qid('user') . '.id AS userid
                     FROM build AS b, siteinformation AS si, site as s
                     LEFT JOIN site2user ON (site2user.siteid=s.id)
                     LEFT JOIN ' . qid('user') . ' ON (site2user.userid=' . qid('user') . ".id)
                     WHERE s.id=b.siteid
                     AND b.starttime<'$end_UTCDate' AND b.starttime>'$beginning_UTCDate'
                     AND si.siteid=s.id
                     AND b.projectid='$projectid' GROUP BY s.id,s.name,si.processorclockfrequency,
                     si.description,
                     si.numberphysicalcpus,s.ip,s.latitude,s.longitude," . qid('user') . '.firstname,' . qid('user') . '.lastname,' . qid('user') . '.id');
} else {
    $site = pdo_query('SELECT s.id,s.name,si.processorclockfrequency,
                     si.description,
                     si.numberphysicalcpus,s.ip,s.latitude,s.longitude,
                     ' . qid('user') . '.firstname,' . qid('user') . '.lastname,' . qid('user') . '.id AS userid
                     FROM build AS b, siteinformation AS si, site as s
                     LEFT JOIN site2user ON (site2user.siteid=s.id)
                     LEFT JOIN ' . qid('user') . ' ON (site2user.userid=' . qid('user') . ".id)
                     WHERE s.id=b.siteid
                     AND b.starttime<'$end_UTCDate' AND b.starttime>'$beginning_UTCDate'
                     AND si.siteid=s.id
                     AND b.projectid='$projectid' GROUP BY s.id");
}

echo pdo_error();

while ($site_array = pdo_fetch_array($site)) {
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
