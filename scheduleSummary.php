<?php
/*=========================================================================
 Program:   CDash - Cross-Platform Dashboard System
 Module:    $Id$
 Language:  PHP
 Date:      $Date$
 Version:   $Revision$
 Copyright (c) 2002 Kitware, Inc.  All rights reserved.
 See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.
 This software is distributed WITHOUT ANY WARRANTY; without even
 the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 PURPOSE.  See the above copyright notices for more information.
 =========================================================================*/
require_once("cdash/pdo.php");
include_once('cdash/common.php');
include("cdash/version.php");

include_once('models/project.php');
include_once("models/clientjobschedule.php");

if (!$CDASH_MANAGE_CLIENTS) {
    echo "CDash has not been setup to allow client management";
    return;
}

$userid = $_SESSION['cdash']['loginid'];

if (!isset($_GET['scheduleid'])) {
    echo "Schedule id not set";
    return;
}

$scheduleid = $_GET['scheduleid'];
$ClientJobSchedule = new ClientJobSchedule();
$ClientJobSchedule->Id = $scheduleid;
$projectid = $ClientJobSchedule->GetProjectId();

$xml = begin_XML_for_XSLT();
$xml .= add_XML_value("manageclient", $CDASH_MANAGE_CLIENTS);

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);
$xml .= add_XML_value("title", "CDash - Scheduled Build Submissions");
$xml .= add_XML_value("menutitle", "CDash");
$xml .= add_XML_value("menusubtitle", "Submitted Builds");

$xml .= "<hostname>".$_SERVER['SERVER_NAME']."</hostname>";
$xml .= "<date>".date("r")."</date>";
$xml .= "<backurl>user.php</backurl>";

$builds = $ClientJobSchedule->GetAssociatedBuilds();
foreach ($builds as $buildid) {
    $xml .= '<build>';
    $xml .= add_XML_value("id", $buildid);
    $xml .= '</build>';
}

$status = $ClientJobSchedule->GetStatus();
switch ($status) {
  case CDASH_JOB_SCHEDULED:
    $statusText = "Scheduled";
    break;
  case CDASH_JOB_RUNNING:
    $statusText = "Running";
    break;
  case CDASH_JOB_FINISHED:
    $statusText = "Finished";
    break;
  case CDASH_JOB_FAILED:
    $statusText = "Failed";
    break;
  case CDASH_JOB_ABORTED:
    $statusText = "Aborted";
    break;
  default:
    $statusText = "Unknown";
    break;
  }
$xml .= '<status>'.$statusText.'</status>';

$xml .= "</cdash>";
generate_XSLT($xml, "scheduleSummary", true);
