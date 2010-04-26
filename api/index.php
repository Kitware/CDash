<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/

// Open the database connection
include("../cdash/config.php");
require_once("../cdash/pdo.php");

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

// Add other api includes here
include("api_coverage.php");
include("api_project.php");
include("api_build.php");
include("api_user.php");

if(!isset($_GET['method']))
  {
  echo "Method should be set: method=...";  
  exit();
  }
$method = $_GET['method'];

$classname = ucfirst($method).'API';
$class = new $classname;
$class->Parameters = $_GET;
$results = $class->Run();

// Return json by default
echo json_encode($results);
?>
