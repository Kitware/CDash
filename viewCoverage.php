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
				
$project = mysql_query("SELECT * FROM project WHERE id='$projectid'");
if(mysql_num_rows($project)>0)
		{
		$project_array = mysql_fetch_array($project);
		$svnurl = $project_array["cvsurl"];
		$homeurl = $project_array["homeurl"];
		$bugurl = $project_array["bugtrackerurl"];			
		$projectname	= $project_array["name"];		
		}

list ($previousdate, $currenttime, $nextdate) = get_dates($date);
$logoid = getLogoID($projectid);

$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>CDash : ".$projectname."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .="<dashboard>
  <datetime>".date("D, d M Y H:i:s",strtotime($build_array["starttime"]))."</datetime>
  <date>".$date."</date>
  <svn>".$svnurl."</svn>
  <bugtracker>".$bugurl."</bugtracker>	
  <home>".$homeurl."</home>
  <projectid>".$projectid."</projectid>	
  <logoid>".$logoid."</logoid>	
  <projectname>".$projectname."</projectname>	
  <previousdate>".$previousdate."</previousdate>	
  <nextdate>".$nextdate."</nextdate>	
  </dashboard>
  ";
		
		// coverage
		$xml .= "<coverage>";
		$coverage = mysql_query("SELECT * FROM coveragesummary WHERE buildid='$buildid'");
		$coverage_array = mysql_fetch_array($coverage);
		$xml .= add_XML_value("starttime",date("l, F d Y",strtotime($build_array["starttime"])));
		$xml .= add_XML_value("loctested",$coverage_array["loctested"]);
		$xml .= add_XML_value("locuntested",$coverage_array["locuntested"]);
		
		$loc = $coverage_array["loctested"]+$coverage_array["locuntested"];
		$percentcoverage = round($coverage_array["loctested"]/($coverage_array["loctested"]+$coverage_array["locuntested"])*100,2);
		$xml .= add_XML_value("loc",$loc);
		$xml .= add_XML_value("percentcoverage",$percentcoverage);
		
		$coveredfiles = mysql_query("SELECT count(covered) FROM coverage WHERE buildid='$buildid' AND covered='1'");
  $coveredfiles_array = mysql_fetch_array($coveredfiles);
		$ncoveredfiles = $coveredfiles_array[0];
		
		$files = mysql_query("SELECT count(covered) FROM coverage WHERE buildid='$buildid'");
  $files_array = mysql_fetch_array($files);
		$nfiles = $files_array[0];
		
		$xml .= add_XML_value("totalcovered",$ncoveredfiles);
		$xml .= add_XML_value("totalfiles",$nfiles);
		$xml .= add_XML_value("totalsatisfactorilycovered",$ncoveredfiles);
		$xml .= add_XML_value("totalunsatisfactorilycovered",$nfiles-$ncoveredfiles);
		$xml .= add_XML_value("buildid",$buildid);
		$xml .= add_XML_value("sortby",$sortby);
  $xml .= "</coverage>";
		
		
				
		// Coverage files
		$coveragefile = mysql_query("SELECT cf.filename,cf.fullpath,c.fileid,c.locuntested,c.loctested 
		                             FROM coverage AS c,coveragefile AS cf WHERE c.buildid='$buildid' AND cf.id=c.fileid");
		
		$covfile_array = array();
  while($coveragefile_array = mysql_fetch_array($coveragefile))
		  {
				$covfile["filename"] = $coveragefile_array["filename"];
				$covfile["fullpath"] = $coveragefile_array["fullpath"];
				$covfile["locuntested"] = $coveragefile_array["locuntested"];
				$covfile["loctested"] = $coveragefile_array["loctested"];		
		  $covfile_array[] = $covfile;
		  }
		
		// Do the sorting
		function sort_array($a,$b)
		  { 
			 global $sortby;	
				if($sortby == "filename")
						{
						return $a["filename"]>$b["filename"] ? 1:0;
						}
				else if($sortby == "status")
						{
						return $a["filename"]>$b["filename"] ? 1:0;
						}
				else if($sortby == "percentage")
						{
						return $a["loctested"]/($a["loctested"]+$a["locuntested"])>$b["loctested"]/($b["loctested"]+$b["locuntested"]) ? 1:0;
						}
				else if($sortby == "lines")
						{
						return $a["locuntested"]<$b["locuntested"] ? 1:0;
						}				
				}
				
		usort($covfile_array,"sort_array");
		
		foreach($covfile_array as $covfile)
		  {	
				$xml .= "<coveragefile>";				
		  $xml .= add_XML_value("filename",$covfile["filename"]);
	  	$xml .= add_XML_value("fullpath",$covfile["fullpath"]);
				$xml .= add_XML_value("locuntested",$covfile["locuntested"]);
				$percentcoverage = round($covfile["loctested"]/($covfile["loctested"]+$covfile["locuntested"])*100,2);
				$xml .= add_XML_value("percentcoverage",$percentcoverage);
				$xml .= "</coveragefile>";
				}
				
  $xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewCoverage");
?>
