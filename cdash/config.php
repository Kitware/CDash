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
// Hostname of the database server
$CDASH_DB_HOST = 'localhost';
// Login for database access
$CDASH_DB_LOGIN = 'root';
// Password for database access
$CDASH_DB_PASS = '';
// Name of the database
$CDASH_DB_NAME = 'cdash';
// Database type (empty means mysql)
$CDASH_DB_TYPE = 'mysql';
// Default from email
$CDASH_EMAILADMIN = 'admin@cdash.org';
$CDASH_EMAIL_FROM = 'admin@cdash.org';
$CDASH_EMAIL_REPLY = 'noreply@cdash.org';
// Duration of the cookie session (in seconds)
$CDASH_COOKIE_EXPIRATION_TIME='3600';
// Using HTTPS protocol to access CDash
$CDASH_USE_HTTPS ='0';
// Name of the server running CDash. 
// Leave empty to use current name.
$CDASH_SERVER_NAME = '';
// If the remote request should use localhost or the full name
// This variable should be set to 1 in most of the server configurations
$CDASH_CURL_REQUEST_LOCALHOST='1';
$CDASH_CURL_LOCALHOST_PREFIX='';
// Define the location of the local directory
$CDASH_USE_LOCAL_DIRECTORY = '0';
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
// Request full email address to add new users
// instead of displaying a list
$CDASH_FULL_EMAIL_WHEN_ADDING_USER = '0';
// Use getIPfromApache script to get IP addresses
// when using forwarding script
$CDASH_FORWARDING_IP='192.%'; // should be an SQL format 
$CDASH_DEFAULT_IP_LOCATIONS = array();
// Use LDAP
$CDASH_USE_LDAP='0';
$CDASH_LDAP_HOSTNAME='localhost';
$CDASH_LDAP_BASEDN='ou=people,dc=organization,dc=com';
$CDASH_LDAP_PROTOCOL_VERSION='3';

// Set to start the autoremoval on the first build of the day
$CDASH_AUTOREMOVE_BUILDS='0';

// Google Map API
$CDASH_GOOGLE_MAP_API_KEY = array();
$CDASH_GOOGLE_MAP_API_KEY['localhost'] = 'ABQIAAAAT7I3XxP5nXC2xZUbg5AhLhQlpUmSySBnNeRIYFXQdqJETZJpYBStoWsCJtLvtHDiIJzsxJ953H3rgg';

$CDASH_DEFAULT_GOOGLE_ANALYTICS='';

// How long since the last submission before considering a project
// non active
$CDASH_ACTIVE_PROJECT_DAYS = '7'; // a week

/** DO NOT EDIT AFTER THIS LINE */
$localConfig = dirname(__FILE__).'/config.local.php'; 
if ( file_exists($localConfig) )
  {
  include($localConfig);
  }
?>
