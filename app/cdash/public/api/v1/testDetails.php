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

/*
* testDetails.php shows more detailed information for a particular test that
* was run.  This includes test output and image comparison information
*/

require_once 'include/api_common.php';

use App\Models\BuildTest;

use CDash\Model\Build;
use CDash\Controller\Api\TestDetails as Controller;
use CDash\Database;

$db = Database::getInstance();

$buildtestid = $_GET['buildtestid'];
if (!isset($buildtestid) || !is_numeric($buildtestid)) {
    json_error_response(['error' => 'A valid test was not specified.']);
}

$buildtest = BuildTest::where('id', '=', $buildtestid)->first();
if ($buildtest === null) {
    json_error_response(['error' => 'test not found'], 404);
    return;
}


$testid = $buildtest->test->id;
$build = new Build();
$build->Id = $buildtest->buildid;
$build->FillFromId($build->Id);

$controller = new Controller($db, $buildtest);
$response = $controller->getResponse();
if ($controller->echoResponse) {
    echo json_encode(cast_data_for_JSON($response));
}
