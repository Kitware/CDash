<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: common.php,v $
  Language:  PHP
  Date:      $Date: 2008-02-04 17:50:42 -0500 (Mon, 04 Feb 2008) $
  Version:   $Revision: 435 $

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include("config.php");
include("common.php");

@$projectid = $_GET["projectid"];

include("config.php");
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);
  
$project = mysql_query("SELECT * FROM project WHERE id='$projectid'");
if(mysql_num_rows($project)==0)
  {
  return;
  }

$project_array = mysql_fetch_array($project);

$ctestconfig = "";

$ctestconfig .= "SET(CTEST_PROJECT_NAME \"".$project_array["name"]."\")\n";
$ctestconfig .= "SET(CTEST_NIGHTLY_START_TIME \"".$project_array["nightlytime"]."\")\n\n";

$ctestconfig .= "IF(NOT DEFINED CTEST_DROP_METHOD)\n";
$ctestconfig .= "  SET(CTEST_DROP_METHOD \"http\")\n";
$ctestconfig .= "ENDIF(NOT DEFINED CTEST_DROP_METHOD)\n\n";

$ctestconfig .= "IF(CTEST_DROP_METHOD STREQUAL \"http\")\n";
$ctestconfig .= "  SET(CTEST_DROP_SITE \"".$_SERVER['SERVER_NAME']."\")\n";

$currentURI = $_SERVER['REQUEST_URI']; 
$currentURI = substr($currentURI,0,strrpos($currentURI,"/"));
   
$ctestconfig .= "  SET(CTEST_DROP_LOCATION \"".$currentURI."/submit.php?project=".$project_array["name"]."\")\n";
$ctestconfig .= "  SET(CTEST_TRIGGER_SITE \"\")\n";
$ctestconfig .= "ENDIF(CTEST_DROP_METHOD STREQUAL \"http\")\n";
	
header('Vary: User-Agent');
if(ob_get_contents())
  echo "Some data has already been output";
if(isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'],'MSIE'))
  header('Content-Type: application/force-download');
else
  header('Content-Type: application/octet-stream');
if(headers_sent())
  echo "Some data has already been output to browser";
   
header("Content-Disposition: attachment; filename=\"CTestConfig.cmake\"");
header("Content-Transfer-Encoding: binary");
header("Content-Length: ".strlen($ctestconfig));
echo $ctestconfig;

?>
