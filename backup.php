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
include("config.php");
require_once("pdo.php");
include('login.php');
include("version.php");

function cleanup($filename)
{
  unlink($filename);
}

function readfile_chunked ($filename)
{
  $chunksize = 1*(1024*1024); // how many bytes per chunk
  $buffer = '';
  $handle = fopen($filename, 'rb');
  if ($handle === false)
    {
    return false;
    }
  while (!feof($handle))
    {
    $buffer = fread($handle, $chunksize);
    print $buffer;
    flush();
    }
  return fclose($handle);
}

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
if(!$db)
  {
  echo pdo_error();
  }
if(pdo_select_db("$CDASH_DB_NAME",$db) === FALSE)
  {
  echo pdo_error();
  return;
  }

checkUserPolicy(@$_SESSION['cdash']['loginid'],0); // only admin

$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= "</cdash>";
@$submit = $_POST["Submit"];

if(isset($submit))
  {
  $filename = getcwd() ."/cdash_dump" . time() . ".sql";
  //delete the dump file when we're done executing
  register_shutdown_function(cleanup, $filename);
  $command = "mysqldump -u $CDASH_DB_LOGIN --password=$CDASH_DB_PASS --extended-insert=0 $CDASH_DB_NAME > $filename";
  shell_exec($command);

  //hack to get the size of a large file in php
  $command = "du -b $filename";
  $output = shell_exec($command);
  $strings = explode("\t", $output);
  $filesize = $strings[0];

  header("Content-type: application/octet-stream");
  header("Content-length: $filesize");
  header("Content-disposition: attachment; filename=cdash_backup.sql");
  readfile_chunked($filename);
  unlink($filename);
  exit(1);
  }
else
  {
  generate_XSLT($xml,"backup");
  }
?>
