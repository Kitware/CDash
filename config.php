<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: config.php,v $
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
// CSS file 
$CDASH_CSS_FILE = 'cdash.css';
// Backup directory
$CDASH_BACKUP_DIRECTORY = 'backup';
// LOG FILE
$CDASH_LOG_FILE = $CDASH_BACKUP_DIRECTORY."/cdash.log";
// Backup timeframe
$CDASH_BACKUP_TIMEFRAME = '48'; // 48 hours
// Use getIPfromApache script to get IP addresses
// when using forwarding script
$CDASH_USE_IP_FROM_ACCESS_LOG='0';
// Google Map API
$CDASH_GOOGLE_MAP_API_KEY = array();
$CDASH_GOOGLE_MAP_API_KEY['localhost'] = 'ABQIAAAAT7I3XxP5nXC2xZUbg5AhLhQlpUmSySBnNeRIYFXQdqJETZJpYBStoWsCJtLvtHDiIJzsxJ953H3rgg';
?>
