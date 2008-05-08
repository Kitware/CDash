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
// Hostname of the MySQL database 
$CDASH_DB_HOST = 'localhost';
// Login for MySQL database access
$CDASH_DB_LOGIN = 'root';
// Password for MySQL database access
$CDASH_DB_PASS = '';
// Name of the MySQL database
$CDASH_DB_NAME = 'cdash';
// Default from email
$CDASH_EMAILADMIN = 'admin@cdash.org';
$CDASH_EMAIL_FROM = 'admin@cdash.org';
$CDASH_EMAIL_REPLY = 'noreply@cdash.org';
// Name of the server running CDash. 
// Leave empty to use current name.
$CDASH_SERVER_NAME = '';
// CSS file 
$CDASH_CSS_FILE = 'cdash.css';
// Backup directory
$CDASH_BACKUP_DIRECTORY = 'backup';
// Log file location
$CDASH_LOG_FILE = $CDASH_BACKUP_DIRECTORY."/cdash.log";
// Using external authentication
$CDASH_EXTERNAL_AUTH = '0';
// Backup timeframe
$CDASH_BACKUP_TIMEFRAME = '48'; // 48 hours
// Use getIPfromApache script to get IP addresses
// when using forwarding script
$CDASH_FORWARDING_IP='192.%'; // should be an SQL format 
$CDASH_DEFAULT_IP_LOCATIONS = array();
$CDASH_DEFAULT_IP_LOCATIONS[] = array("IP" => "192.*","latitude" => "42.6612","longitude" => "-73.7689");
$CDASH_DEFAULT_IP_LOCATIONS[] = array("IP" => "76.65.240.*","latitude" => "43.667","longitude" => "-79.417");
$CDASH_DEFAULT_IP_LOCATIONS[] = array("IP" => "69.135.193.169","latitude" => "39.6304","longitude" => "-84.2698");
$CDASH_DEFAULT_IP_LOCATIONS[] = array("IP" => "82.16.66.10","latitude" => "52.809","longitude" => "-2.111");
$CDASH_DEFAULT_IP_LOCATIONS[] = array("IP" => "213.47.10.40","latitude" => "48.2","longitude" => "16.367");
$CDASH_DEFAULT_IP_LOCATIONS[] = array("IP" => "129.255.93.*","latitude" => "41.6581","longitude" => "-91.5379");
$CDASH_DEFAULT_IP_LOCATIONS[] = array("IP" => "216.83.140.10","latitude" => "40.2971","longitude" => "-111.699");
$CDASH_DEFAULT_IP_LOCATIONS[] = array("IP" => "72.43.11.34","latitude" => "43.3117","longitude" => "-73.645");
$CDASH_DEFAULT_IP_LOCATIONS[] = array("IP" => "147.250.1.2","latitude" => "48.8542","longitude" => "2.34486");
$CDASH_DEFAULT_IP_LOCATIONS[] = array("IP" => "32.97.185.24","latitude" => "40.8615","longitude" => "-74.073");
$CDASH_DEFAULT_IP_LOCATIONS[] = array("IP" => "72.224.76.159","latitude" => "42.8404","longitude" => "-73.8521");
$CDASH_DEFAULT_IP_LOCATIONS[] = array("IP" => "143.121.62.63","latitude" => "52.1","longitude" => "5.233");
$CDASH_DEFAULT_IP_LOCATIONS[] = array("IP" => "132.235.227.198","latitude" => "39.3095","longitude" => "-82.081");
$CDASH_DEFAULT_IP_LOCATIONS[] = array("IP" => "66.177.9.95","latitude" => "30.1969","longitude" => "-81.6279");

// Google Map API
$CDASH_GOOGLE_MAP_API_KEY = array();
$CDASH_GOOGLE_MAP_API_KEY['localhost'] = 'ABQIAAAAT7I3XxP5nXC2xZUbg5AhLhQlpUmSySBnNeRIYFXQdqJETZJpYBStoWsCJtLvtHDiIJzsxJ953H3rgg';

$CDASH_DEFAULT_GOOGLE_ANALYTICS='UA-701656-6';
?>
