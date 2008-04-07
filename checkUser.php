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
include_once("common.php");

// Open the database connection
include("config.php");
$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

@$cvsuser = $_GET['cvsuser'];
@$project = $_GET['project'];

$projectid = get_project_id($project);

if(!is_numeric($projectid))
  {
  echo "0";
  return;
  }
$cvsuser = mysql_real_escape_string($cvsuser);
// Check if the given user exists in the database
$qry = mysql_query("SELECT id FROM user,user2project WHERE user.id=user2project.userid 
                     AND user2project.cvslogin='$cvsuser'
                     AND user2project.projectid='$projectid'");
                                          
if(mysql_num_rows($qry)>0)
  {
  echo "1";
  return;
  }

echo "0";  
?>
