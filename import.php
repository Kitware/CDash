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
include_once("config.php");
include_once("common.php");
include_once("ctestparser.php");

$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

  
$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";

$project = mysql_query("SELECT name,id FROM project ORDER BY id");
while($project_array = mysql_fetch_array($project))
  {
  $xml .= "<project>";
  $xml .= "<name>".$project_array["name"]."</name>";
  $xml .= "<id>".$project_array["id"]."</id>";
  $xml .= "</project>";
  }
$xml .= "</cdash>";

// If we should create the tables
@$Submit = $_POST["Submit"];
if($Submit)
{
  $directory = $_POST["directory"];
		$projectid = $_POST["project"];
		
		if(strlen($directory)>0)
		{
		
		$directory = str_replace('\\\\','/',$directory);
		$files = globr($directory,"*.xml");
		foreach($files as $file)
		  {
				if(strlen($file)==0)
				  {
						continue;
						}

    $handle = fopen($file,"r");
    $contents = fread($handle,filesize($file));
				ctest_parse($contents,$projectid);
				fclose($handle);
		  }
				} // end strlen(directory)>0
}

// Now doing the xslt transition
generate_XSLT($xml,"import");
?>