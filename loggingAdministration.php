<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: common.php,v $
  Language:  PHP
  Date:      $Date: 2008-01-25 14:40:27 -0500 (Fri, 25 Jan 2008) $
  Version:   $Revision: 373 $

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include("config.php");
include("common.php"); 

@$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);
$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<backurl>user.php</backurl>";
$xml .= "<title>CDash - Logging Administration</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>Log Viewer</menusubtitle>";
$xml .= "<log>";
$xml .= "<name>cdash.log</name>";
$xml .= "<fullpath>".$CDASH_BACKUP_DIRECTORY."/cdash.log</fullpath>";
$xml .= "</log>";
// List of the file in the directory that have other*.xml
foreach (glob($CDASH_BACKUP_DIRECTORY."/*_Other*.xml") as $filename) {
    $xml .= "<file>";
  $xml .= "<name>".substr($filename,strrpos($filename,"/")+1)."</name>";
  $xml .= "<fullpath>".$filename."</fullpath>";
  $xml .= "</file>";
}

$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"loggingAdministration");
?>
