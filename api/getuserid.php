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

if(!isset($_GET['author']))
  {
  echo "error<no-author-param/></userid>";
  return;
  }

if(strlen($_GET['author']) == 0)
  {
  echo "error<empty-author-param/></userid>";
  return;
  }

$author = pdo_real_escape_string($_GET['author']);

// First, try the simplest query, where the author string is simply exactly
// equal to the user's email:
//
$userid = pdo_get_field_value("SELECT id FROM user WHERE email='$author'", 'id', '-1');
if ($userid !== '-1')
  {
  echo $userid."</userid>";
  return;
  }

// If no exact email match, fall back to the more complicated project-based
// repository credentials lookup:
//
if(!isset($_GET['project']))
  {
  echo "error<no-project-param/></userid>";
  return;
  }

if(strlen($_GET['project'])==0)
  {
  echo "error<empty-project-param/></userid>";
  return;
  }

$project = pdo_real_escape_string($_GET['project']);
$projectid = get_project_id($project);
if($projectid === -1)
  {
  echo "error<no-such-project/></userid>";
  return;
  }

$userquery = pdo_query("SELECT up.userid FROM user2project AS up,user2repository AS ur
                        WHERE ur.userid=up.userid
                          AND up.projectid='$projectid'
                          AND ur.credential='$author'
                          AND (ur.projectid='$projectid' OR ur.projectid=0)");

if(pdo_num_rows($userquery)>0)
  {
  $userarray = pdo_fetch_array($userquery);
  $userid = $userarray['userid'];
  echo $userid."</userid>";
  return;
  }

echo "not found<no-such-user/></userid>";
?>
