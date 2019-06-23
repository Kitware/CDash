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

require_once 'include/api_common.php';

use CDash\Controller\Api\Timeline as Controller;
use CDash\Database;

$project = get_project_from_request();
if (is_null($project)) {
    return;
}

$controller = new Controller(Database::getInstance(), $project);
echo json_encode(cast_data_for_JSON($controller->getResponse()));
