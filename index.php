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

/** Generate the main dashboard XML */
function generate_main_dasboard_XML($projectid,$date)
{
  include("config.php");
 	$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  mysql_select_db("$CDASH_DB_NAME",$db);
		
	 $project = mysql_query("SELECT * FROM project WHERE id='$projectid'");
		if(mysql_num_rows($project)>0)
		  {
				$project_array = mysql_fetch_array($project);
				$svnurl = $project_array["cvsurl"];
				$homeurl = $project_array["homeurl"];
				$bugurl = $project_array["bugtrackerurl"];			
				$projectname	= $project_array["name"];		
		  }
		else
		  {
		  $projectname = "NA";
		  }
				
	 $xml = '<?xml version="1.0"?><cdash>';
		$xml .= "<title>CDash : ".$projectname."</title>";
		$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
		
		if(!isset($date) || strlen($date)==0)
	  	{ 
				$currenttime = time();
		  }
		else
		  {
				$currenttime = mktime("23","59","0",substr($date,4,2),substr($date,6,2),substr($date,0,4));
		  }
		
		$previousdate = date("Ymd",$currenttime-24*3600);	
		$nextdate = date("Ymd",$currenttime+24*3600);
		
		// Main dashboard section 
		$xml .=
  "<dashboard>
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
		
		// builds
  $xml .= "<builds>";
	

		$totalerrors = 0;
		$totalwarnings = 0;
		$totalconfigure = 0;
		$totalnotrun = 0;
		$totalfail= 0;
		$totalpass = 0;	
		$totalna = 0;	
				
  // Check the builds
		$beginning_timestamp = $currenttime-($CDASH_DASHBOARD_TIMEFRAME*3600);
		$end_timestamp = $currenttime;
  $builds = mysql_query("SELECT * FROM build 
		                       WHERE UNIX_TIMESTAMP(starttime)<$end_timestamp AND UNIX_TIMESTAMP(starttime)>$beginning_timestamp
																									AND projectid='$projectid'
																									");
		echo mysql_error();
		while($build_array = mysql_fetch_array($builds))
		  {  
				// Get the site name
				$xml .= "<".strtolower($build_array["type"]).">";
				$siteid = $build_array["siteid"];
				$buildid = $build_array["id"];
				$site_array = mysql_fetch_array(mysql_query("SELECT name FROM site WHERE id='$siteid'"));
				
				$xml .= add_XML_value("site",$site_array["name"]);
				$xml .= add_XML_value("siteid",$siteid);
				$xml .= add_XML_value("buildname",$build_array["name"]);
				$xml .= add_XML_value("buildid",$build_array["id"]);
    $xml .= add_XML_value("generator",$build_array["generator"]);
    //<notes>note</notes>
				
				$update = mysql_query("SELECT buildid FROM updatefile WHERE buildid='$buildid'");
			 $xml .= add_XML_value("update",mysql_num_rows($update));
    
    $xml .= "<build>";
				
				// Find the number of errors and warnings
				$builderror = mysql_query("SELECT buildid FROM builderror WHERE buildid='$buildid' AND type='0'");
				$nerrors = mysql_num_rows($builderror);
				$totalerrors += $nerrors;
				$xml .= add_XML_value("error",$nerrors);
				$buildwarning = mysql_query("SELECT buildid FROM builderror WHERE buildid='$buildid' AND type='1'");
				$nwarnings = mysql_num_rows($buildwarning);
				$totalwarnings += $nwarnings;
				$xml .= add_XML_value("warning",$nwarnings);
				$diff = (strtotime($build_array["endtime"])-strtotime($build_array["starttime"]))/60;
    $xml .= "<time>".$diff."</time>";
    $xml .= "</build>";
				
				// Get the Configure options
				$configure = mysql_query("SELECT status FROM configure WHERE buildid='$buildid'");
				if(mysql_num_rows($configure)>0)
				  {
						$configure_array = mysql_fetch_array($configure);
						$xml .= add_XML_value("configure",$configure_array["status"]);
						$totalconfigure += $configure_array["status"];
				  }
		
				// Get the tests
				$test = mysql_query("SELECT * FROM test WHERE buildid='$buildid'");
				if(mysql_num_rows($test)>0)
				  {
				  $test_array = mysql_fetch_array($test);
						$xml .= "<test>";
						$nnotrun = mysql_num_rows(mysql_query("SELECT * FROM test WHERE buildid='$buildid' AND status='notrun'"));
						$nfail = mysql_num_rows(mysql_query("SELECT * FROM test WHERE buildid='$buildid' AND status='failed'"));
						$npass = mysql_num_rows(mysql_query("SELECT * FROM test WHERE buildid='$buildid' AND status='passed'"));
						$nna = mysql_num_rows(mysql_query("SELECT * FROM test WHERE buildid='$buildid' AND status='na'"));
						
						$totalnotrun += $nnotrun;
						$totalfail += $nfail;
						$totalpass += $npass;
						$totalna += $nna;
						
			   $xml .= add_XML_value("notrun",$nnotrun);
      $xml .= add_XML_value("fail",$nfail);
      $xml .= add_XML_value("pass",$npass);
						$xml .= add_XML_value("na",$nna);
						$xml .= add_XML_value("time","NA");
				  $xml .= "</test>";
					 }
				$xml .= add_XML_value("builddate",$build_array["starttime"]);
				$xml .= add_XML_value("submitdate",$build_array["submittime"]);
  $xml .= "</".strtolower($build_array["type"]).">";
  } // end looping through builds
 
	 $xml .= add_XML_value("totalConfigure",$totalconfigure);
  $xml .= add_XML_value("totalError",$totalerrors);
  $xml .= add_XML_value("totalWarning",$totalwarnings);
	
		$xml .= add_XML_value("totalNotRun",$totalnotrun);
		$xml .= add_XML_value("totalFail",$totalfail);
		$xml .= add_XML_value("totalPass",$totalpass);	
		$xml .= add_XML_value("totalNA",$totalna);
			
  $xml .= "</builds>";
		
		/*
<coverage>
    <site>gaia.kitware</site>
				<siteid>1</siteid>
    <buildname>Win32-vs8-Debug-Coverage</buildname>
				<percentage>57.62</percentage>
				<fail>2</fail>
    <pass>3</pass>
    <date>Oct 09 23:09 Eastern Daylight Time</date>
    <submitdate>Oct 09 23:09 Eastern Daylight Time</submitdate>
</coverage>
*/

  $xml .= "</cdash>";

	 return $xml;
} 


// Check if we can connect to the database
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
if(!$db)
  {
		// redirect to the install.php script
		echo "<script language=\"javascript\">window.location='install.php'</script>";
		return;
  }
if(mysql_select_db("$CDASH_DB_NAME",$db) === FALSE)
  {
		echo "<script language=\"javascript\">window.location='install.php'</script>";
		return;
  }
		
@$projectname = $_GET["project"];
if(!isset($projectname )) // if the project name is not set we grab the first project
  {
  $project = mysql_query("SELECT id FROM project ORDER BY id");
		if(mysql_num_rows($project)>0)
		  {
				$project_array = mysql_fetch_array($project);
				$projectid = $project_array["id"];
    }
  }
else
  {
		$projectid = get_project_id($projectname);
  }
		
@$date = $_GET["date"];

$xml = generate_main_dasboard_XML($projectid,$date);

// Now doing the xslt transition
generate_XSLT($xml,"index");
?>
