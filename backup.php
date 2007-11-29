<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: common.php,v $
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include("config.php");
include("common.php");

$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
if(!$db)
  {
  echo mysql_error();
  }
if(mysql_select_db("$CDASH_DB_NAME",$db) === FALSE)
  {
  echo mysql_error();
  return;
  }

$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "</cdash>";
@$submit = $_POST["Submit"];

if(isset($submit))
  {
  $filename = "cdash_dump" . time() . ".sql";
  $command = "mysqldump -u $CDASH_DB_LOGIN --password=$CDASH_DB_PASS --extended-insert=0 $CDASH_DB_NAME > $filename";
  $output = shell_exec($command);

  header("Content-type: application/octet-stream");
  header("Content-length: ".strlen($output));
  header("Content-disposition: attachment; filename=cdash_backup.sql");
  readfile($filename);
  #unlink($filename);
  exit(1);
  }
else
  {
  generate_XSLT($xml,"backup");
  }
?>
