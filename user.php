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

  $user = mysql_query("SELECT * FROM user WHERE id='$userid'");
		$user_array = mysql_fetch_array($user);
	 $xml .= add_XML_value("user_name",$user_array["firstname"]);
		
		$xml .= "</cdash>";
		
		// Now doing the xslt transition
  generate_XSLT($xml,"user");
  }

?>
