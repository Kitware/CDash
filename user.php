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
  $xml = "<cdash>";
  $xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
  $db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  mysql_select_db("$CDASH_DB_NAME",$db);
  $xml .= add_XML_value("title","CDash - My Profile");

  $user = mysql_query("SELECT * FROM user WHERE id='$userid'");
  $user_array = mysql_fetch_array($user);
  $xml .= add_XML_value("user_name",$user_array["firstname"]);
  $xml .= add_XML_value("user_is_admin",$user_array["admin"]);
  
		// Go through the list of project the user is part of
  $project2user = mysql_query("SELECT projectid,role FROM user2project WHERE userid='$userid'");	
		while($project2user_array = mysql_fetch_array($project2user))
				{
				$projectid = $project2user_array["projectid"];
				$project_array = mysql_fetch_array(mysql_query("SELECT name FROM project WHERE id='$projectid'"));
				$xml .= "<project>";
				$xml .= add_XML_value("id",$projectid);
				$xml .= add_XML_value("role",$project2user_array["role"]); // 0 is normal user, 1 is maintainer, 2 is administrator, 3 is superadministrator
				$xml .= add_XML_value("name",$project_array["name"]);
				$xml .= "</project>";
				}
		
		// Go through the public projects
		$project = mysql_query("SELECT name,id FROM project WHERE id NOT IN (SELECT projectid as id FROM user2project WHERE userid='$userid' AND public='1')");
		while($project_array = mysql_fetch_array($project))
		  {
				$xml .= "<publicproject>";
				$xml .= add_XML_value("id",$project_array["id"]);
				$xml .= add_XML_value("name",$project_array["name"]);
				$xml .= "</publicproject>";
		  }
				
		if(@$_GET['note'] == "subscribedtoproject")
    {
    $xml .= "<message>You have subscribed to a project.</message>";
    }		
		else if(@$_GET['note'] == "subscribedtoproject")
    {
				$xml .= "<message>You have been unsubscribed from a project.</message>";
				}
				
  $xml .= "</cdash>";
  
  
  // Now doing the xslt transition
  generate_XSLT($xml,"user");
  }

?>
