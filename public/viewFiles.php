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
include_once 'include/common.php';
include 'include/version.php';
include 'public/login.php';

use CDash\Model\Build;
use CDash\Model\Site;

if (!isset($_GET['buildid'])) {
    echo 'Build id not set';
    return;
}

$buildid = pdo_real_escape_numeric($_GET['buildid']);
$Build = new Build();
$Build->Id = $buildid;
$Build->FillFromId($buildid);
$Site = new Site();
$Site->Id = $Build->SiteId;

$build_array = pdo_fetch_array(pdo_query("SELECT projectid FROM build WHERE id='$buildid'"));
if (!isset($build_array['projectid'])) {
    echo 'Build does not exist. Maybe it has been deleted.';
    return;
}
$projectid = $build_array['projectid'];

checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid);

@$date = $_GET['date'];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

$xml = begin_XML_for_XSLT();
$xml .= get_cdash_dashboard_xml(get_project_name($projectid), $date);

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);
$xml .= add_XML_value('title', 'CDash - Uploaded files');
$xml .= add_XML_value('menutitle', 'CDash');
$xml .= add_XML_value('menusubtitle', 'Uploaded files');

$xml .= '<hostname>' . $_SERVER['SERVER_NAME'] . '</hostname>';
$xml .= '<date>' . date('r') . '</date>';
$xml .= '<backurl>index.php</backurl>';

$xml .= "<buildid>$buildid</buildid>";
$xml .= '<buildname>' . $Build->Name . '</buildname>';
$xml .= '<buildstarttime>' . $Build->StartTime . '</buildstarttime>';
$xml .= '<siteid>' . $Site->Id . '</siteid>';
$xml .= '<sitename>' . $Site->GetName() . '</sitename>';

$uploadFilesOrURLs = $Build->GetUploadedFilesOrUrls();

foreach ($uploadFilesOrURLs as $uploadFileOrURL) {
    if (!$uploadFileOrURL->IsUrl) {
        $xml .= '<uploadfile>';
        $xml .= '<id>' . $uploadFileOrURL->Id . '</id>';
        $xml .= '<href>' . $CDASH_DOWNLOAD_RELATIVE_URL . '/' . $uploadFileOrURL->Sha1Sum . '/' . $uploadFileOrURL->Filename . '</href>';
        $xml .= '<sha1sum>' . $uploadFileOrURL->Sha1Sum . '</sha1sum>';
        $xml .= '<filename>' . $uploadFileOrURL->Filename . '</filename>';
        $xml .= '<filesize>' . $uploadFileOrURL->Filesize . '</filesize>';

        $filesize = $uploadFileOrURL->Filesize;
        $ext = 'b';
        if ($filesize > 1024) {
            $filesize /= 1024;
            $ext = 'Kb';
        }
        if ($filesize > 1024) {
            $filesize /= 1024;
            $ext = 'Mb';
        }
        if ($filesize > 1024) {
            $filesize /= 1024;
            $ext = 'Gb';
        }
        if ($filesize > 1024) {
            $filesize /= 1024;
            $ext = 'Tb';
        }

        $xml .= '<filesizedisplay>' . round($filesize) . ' ' . $ext . '</filesizedisplay>';
        $xml .= '<isurl>' . $uploadFileOrURL->IsUrl . '</isurl>';
        $xml .= '</uploadfile>';
    } else {
        $xml .= '<uploadurl>';
        $xml .= '<id>' . $uploadFileOrURL->Id . '</id>';
        $xml .= '<filename>' . htmlspecialchars($uploadFileOrURL->Filename) . '</filename>';
        $xml .= '</uploadurl>';
    }
}

$xml .= '</cdash>';

generate_XSLT($xml, 'viewFiles', true);
