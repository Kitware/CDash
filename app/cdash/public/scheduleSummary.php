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

include dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';
include_once 'include/common.php';
include 'include/version.php';

use CDash\Model\ClientJobSchedule;
use CDash\Model\Job;
use CDash\Config;

$config = Config::getInstance();

if (!$config->get('CDASH_MANAGE_CLIENTS')) {
    echo 'CDash has not been setup to allow client management';
    return;
}

$userid = Auth::id();

if (!isset($_GET['scheduleid'])) {
    echo 'Schedule id not set';
    return;
}

$scheduleid = $_GET['scheduleid'];
$ClientJobSchedule = new ClientJobSchedule();
$ClientJobSchedule->Id = $scheduleid;
$projectid = $ClientJobSchedule->GetProjectId();

$xml = begin_XML_for_XSLT();
$xml .= add_XML_value('manageclient', $config->get('CDASH_MANAGE_CLIENTS'));

$xml .= add_XML_value('title', 'CDash - Scheduled Build Submissions');
$xml .= add_XML_value('menutitle', 'CDash');
$xml .= add_XML_value('menusubtitle', 'Submitted Builds');

$xml .= '<hostname>' . $_SERVER['SERVER_NAME'] . '</hostname>';
$xml .= '<date>' . date('r') . '</date>';
$xml .= '<backurl>user.php</backurl>';

$builds = $ClientJobSchedule->GetAssociatedBuilds();
foreach ($builds as $buildid) {
    $xml .= '<build>';
    $xml .= add_XML_value('id', $buildid);
    $xml .= '</build>';
}

$status = $ClientJobSchedule->GetStatus();
switch ($status) {
    case Job::SCHEDULED:
        $statusText = 'Scheduled';
        break;
    case Job::RUNNING:
        $statusText = 'Running';
        break;
    case Job::FINISHED:
        $statusText = 'Finished';
        break;
    case Job::FAILED:
        $statusText = 'Failed';
        break;
    case Job::ABORTED:
        $statusText = 'Aborted';
        break;
    default:
        $statusText = 'Unknown';
        break;
}
$xml .= '<status>' . $statusText . '</status>';

$xml .= '</cdash>';
generate_XSLT($xml, 'scheduleSummary', true);
