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

//TODO read any fields that indicate where linked properties should go
//$projectname = $_REQUEST["project"];
//$projectid = get_project_id($projectname);

//insert file into database
$file_path='php://input';
$fp = fopen($file_path, 'rb');
$contents = '';
while (!feof($fp)) 
  {
  $contents .= fread($fp, 8192);
  }
fclose($fp);

$md5sum = md5($contents);

$results = pdo_query("SELECT id FROM filesum WHERE md5sum='$md5sum'");
if(pdo_num_rows($results) == 0)
  {
  pdo_query("INSERT INTO filesum (md5sum, contents) VALUES ('$md5sum','$contents')");
  }
else
  {
  echo "Error: file already in database.\n";
  }
?>
