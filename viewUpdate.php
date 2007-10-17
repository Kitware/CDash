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

@$buildid = $_GET["buildid"];
@$date = $_GET["date"];

include("config.php");
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);
		
$build_array = mysql_fetch_array(mysql_query("SELECT projectid FROM build WHERE id='$buildid'"));		
$projectid = $build_array["projectid"];

if(!isset($date) || strlen($date)==0)
	  	{ 
				$currenttime = time();
		  }
		else
		  {
				$currenttime = mktime("23","59","0",substr($date,4,2),substr($date,6,2),substr($date,0,4));
		  }
				
$project = mysql_query("SELECT * FROM project WHERE id='$projectid'");
if(mysql_num_rows($project)>0)
		{
		$project_array = mysql_fetch_array($project);
		$svnurl = $project_array["cvsurl"];
		$homeurl = $project_array["homeurl"];
		$bugurl = $project_array["bugtrackerurl"];			
		$projectname	= $project_array["name"];		
		}

		$previousdate = date("Ymd",$currenttime-24*3600);	
		$nextdate = date("Ymd",$currenttime+24*3600);

$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .="<dashboard>
  <datetime>".date("D, d M Y H:i:s",$currenttime)."</datetime>
  <date>".date("l, F d Y",$currenttime)."</date>
		<svn>".$svnurl."</svn>
		<bugtracker>".$bugurl."</bugtracker>	
		<home>".$homeurl."</home>
		<projectid>".$projectid."</projectid>	
  <projectname>".$projectname."</projectname>	
		<previousdate>".$previousdate."</previousdate>	
		<nextdate>".$nextdate."</nextdate>	
		</dashboard>
  ";
		
		// Build
		$xml .= "<build>";
		$build = mysql_query("SELECT * FROM build WHERE id='$buildid'");
		$build_array = mysql_fetch_array($build); 
		$siteid = $build_array["siteid"];
		$site_array = mysql_fetch_array(mysql_query("SELECT name FROM site WHERE id='$siteid'"));
		$xml .= add_XML_value("site",$site_array["name"]);
		$xml .= add_XML_value("buildname",$build_array["name"]);
		$xml .= add_XML_value("buildid",$build_array["id"]);
		$xml .= add_XML_value("buildtime",$build_array["starttime"]);		
			
  $xml .= "</build>";
		
		$xml .= "<updates>";
		
		// Regretfully this is not correct and need to be fixes
		$updatedfiles = mysql_query("SELECT * FROM updatefile WHERE buildid='$buildid' ORDER BY REVERSE(RIGHT(REVERSE(filename),LOCATE('/',REVERSE(filename)))) ");
		
		
		$xml .= "dbAdd (true, \"Updated files  (".mysql_num_rows($updatedfiles).")\", \"\", 0, \"\", \"1\", \"\", \"\", \"\")\n";
		$previousdir = "";
		while($file_array = mysql_fetch_array($updatedfiles))
		  {
				$filename = $file_array["filename"];
				$directory = substr($filename,0,strrpos($filename,"/"));
				$file = substr($filename,strrpos($filename,"/")+1);
				
				if($directory != $previousdir)
				  {
						$xml .= " dbAdd (true, \"&lt;b&gt;".$directory."&lt;/b&gt;\", \"\", 1, \"\", \"1\", \"\", \"\", \"\")\n";
						$previousdir = $directory;
      }
				
				$author = $file_array["author"];
				$email = $file_array["email"];
				$log = $file_array["log"];
				$revision = $file_array["revision"];
				$log = str_replace("\n"," ",$log);
				$log = str_replace("\r"," ",$log);
				$xml .= " dbAdd ( false, \"".$file." Revision: ".$revision."\",\"http://test\",2,\"\",\"1\",\"".$author."\",\"".$email."\",\"".$log."\")\n";
		  }
		$xml .= "dbAdd (true, \"Modified files  (0)\", \"\", 0, \"\", \"1\", \"\", \"\", \"\")\n";
  $xml .= "dbAdd (true, \"Conflicting files  (0)\", \"\", 0, \"\", \"1\", \"\", \"\", \"\")\n";

		$xml .= "</updates>";
  $xml .= "</cdash>";
 

// Now doing the xslt transition
generate_XSLT($xml,"viewUpdate");
?>
