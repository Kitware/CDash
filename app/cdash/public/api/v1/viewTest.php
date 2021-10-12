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

/**
 * View tests of a particular build.
 *
 * GET /viewTest.php
 * Required Params:
 * buildid=[integer] The ID of the build
 *
 * Optional Params:
 *
 * date=[YYYY-mm-dd]
 * tests=[array of test names]
 *   If tests is passed the following parameters apply:
 *       Required:
 *         projectid=[integer]
 *         groupid=[integer]
 *       Optional:
 *         previous_builds=[comma separated list of build ids]
 *         time_begin=[SQL compliant comparable to timestamp]
 *         time_end=[SQL compliant comparable to timestamp]
 * onlypassed=[presence]
 * onlyfailed=[presence]
 * onlytimestatus=[presence]
 * onlynotrun=[presence]
 * onlydelta=[presence]
 * filterstring
 * export=[presence]
 **/

require_once 'include/api_common.php';

use CDash\Controller\Api\ViewTest as Controller;
use CDash\Database;

$db = Database::getInstance();
$controller = new Controller($db, get_request_build());
$response = $controller->getResponse();
if ($controller->JSONEncodeResponse) {
    echo json_encode(cast_data_for_JSON($response));
} else {
    return $response;
}
