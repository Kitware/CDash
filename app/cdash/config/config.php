<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

/** WARNING: It's recommended to edit the existing .env file and leave
 * this file as is.*/

// This file is 'config.php', in the directory 'config', in the root.
// Therefore, the root of the CDash source tree on the web server is:

if (!isset($ONLY_LOAD_DEFAULTS)) {
    @include_once dirname(__DIR__) . '/vendor/autoload.php';
    include_once dirname(__FILE__) . '/../bootstrap/cdash_autoload.php';
}

$CDASH_ROOT_DIR = str_replace('\\', '/', dirname(dirname(__FILE__)));
set_include_path(get_include_path() . PATH_SEPARATOR . $CDASH_ROOT_DIR);

// Using HTTPS protocol to access CDash
$CDASH_USE_HTTPS = '0';
// Name of the server running CDash.
// Leave empty to use current name and default port.
$CDASH_SERVER_NAME = '';
// CSS file
$CDASH_CSS_FILE = 'css/cdash.css';
// Log directory
$CDASH_LOG_DIRECTORY = $CDASH_ROOT_DIR . '/log';
// Log file location. Set to false to log to the syslog.
$CDASH_LOG_FILE = $CDASH_LOG_DIRECTORY . '/cdash.log';
// Request full email address to add new users
// instead of displaying a list
$CDASH_FULL_EMAIL_WHEN_ADDING_USER = '0';
$CDASH_DEFAULT_IP_LOCATIONS = array();

// Define the git command
$CDASH_GIT_COMMAND = 'git';
// The default git directory where the bare repositories should be created
$CDASH_DEFAULT_GIT_DIRECTORY = 'git';
// Define the p4 command
$CDASH_P4_COMMAND = 'p4';

// Location of the private key that allows this CDash installation to act
// as a GitHub App.
$CDASH_GITHUB_PRIVATE_KEY = dirname(__FILE__) . '/github.pem';

// Optional secret used to secure webhooks.
$CDASH_WEBHOOK_SECRET = null;
