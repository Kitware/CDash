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
require_once 'include/api_common.php';
$noforcelogin = 1;
include 'public/login.php';

use CDash\Database;

$build = get_request_build();

if (is_null($build)) {
    return;
}

$response = [];
$pdo = Database::getInstance()->getPdo();

// Find previous submissions from this build.
$stmt = $pdo->prepare(
    'SELECT b.id, b.starttime, bu.nfiles FROM build as b
    JOIN build2update AS b2u ON b2u.buildid = b.id
    JOIN buildupdate AS bu ON bu.id = b2u.updateid
    WHERE b.siteid = :siteid AND b.type = :type AND b.name = :name AND
          b.projectid = :projectid AND b.starttime <= :starttime
    ORDER BY b.starttime ASC');
$params = [
    ':siteid' => $build->SiteId,
    ':type' => $build->Type,
    ':name' => $build->Name,
    ':projectid' => $build->ProjectId,
    ':starttime' => $build->StartTime
];
pdo_execute($stmt, $params);

$response = [];
$response['data'] = [];
$response['buildids'] = [];

while ($row = $stmt->fetch()) {
    $t = strtotime($row['starttime']) * 1000; //flot expects milliseconds
    $response['data'][] = [$t, $row['nfiles']];
    $response['buildids'][$t] = $row['id'];
}

echo json_encode(cast_data_for_JSON($response));
