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
				$site_processor = $_POST["site_processor"];
				$site_nprocessors = $_POST["site_nprocessors"];
				$site_ip = $_POST["site_ip"];
				$site_longitude = $_POST["site_longitude"];			
				$site_latitude = $_POST["site_latitude"];										
	  	}
	
		if($updatesite)
		  {
			 update_site($claimsiteid,$site_name,$site_description,$site_processor,$site_nprocessors,$site_ip,$site_latitude,$site_longitude);
		  }
			
		// If we should retrieve the geolocation

		if($geolocation)
		  {
				$location = get_geolocation($site_ip);
			 update_site($claimsiteid,$site_name,$site_description,$site_processor,$site_nprocessors,$site_ip,$location['latitude'],$location['longitude']);
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
				$xml .= add_XML_value("id",$siteid);
				$xml .= add_XML_value("name",$site_array["name"]);
				$xml .= add_XML_value("description",$site_array["description"]);		
				$xml .= add_XML_value("processor",$site_array["processor"]);			
				$xml .= add_XML_value("nprocessors",$site_array["numprocessors"]);		
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
