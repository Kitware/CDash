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
include_once("../common.php");

// Open the database connection
include("../config.php");
require_once("../pdo.php");
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);


echo '<?xml version="1.0" encoding="UTF-8"?>';
echo "<userid>";

@$author = $_GET['author'];
@$project = $_GET['project'];

if(!isset($_GET['author']) || !isset($_GET['project']))
  {
  echo "not found</userid>";
  return;
  }

if(strlen($_GET['author']) == 0 || strlen($_GET['project'])==0)
  {
  echo "not found</userid>";
  return;
  }
  
$projectid = get_project_id($project);

if(!is_numeric($projectid))
  {
  echo "not found</userid>";
  return;
  }
  
$author = pdo_real_escape_string($author);
// Check if the given user exists in the database
$userquery = pdo_query("SELECT id FROM ".qid("user").",user2project WHERE ".qid("user").".id=user2project.userid 
                        AND user2project.cvslogin='$author'
                        AND user2project.projectid='$projectid'");
                                          
if(pdo_num_rows($userquery)>0)
  { 
  $userarray = pdo_fetch_array($userquery);
  $userid = $userarray['id'];
  echo $userid."</userid>";
  return;
  }

echo "not found</userid>"; 
?>
