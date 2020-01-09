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
require_once 'include/common.php';
require_once 'include/pdo.php';

@$project = $_GET['project'];
@$site = $_GET['site'];
@$siteid = $_GET['siteid'];
@$stamp = $_GET['stamp'];
@$name = $_GET['name'];

$project = htmlspecialchars(pdo_real_escape_string($project));
$site = htmlspecialchars(pdo_real_escape_string($site));
$siteid = pdo_real_escape_numeric($siteid);
$stamp = htmlspecialchars(pdo_real_escape_string($stamp));
$name = htmlspecialchars(pdo_real_escape_string($name));

$projectid = get_project_id($project);

$xml = '<?xml version="1.0" encoding="UTF-8"?>';
$xml .= '<buildid>';

if (!is_numeric($projectid)) {
    $xml .= 'not found</buildid>';
    return response($xml, 404)->header('Content-Type', 'application/xml');
}

if (!array_key_exists('siteid', $_GET)) {
    $sitequery = pdo_query("SELECT id FROM site WHERE name='$site'");
    if (pdo_num_rows($sitequery) > 0) {
        $site_array = pdo_fetch_array($sitequery);
        $siteid = $site_array['id'];
    }
}

if (!is_numeric($siteid)) {
    $xml .= 'wrong site</buildid>';
    return response($xml, 404)->header('Content-Type', 'application/xml');
}

$buildquery = pdo_query("SELECT id FROM build WHERE siteid='$siteid' AND projectid='$projectid'
                         AND name='$name' AND stamp='$stamp'");

if (pdo_num_rows($buildquery) > 0) {
    $buildarray = pdo_fetch_array($buildquery);
    $buildid = $buildarray['id'];
    $xml .= $buildid . '</buildid>';
    return response($xml, 400)->header('Content-Type', 'application/xml');
}

$xml .= 'not found</buildid>';
return response($xml, 404)->header('Content-Type', 'application/xml');
