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

// To be able to access files in this CDash installation regardless
// of getcwd() value:
//
$cdashpath = str_replace('\\', '/', dirname(dirname(__FILE__)));
set_include_path($cdashpath . PATH_SEPARATOR . get_include_path());

require_once("cdash/common.php");
require_once("cdash/pdo.php");

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
$userquery = pdo_query("SELECT up.userid FROM user2project AS up,user2repository AS ur
                        WHERE ur.userid=up.userid AND up.projectid='$projectid' 
                        AND ur.credential='$author'
                        AND (ur.projectid='$projectid' OR ur.projectid=0)");
                                          
if(pdo_num_rows($userquery)>0)
  { 
  $userarray = pdo_fetch_array($userquery);
  $userid = $userarray['userid'];
  echo $userid."</userid>";
  return;
  }

echo "not found</userid>"; 
?>
