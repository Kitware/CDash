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

namespace CDash\Api\v1\TestGraph;

require_once 'include/api_common.php';

use App\Models\BuildTest;

use CDash\Model\Build;
use CDash\Controller\Api\TestGraph as Controller;
use CDash\Database;

$db = Database::getInstance();

$build = get_request_build();
if (is_null($build)) {
    return;
}

$testid = pdo_real_escape_numeric($_GET['testid']);
if (!isset($testid) || !is_numeric($testid)) {
    abort(400, 'A valid test was not specified.');
}

$buildtest = BuildTest::where('buildid', '=', $build->Id)
    ->where('testid', '=', $testid)
    ->first();
if ($buildtest === null) {
    abort(404, 'test not found');
}

$controller = new Controller($db, $buildtest);
$response = $controller->getResponse();
echo json_encode(cast_data_for_JSON($response));
