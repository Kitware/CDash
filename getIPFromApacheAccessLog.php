<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id: buildOverview.php 1161 2008-09-19 14:56:14Z jjomier $
  Language:  PHP
  Date:      $Date: 2008-02-04 14:19:42 -0500 (Mon, 04 Feb 2008) $
  Version:   $Revision: 430 $

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
// Open the database connection
include("cdash/config.php");
require_once("cdash/pdo.php");
include_once("cdash/common.php");

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$apacheaccesslog = "/var/log/apache/access.log";
$contents = file_get_contents($apacheaccesslog);

// Loop through the sites
// Select all the ips that have been forwarded
$site = pdo_query("SELECT name FROM site WHERE ip LIKE '$CDASH_FORWARDING_IP'");
while($site_array = pdo_fetch_array($site))
{
  $sitename = $site_array["name"];
  $pos = strpos($contents,$sitename."__");
  if($pos !== FALSE)
    {  
    // Find the IP in the log
    $beginip = strrpos(substr($contents,$pos-500,500),"\n")+$pos-500+1;
    $endip = strpos($contents," ",$beginip);
    $ip = substr($contents,$beginip,$endip-$beginip);
    echo $ip."\n"; 
    $location = get_geolocation($ip);
    $latitude = $location['latitude'];
    $longitude = $location['longitude'];
  
    $sql = "UPDATE site SET ip='$ip',latitude='$latitude',longitude='$longitude' WHERE name='$sitename'";
    pdo_query($sql);
    echo pdo_error();
    }
}

?>
