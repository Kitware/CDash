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

@$currenttime = $_GET["currenttime"];
$siteinformation_array = array();
$siteinformation_array["description"] = "NA";
$siteinformation_array["processoris64bits"] = "NA";
$siteinformation_array["processorvendor"] = "NA";
$siteinformation_array["processorvendorid"] = "NA";
$siteinformation_array["processorfamilyid"] = "NA";
$siteinformation_array["processormodelid"] = "NA";
$siteinformation_array["processorcachesize"] = "NA";
$siteinformation_array["numberlogicalcpus"] = "NA";
$siteinformation_array["numberphysicalcpus"] = "NA";
$siteinformation_array["totalvirtualmemory"] = "NA";
$siteinformation_array["totalphysicalmemory"] = "NA";
$siteinformation_array["logicalprocessorsperphysical"] = "NA";
$siteinformation_array["processorclockfrequency"] = "NA";

$query = mysql_query("SELECT * FROM siteinformation WHERE siteid='$siteid' AND timestamp<='$currenttime' ORDER BY timestamp ASC LIMIT 1");
if(mysql_num_rows($query) > 0)
  {
	$siteinformation_array = mysql_fetch_array();
	if($siteinformation_array["processoris64bits"] == -1)
		{
		$siteinformation_array["processoris64bits"] = "NA";
		}
	if($siteinformation_array["processorfamilyid"] == -1)
	  {
	  $siteinformation_array["processorfamilyid"] = "NA";
		}
  if($siteinformation_array["processormodelid"] == -1)
	  {
	  $siteinformation_array["processormodelid"] = "NA";
		}
	if($siteinformation_array["processorcachesize"] == -1)
	  {
	  $siteinformation_array["processorcachesize"] = "NA";
		}
  if($siteinformation_array["numberlogicalcpus"] == -1)
	  {
	  $siteinformation_array["numberlogicalcpus"] = "NA";
		}
  if($siteinformation_array["numberphysicalcpus"] == -1)
	  {
	  $siteinformation_array["numberphysicalcpus"] = "NA";
   	}
  if($siteinformation_array["totalvirtualmemory"] == -1)
	  {
	  $siteinformation_array["totalvirtualmemory"] = "NA";
		}
	if($siteinformation_array["totalphysicalmemory"] == -1)
	  {
	  $siteinformation_array["totalphysicalmemory"] = "NA";
  	}
  if($siteinformation_array["logicalprocessorsperphysical"] == -1)
	  {
	  $siteinformation_array["logicalprocessorsperphysical"] = "NA";
		}
  if($siteinformation_array["processorclockfrequency"] == -1)
	  {
	  $siteinformation_array["processorclockfrequency"] = "NA";
		}
	}

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
$xml .= add_XML_value("description",$siteinformation_array["description"]);
$xml .= add_XML_value("processoris64bits",$siteinformation_array["processoris64bits"]);
$xml .= add_XML_value("processorvendor",$siteinformation_array["processorvendor"]);
$xml .= add_XML_value("processorvendorid",$siteinformation_array["processorvendorid"]);
$xml .= add_XML_value("processorfamilyid",$siteinformation_array["processorfamilyid"]);
$xml .= add_XML_value("processormodelid",$siteinformation_array["processormodelid"]);
$xml .= add_XML_value("processorcachesize",$siteinformation_array["processorcachesize"]);
$xml .= add_XML_value("numberlogicalcpus",$siteinformation_array["numberlogicalcpus"]);
$xml .= add_XML_value("numberphysicalcpus",$siteinformation_array["numberphysicalcpus"]);
$xml .= add_XML_value("totalvirtualmemory",$siteinformation_array["totalvirtualmemory"]);
$xml .= add_XML_value("totalphysicalmemory",$siteinformation_array["totalphysicalmemory"]);
$xml .= add_XML_value("logicalprocessorsperphysical",$siteinformation_array["logicalprocessorsperphysical"]);
$xml .= add_XML_value("processorclockfrequency",$siteinformation_array["processorclockfrequency"]);
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
