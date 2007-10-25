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
@$sortby = $_GET["sortby"];

if(!$sortby)
  {
		$sortby = "filename";
  }

include("config.php");
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);
		
$build_array = mysql_fetch_array(mysql_query("SELECT starttime,projectid FROM build WHERE id='$buildid'"));		
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
		
		// coverage
		$xml .= "<coverage>";
		$coverage = mysql_query("SELECT * FROM coverage WHERE buildid='$buildid'");
		$coverage_array = mysql_fetch_array($coverage);
		$xml .= add_XML_value("starttime",date("l, F d Y",strtotime($build_array["starttime"])));
		$xml .= add_XML_value("loctested",$coverage_array["loctested"]);
		$xml .= add_XML_value("locuntested",$coverage_array["locuntested"]);
		$xml .= add_XML_value("loc",$coverage_array["loc"]);
		$xml .= add_XML_value("percentcoverage",$coverage_array["percentcoverage"]);
		
		$coveredfiles = mysql_query("SELECT count(covered) FROM coveragefile WHERE buildid='$buildid' AND covered='1'");
  $coveredfiles_array = mysql_fetch_array($coveredfiles);
		$ncoveredfiles = $coveredfiles_array[0];
		
		$files = mysql_query("SELECT count(covered) FROM coveragefile WHERE buildid='$buildid'");
  $files_array = mysql_fetch_array($files);
		$nfiles = $files_array[0];
		
		$xml .= add_XML_value("totalcovered",$ncoveredfiles);
		$xml .= add_XML_value("totalfiles",$nfiles);
		$xml .= add_XML_value("totalsatisfactorilycovered",$ncoveredfiles);
		$xml .= add_XML_value("totalunsatisfactorilycovered",$nfiles-$ncoveredfiles);
		$xml .= add_XML_value("buildid",$buildid);
		$xml .= add_XML_value("sortby",$sortby);
  $xml .= "</coverage>";
		
		// Translate the sort by to an SQL orderby
		if($sortby == "filename")
		  {
		  $orderby = "filename ASC";
		  }
		else if($sortby == "status")
		  {
		  $orderby = "coveragemetric";
		  }
		else if($sortby == "percentage")
		  {
		  $orderby = "percentcoverage";
		  }
		else if($sortby == "lines")
		  {
		  $orderby = "locuntested DESC";
		  }		
				
		// Coverage files
		$files = mysql_query("SELECT * FROM coveragefile WHERE buildid='$buildid' ORDER BY $orderby");
  while($files_array = mysql_fetch_array($files))
		  {	
				$xml .= "<coveragefile>";
		  $xml .= add_XML_value("filename",$files_array["filename"]);
	  	$xml .= add_XML_value("fullpath",$files_array["fullpath"]);
				$xml .= add_XML_value("locuntested",$files_array["locuntested"]);
				$xml .= add_XML_value("percentcoverage",$files_array["percentcoverage"]);
				$xml .= add_XML_value("coveragemetric",$files_array["coveragemetric"]);
				$xml .= "</coveragefile>";
				}
  $xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewCoverage");
?>
