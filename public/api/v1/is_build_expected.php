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
require_once dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
require_once 'include/api_common.php';

$response = array();

$build = get_request_build();

// Get details about this build.
$buildid = $build->Id;
$buildtype = $build->Type;
$buildname = $build->Name;
$siteid = $build->SiteId;

// Lookup what group this build currently belongs to.
$currentgroup = pdo_query(
    "SELECT g.id FROM buildgroup AS g, build2group as b2g
   WHERE g.id=b2g.groupid AND b2g.buildid='$buildid'");
$currentgroup_array = pdo_fetch_array($currentgroup);
$currentgroupid = $currentgroup_array['id'];

// Lookup whether or not this build is expected.
// This works only for the most recent dashboard (and future)
$response['expected'] = 0;

$build2groupexpected = pdo_query(
    "SELECT groupid FROM build2grouprule
   WHERE groupid='$currentgroupid' AND buildtype='$buildtype' AND
         buildname='$buildname' AND siteid='$siteid' AND
         endtime='1980-01-01 00:00:00' AND expected='1'");
if (pdo_num_rows($build2groupexpected) > 0) {
    $response['expected'] = 1;
}

echo json_encode($response);
