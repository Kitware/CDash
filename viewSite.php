<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
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
require_once("pdo.php");
include('login.php');
include_once('common.php');
include("version.php");

@$siteid = $_GET["siteid"];
// Checks
if(!isset($siteid) || !is_numeric($siteid))
  {
  echo "Not a valid siteid!";
  return;
  }
  
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);
  
$site_array = pdo_fetch_array(pdo_query("SELECT * FROM site WHERE id='$siteid'"));  
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

$currenttimestamp = gmdate(FMT_DATETIME,$currenttime+3600*24); // Current timestamp is the beginning of the dashboard and we want the end

$query = pdo_query("SELECT * FROM siteinformation WHERE siteid='$siteid' AND timestamp<='$currenttimestamp' ORDER BY timestamp DESC LIMIT 1");
if(pdo_num_rows($query) > 0)
  {
 $siteinformation_array = pdo_fetch_array($query);
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
$xml .= "<version>".$CDASH_VERSION."</version>";

@$projectid = $_GET["project"];
if($projectid)
  {
  $project_array = pdo_fetch_array(pdo_query("SELECT name,nightlytime FROM project WHERE id='$projectid'"));  
  $xml .= "<backurl>index.php?project=".$project_array["name"];
  $xml .= "&#38;date=".get_dashboard_date_from_build_starttime(gmdate(FMT_DATETIME,$currenttime),$project_array["nightlytime"]);
  $xml .= "</backurl>";
  }
else  
  {
  $xml .= "<backurl>index.php</backurl>";
  }
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
$xml .= add_XML_value("totalvirtualmemory",getByteValueWithExtension($siteinformation_array["totalvirtualmemory"]*1024*1024)."B");
$xml .= add_XML_value("totalphysicalmemory",getByteValueWithExtension($siteinformation_array["totalphysicalmemory"]*1024*1024)."B");
$xml .= add_XML_value("logicalprocessorsperphysical",$siteinformation_array["logicalprocessorsperphysical"]);
$xml .= add_XML_value("processorclockfrequency",getByteValueWithExtension($siteinformation_array["processorclockfrequency"]*1024*1024)."Hz");
$xml .= add_XML_value("ip",$site_array["ip"]);
$xml .= add_XML_value("latitude",$site_array["latitude"]);
$xml .= add_XML_value("longitude",$site_array["longitude"]);
$xml .= "</site>";

// List the claimers of the site

$siteclaimer = pdo_query("SELECT firstname,lastname FROM ".qid("user").",site2user 
                          WHERE ".qid("user").".id=site2user.userid AND site2user.siteid='$siteid' ORDER BY firstname");
while($siteclaimer_array = pdo_fetch_array($siteclaimer))
   {
   $xml .= "<claimer>";
   $xml .= add_XML_value("firstname",$siteclaimer_array["firstname"]);
   $xml .= add_XML_value("lastname",$siteclaimer_array["lastname"]);
   $xml .= "</claimer>";
   }

// Select projects that belong to this site
$displayPage=0;
$projects = array();
$site2project = pdo_query("SELECT projectid,max(submittime) AS maxtime FROM build WHERE siteid='$siteid' AND projectid>0 GROUP BY projectid");

while($site2project_array = pdo_fetch_array($site2project))
   {
   $projectid = $site2project_array["projectid"];
   $project_array = pdo_fetch_array(pdo_query("SELECT name,public FROM project WHERE id=$projectid"));

   if(checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid,1))
     {
     $xml .= "<project>";
     $xml .= add_XML_value("id",$projectid);
     $xml .= add_XML_value("submittime",$site2project_array["maxtime"]);
     $xml .= add_XML_value("name",$project_array["name"]);
     $xml .= "</project>";
     $displayPage=1; // if we have at least a valid project we display the page
     $projects[] = $projectid;
     }
   }

// If the current site as only private projects we check that we have the right
// to view the page
if(!$displayPage)
  {
  echo "You cannot access this page";
  exit(0);
  }
  
  
// Compute the time for all the projects (faster than individually) average of the week
if($CDASH_DB_TYPE == "pgsql")
  {
  $timediff = "EXTRACT(EPOCH FROM (build.submittime - buildupdate.starttime))";
  $timestampadd = "NOW()-INTERVAL'167 hours'";
  }
else
  {
  $timediff = "TIME_TO_SEC(TIMEDIFF(build.submittime, buildupdate.starttime))";
  $timestampadd = "TIMESTAMPADD(".qiv("HOUR").", -167, NOW())";
  }

$testtime = pdo_query("SELECT projectid, build.name AS buildname, build.type AS buildtype, AVG(".$timediff.") AS elapsed
              FROM build, buildupdate
              WHERE
                build.submittime > ".$timestampadd."
                AND buildupdate.buildid = build.id
                AND build.siteid = '$siteid'    
                GROUP BY projectid,buildname,buildtype
                ORDER BY elapsed    
                ");          

$xml .= "<siteload>";
                
echo pdo_error();
$totalload = 0;
while($testtime_array = pdo_fetch_array($testtime))
  {
  $projectid = $testtime_array["projectid"];
  if(checkUserPolicy(@$_SESSION['cdash']['loginid'],$projectid,1))
     {
     $xml .= "<build>";
     $xml .= add_XML_value("name",$testtime_array["buildname"]);
     $xml .= add_XML_value("project",get_project_name($projectid));
     $xml .= add_XML_value("type",$testtime_array["buildtype"]);
     $xml .= add_XML_value("time",$testtime_array["elapsed"]);
     $totalload += $testtime_array["elapsed"];
     $xml .= "</build>"; 
     }
  }

// Compute the idle time
$idletime = 24*3600-$totalload;
$xml .= "<idle>".$idletime."</idle>";
$xml .= "</siteload>";

if(isset($_SESSION['cdash']))
   {
   $xml .= "<user>";
   $userid = $_SESSION['cdash']['loginid'];
   
   // Check if the current user as a role in this project
   foreach($projects as $projectid)
    {
    $user2project = pdo_query("SELECT role FROM user2project WHERE projectid='$projectid' and role>0");
    if(pdo_num_rows($user2project)>0)
      {
      $xml .= add_XML_value("sitemanager","1");
      
      $user2site = pdo_query("SELECT * FROM site2user WHERE siteid='$siteid' and userid='$userid'");
      if(pdo_num_rows($user2site) == 0)
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

   $user = pdo_query("SELECT admin FROM ".qid("user")." WHERE id='$userid'");
   $user_array = pdo_fetch_array($user);
   $xml .= add_XML_value("id",$userid);
   $xml .= add_XML_value("admin",$user_array["admin"]);
   $xml .= "</user>";
   }

$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"viewSite");
?>
