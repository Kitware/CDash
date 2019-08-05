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
require_once 'include/pdo.php';

// Add other api includes here
require 'api_coverage.php';
require 'api_project.php';
require 'api_build.php';
require 'api_user.php';
require 'api_repository.php';

if (!isset($_GET['method'])) {
    echo 'Method should be set: method=...';
    return;
}
$method = htmlspecialchars(pdo_real_escape_string($_GET['method']));

$classname = ucfirst($method) . 'API';
$class = new $classname;
$class->Parameters = array_merge($_GET, $_POST);
$results = $class->Run();

// Return json by default
echo json_encode(cast_data_for_JSON($results), JSON_UNESCAPED_SLASHES);
