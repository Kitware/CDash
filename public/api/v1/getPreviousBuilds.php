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

include dirname(dirname(dirname(__DIR__))) . '/config/config.php';
require_once 'include/pdo.php';
require_once 'include/common.php';
require_once 'models/build.php';

$buildid = pdo_real_escape_numeric($_GET['buildid']);
if (!isset($buildid) || !is_numeric($buildid)) {
    return;
}

// Get details about this build.
$build = new Build();
$build->Id = $buildid;
$build->FillFromId($build->Id);
if ($build->ProjectId < 1) {
    return;
}

// Take subproject into account, such that if there is one, then the
// previous builds must be associated with the same subproject.
//
$subproj_table = '';
$subproj_criteria = '';
if ($build->SubProjectId > 0) {
    $subproj_table =
        'INNER JOIN subproject2build AS sp2b ON (b.id=sp2b.buildid)';
    $subproj_criteria =
        'AND sp2b.subprojectid=:subprojectid';
}

// Get details about previous builds.
// Currently just grabbing the info used for the graphs and charts
// on buildSummary.php.
$pdo = get_link_identifier()->getPdo();
$stmt = $pdo->prepare(
    "SELECT b.id, nfiles, configureerrors, configurewarnings,
     buildwarnings, builderrors, testfailed, b.starttime, b.endtime
     FROM build AS b
     LEFT JOIN build2update AS b2u ON (b2u.buildid=b.id)
     LEFT JOIN buildupdate AS bu ON (b2u.updateid=bu.id)
     $subproj_table
     WHERE siteid=:siteid AND b.type=:type AND name=:name AND
     projectid=:projectid AND b.starttime<=:starttime
     $subproj_criteria
     ORDER BY starttime ASC LIMIT 50");

$stmt->bindParam(':siteid', $build->SiteId);
$stmt->bindParam(':type', $build->Type);
$stmt->bindParam(':name', $build->Name);
$stmt->bindParam(':projectid', $build->ProjectId);
$stmt->bindParam(':starttime', $build->StartTime);
if ($build->SubProjectId > 0) {
    $stmt->bindParam(':subprojectid', $build->SubProjectId);
}
$stmt->execute();

$builds_response = array();
while ($previous_build_row = $stmt->fetch()) {
    $build_response = array();

    $build_response['id'] = $previous_build_row['id'];
    $build_response['nfiles'] = $previous_build_row['nfiles'];
    if (is_null($build_response['nfiles'])) {
        $build_response['nfiles'] = 0;
    }
    $build_response['configurewarnings'] = $previous_build_row['configurewarnings'];
    $build_response['configureerrors'] = $previous_build_row['configureerrors'];
    $build_response['buildwarnings'] = $previous_build_row['buildwarnings'];
    $build_response['builderrors'] = $previous_build_row['builderrors'];
    $build_response['starttime'] = $previous_build_row['starttime'];
    $build_response['testfailed'] = $previous_build_row['testfailed'];

    $duration = strtotime($previous_build_row['endtime']) -
        strtotime($previous_build_row['starttime']);
    $build_response['time'] = $duration;

    $builds_response[] = $build_response;
}

$response = array();
$response['builds'] = $builds_response;
echo json_encode(cast_data_for_JSON($response));
