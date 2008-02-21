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
include('login.php');
include_once('common.php');

if ($session_OK) 
  {
  $userid = $_SESSION['cdash']['loginid'];
 
  @$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  mysql_select_db("$CDASH_DB_NAME",$db);
  
  $xml = "<cdash>";
  $xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
  $xml .= "<backurl>user.php</backurl>";
  $xml .= "<title>CDash - Edit Site</title>";
  $xml .= "<menutitle>CDash</menutitle>";
  $xml .= "<menusubtitle>Claim sites</menusubtitle>";

  // Post
  @$claimsites = $_POST["claimsites"];
  @$availablesites = $_POST["availablesites"];
  @$checkedsites = $_POST["checkedsites"];
  if($claimsites)
    {
    foreach($availablesites as $siteid)
      {
      if(@array_key_exists($siteid,$checkedsites))
        {
        add_site2user($siteid,$userid);
        }
      else
        {
        remove_site2user($siteid,$userid);
        }  
      }
    }
    
  @$claimsite = $_POST["claimsite"];
  @$claimsiteid = $_POST["claimsiteid"];
  if($claimsite)
    {
    add_site2user($claimsiteid,$userid);
    }
    
  @$updatesite = $_POST["updatesite"];
  @$geolocation = $_POST["geolocation"];
  
  if($updatesite || $geolocation)
    {
   $site_name = $_POST["site_name"];
   $site_description = $_POST["site_description"];
   $site_processoris64bits = $_POST["site_processoris64bits"];
   $site_processorvendor = $_POST["site_processorvendor"];
   $site_processorvendorid = $_POST["site_processorvendorid"];
   $site_processorfamilyid = $_POST["site_processorfamilyid"];
   $site_processormodelid = $_POST["site_processormodelid"];
   $site_processorcachesize = $_POST["site_processorcachesize"];
   $site_numberlogicalcpus = $_POST["site_numberlogicalcpus"];
   $site_numberphysicalcpus = $_POST["site_numberphysicalcpus"];
   $site_totalvirtualmemory = $_POST["site_totalvirtualmemory"];
    $site_totalphysicalmemory = $_POST["site_totalphysicalmemory"];  
    $site_logicalprocessorsperphysical = $_POST["site_logicalprocessorsperphysical"];  
    $site_processorclockfrequency = $_POST["site_processorclockfrequency"];  
    $site_ip = $_POST["site_ip"];
    $site_longitude = $_POST["site_longitude"];   
    $site_latitude = $_POST["site_latitude"];
   if(isset($_POST["newdescription_revision"]))
     {
     $newdescription_revision=1;
    }
    else
      {
    $newdescription_revision=0;
      }
    }
 
  if($updatesite)
    {
   update_site($claimsiteid,$site_name,
         $site_processoris64bits,
         $site_processorvendor,
         $site_processorvendorid,
         $site_processorfamilyid,
         $site_processormodelid,
         $site_processorcachesize,
         $site_numberlogicalcpus,
         $site_numberphysicalcpus,
         $site_totalvirtualmemory,
         $site_totalphysicalmemory,
         $site_logicalprocessorsperphysical,
         $site_processorclockfrequency,
               $site_description,$site_ip,$site_latitude,$site_longitude,!$newdescription_revision);
    }
   
  // If we should retrieve the geolocation
  if($geolocation)
    {
   $location = get_geolocation($site_ip);
   update_site($claimsiteid,$site_name,
         $site_processoris64bits,
         $site_processorvendor,
         $site_processorvendorid,
         $site_processorfamilyid,
         $site_processormodelid,
         $site_processorcachesize,
         $site_numberlogicalcpus,
         $site_numberphysicalcpus,
         $site_totalvirtualmemory,
         $site_totalphysicalmemory,
         $site_logicalprocessorsperphysical,
         $site_processorclockfrequency,
               $site_description,$site_ip,$location['latitude'],$location['longitude'],false);
    }
    
  // If we have a projectid that means we should list all the sites
  @$projectid = $_GET["projectid"];
  if(isset($projectid))
    {
    $project_array = mysql_fetch_array(mysql_query("SELECT name FROM project WHERE id='$projectid'"));
    $xml .= "<project>";
    $xml .= add_XML_value("id",$projectid);
    $xml .= add_XML_value("name",$project_array["name"]);
    $xml .= "</project>";
    
    // Select sites that belong to this project
    $site2project = mysql_query("SELECT siteid FROM build WHERE projectid='$projectid' GROUP BY siteid");
    while($site2project_array = mysql_fetch_array($site2project))
       {
       $siteid = $site2project_array["siteid"];
       $site_array = mysql_fetch_array(mysql_query("SELECT name FROM site WHERE id='$siteid'"));
       $xml .= "<site>";
       $xml .= add_XML_value("id",$siteid);
       $xml .= add_XML_value("name",$site_array["name"]);
       $user2site = mysql_query("SELECT * FROM site2user WHERE siteid='$siteid' and userid='$userid'");
       if(mysql_num_rows($user2site) == 0)
         {
         $xml .= add_XML_value("claimed","0");
         }
       else
         {
         $xml .= add_XML_value("claimed","1");
         } 
       $xml .= "</site>";
       }
    } // end isset(projectid)
  
  // If we have a siteid we look if the user has claimed the site or not
  @$siteid = $_GET["siteid"];
  if(isset($siteid))
    {
    $xml .= "<user>";
    $xml .= "<site>";
    $site_array = mysql_fetch_array(mysql_query("SELECT * FROM site WHERE id='$siteid'"));
    
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
    
    // Get the last information about the size
    $query = mysql_query("SELECT * FROM siteinformation WHERE siteid='$siteid' ORDER BY timestamp DESC LIMIT 1");
    if(mysql_num_rows($query) > 0)
     {
     $siteinformation_array = mysql_fetch_array($query);
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

    $xml .= add_XML_value("id",$siteid);
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
    
    $site2project = mysql_query("SELECT projectid FROM build WHERE siteid='$siteid' GROUP BY projectid");
    while($site2project_array = mysql_fetch_array($site2project))
       {
       $projectid = $site2project_array["projectid"];
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
     $xml .= "</user>";  
    } // end isset(siteid)
  
  
  $xml .= "</cdash>";
  
  // Now doing the xslt transition
  generate_XSLT($xml,"editSite");
  } // end session OK

?>
