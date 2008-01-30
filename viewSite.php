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
$noforcelogin = 1;
include("config.php");
include('login.php');
include('common.php');

@$siteid = $_GET["siteid"];

include("config.php");
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);
  
$site_array = mysql_fetch_array(mysql_query("SELECT * FROM site WHERE id='$siteid'"));  
$sitename = $site_array["name"];

$xml = '<?xml version="1.0"?><cdash>';
$xml .= "<title>CDash : ".$sitename."</title>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<backurl>index.php</backurl>";
$xml .= "<title>CDash - $sitename</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>$sitename</menusubtitle>";


$xml .= "<dashboard>";
$xml .= "<title>CDash</title>";

// Find the correct google map key
foreach($CDASH_GOOGLE_MAP_API_KEY as $key=>$value)
  {
  if(strstr($_SERVER['HTTP_HOST'],$key) !== FALSE)
    {
    $apikey = $value;
    break;
    }
  } 
  
$xml .=  add_XML_value("googlemapkey",$apikey);
$xml .= "</dashboard>";
$xml .= "<site>";
$xml .= add_XML_value("id",$site_array["id"]);
$xml .= add_XML_value("name",$site_array["name"]);
$xml .= add_XML_value("description",$site_array["description"]);
$xml .= add_XML_value("osname",$site_array["osname"]);
$xml .= add_XML_value("osrelease",$site_array["osrelease"]);
$xml .= add_XML_value("osversion",$site_array["osversion"]);
$xml .= add_XML_value("osplatform",$site_array["osplatform"]);
$xml .= add_XML_value("processoris64bits",$site_array["processoris64bits"]);
$xml .= add_XML_value("processorvendor",$site_array["processorvendor"]);
$xml .= add_XML_value("processorvendorid",$site_array["processorvendorid"]);
$xml .= add_XML_value("processorfamilyid",$site_array["processorfamilyid"]);
$xml .= add_XML_value("processormodelid",$site_array["processormodelid"]);
$xml .= add_XML_value("processorcachesize",$site_array["processorcachesize"]);
$xml .= add_XML_value("numberlogicalcpus",$site_array["numberlogicalcpus"]);
$xml .= add_XML_value("numberphysicalcpus",$site_array["numberphysicalcpus"]);
$xml .= add_XML_value("totalvirtualmemory",$site_array["totalvirtualmemory"]);
$xml .= add_XML_value("totalphysicalmemory",$site_array["totalphysicalmemory"]);
$xml .= add_XML_value("logicalprocessorsperphysical",$site_array["logicalprocessorsperphysical"]);
$xml .= add_XML_value("processorclockfrequency",$site_array["processorclockfrequency"]);
$xml .= add_XML_value("ip",$site_array["ip"]);
$xml .= add_XML_value("latitude",$site_array["latitude"]);
$xml .= add_XML_value("longitude",$site_array["longitude"]);
$xml .= "</site>";

// Select projects that belong to this site
$projects = array();
$site2project = mysql_query("SELECT projectid,submittime FROM build WHERE siteid='$siteid' GROUP BY projectid");
while($site2project_array = mysql_fetch_array($site2project))
			{
			$projectid = $site2project_array["projectid"];
			$project_array = mysql_fetch_array(mysql_query("SELECT name FROM project WHERE id='$projectid'"));
			$xml .= "<project>";
			$xml .= add_XML_value("id",$projectid);
			$xml .= add_XML_value("submittime",$site2project_array["submittime"]);
			$xml .= add_XML_value("name",$project_array["name"]);
	 	$xml .= "</project>";
   $projects[] = $projectid;
		 }

 if(isset($_SESSION['cdash']))
   {
   $xml .= "<user>";
   $userid = $_SESSION['cdash']['loginid'];
			
			// Check if the current user as a role in this project
   foreach($projects as $projectid)
		  {
				$user2project = mysql_query("SELECT role FROM user2project WHERE projectid='$projectid' and role>0");
    if(mysql_num_rows($user2project)>0)
				  {
						$xml .= add_XML_value("sitemanager","1");
						
						$user2site = mysql_query("SELECT * FROM site2user WHERE siteid='$siteid' and userid='$userid'");
						if(mysql_num_rows($user2site) == 0)
						  {
								$xml .= add_XML_value("siteclaimed","0");
						  }
						else
						  {
								$xml .= add_XML_value("siteclaimed","1");
						  }	
						break;
				  }
			 }
  
			$user = mysql_query("SELECT admin FROM user WHERE id='$userid'");
   $user_array = mysql_fetch_array($user);
   $xml .= add_XML_value("id",$userid);
   $xml .= add_XML_value("admin",$user_array["admin"]);
   $xml .= "</user>";
   }

$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewSite");
?>
