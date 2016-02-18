<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id: buildOverview.php 1161 2008-09-19 14:56:14Z jjomier $
  Language:  PHP
  Date:      $Date: 2008-01-25 14:40:27 -0500 (Fri, 25 Jan 2008) $
  Version:   $Revision: 373 $

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/

include(dirname(__DIR__)."/config/config.php");
require_once("include/pdo.php");
include('public/login.php');
include_once("include/common.php");
include("include/version.php");

@$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

checkUserPolicy(@$_SESSION['cdash']['loginid'], 0); // only admin

$xml = begin_XML_for_XSLT();

$xml .= "<backurl>user.php</backurl>";
$xml .= "<title>CDash - Logging Administration</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>Log Viewer</menusubtitle>";

$xml .= "<log>";
if ($CDASH_LOG_FILE === false) {
    $xml .= "See entries for 'cdash' in the syslog.";
} else {
    $contents = file_get_contents($CDASH_LOG_FILE);
    if ($contents !== false) {
        $xml .= htmlentities($contents);
    }
}
$xml .= "</log>";
// List of the file in the directory that have other*.xml
foreach (glob($CDASH_BACKUP_DIRECTORY."/*_Other*.xml") as $filename) {
    $xml .= "<file>";
    $xml .= "<name>".substr($filename, strrpos($filename, "/")+1)."</name>";
    $xml .= "<fullpath>".$filename."</fullpath>";
    $xml .= "</file>";
}
$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml, "loggingAdministration");
