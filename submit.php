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
include("ctestparser.php");
include_once("common.php");
include_once("createRSS.php");
include("sendemail.php");

// Open the database connection
include("config.php");
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

//$contents = file_get_contents("DISCWORLD_Win32-make_20080103-1857-Experimental_Configure.xml");
$contents = file_get_contents("php://input");

$projectname = $_GET["project"];
$projectid = get_project_id($projectname);

// Parse the XML
$xml_array = parse_XML($contents);
// Backup the XML file
backup_xml_file($xml_array,$contents);
unset($contents);

// Parse the XML file
ctest_parse($xml_array,$projectid);

// Send the emails if necessary
sendemail($xml_array,$projectid);

// Create the RSS fee
CreateRSSFeed($projectid);
?>
