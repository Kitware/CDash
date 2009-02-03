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
include_once("../cdash/common.php");

// Open the database connection
include("../cdash/config.php");
require_once("../cdash/pdo.php");
$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);


@$project = $_GET['project'];
@$site = $_GET['site'];
@$siteid = $_GET['siteid'];
@$stamp = $_GET['stamp'];
@$name = $_GET['name'];

$project = pdo_real_escape_string($project);
$site = pdo_real_escape_string($site);
$stamp = pdo_real_escape_string($stamp);
$name = pdo_real_escape_string($name);

$projectid = get_project_id($project);

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo "<buildid>";

if(!is_numeric($projectid))
  {
  echo "not found</buildid>";
  return;
  }

if(!isset($siteid))
  {
  $sitequery = pdo_query("SELECT id FROM site WHERE name='$site'");
  if(pdo_num_rows($sitequery)>0)
    {
    $site_array = pdo_fetch_array($sitequery);
    $siteid = $site_array['id'];
    }
  }

if(!is_numeric($siteid))
  {
  echo "wrong site</buildid>";
  return;
  }  
                           
$buildquery = pdo_query("SELECT id FROM build WHERE siteid='$siteid' AND projectid='$projectid'
                         AND name='$name' AND stamp='$stamp'");
                                          
if(pdo_num_rows($buildquery)>0)
  {
  $buildarray = pdo_fetch_array($buildquery);
  $buildid = $buildarray['id'];
  echo $buildid."</buildid>";
  return;
  }

echo "not found</buildid>";
?>
