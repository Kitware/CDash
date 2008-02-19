<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: common.php,v $
  Language:  PHP
  Date:      $Date: 2007-10-16 11:23:29 -0400 (Tue, 16 Oct 2007) $
  Version:   $Revision: 12 $

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include("config.php");
include('login.php');

//if ($session_OK) 
  {
	include("config.php");
	include("common.php"); 
  
  $xml = '<?xml version="1.0"?><cdash>';
  $xml .= "<title>CDash - My Profile</title>";
  $xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";


	$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  mysql_select_db("$CDASH_DB_NAME",$db);

  $userid = $_SESSION['cdash']['loginid'];

  @$updateprofile = $_POST["updateprofile"];	
  if($updateprofile) 
   {
	 $institution = $_POST["institution"];	
	 $email = $_POST["email"];	
	 $lname = $_POST["lname"];	
	 $fname = $_POST["fname"];	 
		 
   if(mysql_query("UPDATE user SET email='$email',
	                                 institution='$institution',
																	 firstname='$fname',
																	 lastname='$lname' WHERE id='$userid'"))
	   {
		 $xml .= "<error>Your profile has been updated.</error>";
	   }
	 else
	   {
		 $xml .= "<error>Cannot update profile.</error>";
	   }
   
	 add_last_sql_error("editUser.php");
	 }
	 
	@$updatepassword = $_POST["updatepassword"];
	if($updatepassword) 
   	{
	  $passwd = $_POST["passwd"];	
	  $passwd2 = $_POST["passwd2"];	
		
		if(strlen($passwd)<5)
		  {
			$xml .= "<error>Password should be at least 5 characters.</error>";
		  }
	  else if($passwd != $passwd2)
		  {
			$xml .= "<error>Passwords don't match.</error>";
		  }
    else
		  {
			$md5pass = md5($passwd);
			if(mysql_query("UPDATE user SET password='$md5pass' WHERE id='$userid'"))
				{
				$xml .= "<error>Your password has been updated.</error>";
				}
			else
				{
				$xml .= "<error>Cannot update password.</error>";
				}
   
	 		add_last_sql_error("editUser.php");
		  }
   	}
  
	$xml .= "<user>";
  $user = mysql_query("SELECT * FROM user WHERE id='$userid'");
  $user_array = mysql_fetch_array($user);
  $xml .= add_XML_value("firstname",$user_array["firstname"]);
  $xml .= add_XML_value("lastname",$user_array["lastname"]);
  $xml .= add_XML_value("email",$user_array["email"]);
  $xml .= add_XML_value("institution",$user_array["institution"]);
			
  $xml .= "</user>";
  $xml .= "</cdash>";
  
  generate_XSLT($xml,"editUser");

} // end session OK
?>
