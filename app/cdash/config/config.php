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

/** WARNING: It's recommended to create a config.local.php file and leave
 * this file as is.
 * If creating the config.local.php from config.php make sure you DELETE
 * any text after the 'DO NOT EDIT AFTER THIS LINE' otherwise your
 * configuration file will be referencing each other. */

// This file is 'config.php', in the directory 'config', in the root.
// Therefore, the root of the CDash source tree on the web server is:

if (!isset($ONLY_LOAD_DEFAULTS)) {
    @include_once dirname(__DIR__) . '/vendor/autoload.php';
    include_once dirname(__FILE__) . '/../bootstrap/cdash_autoload.php';
}

$CDASH_ROOT_DIR = str_replace('\\', '/', dirname(dirname(__FILE__)));
set_include_path(get_include_path() . PATH_SEPARATOR . $CDASH_ROOT_DIR);

// host for Selenium testing
$CDASH_SELENIUM_HUB='localhost';

// If CDash should use the SendGrid API for email
$CDASH_USE_SENDGRID = false;
// API Key for SendGrid
$CDASH_SENDGRID_API_KEY = null;
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
// Upload directory (absolute or relative)
$CDASH_UPLOAD_DIRECTORY = $CDASH_ROOT_DIR . '/public/upload';
// Should normal user allowed to create projects
$CDASH_USER_CREATE_PROJECTS = false;
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
// Number of seconds to allow processing a single submission before resetting
$CDASH_SUBMISSION_PROCESSING_TIME_LIMIT = '450';
// Number of times to attempt processing a single submission before giving up
$CDASH_SUBMISSION_PROCESSING_MAX_ATTEMPTS = '5';

// Settings to enable external authentication using OAuth 2.0.
// Currently recognized providers are GitHub, GitLab, and Google.
// Example:
// $OAUTH2_PROVIDERS['GitHub'] = [
//    'clientId'          => {client-id},
//    'clientSecret'      => {client-secret},
//    'redirectUri'       => 'http://mydomain.com/CDash/auth/GitHub.php'
//];
// The GitLab provider takes an additional optional argument:
// the base URL for a self-hosted instance.
//    'domain'            => 'https://my.gitlab.example'
$OAUTH2_PROVIDERS = [];

// Whether or not to use Memcache for certain pages
$CDASH_MEMCACHE_ENABLED = false;

// Array of (server, port) to access Memcache on
$CDASH_MEMCACHE_SERVER = array('localhost', 11211);

// A prefix in the case of multiple applications using memcache
// Note: Memcache limits key size to 250 characters
$CDASH_MEMCACHE_PREFIX = 'cdash';

// Whether to use the AWS ElastiCache Auto Discovery feature
$CDASH_USE_ELASTICACHE_AUTO_DISCOVERY = false;

// Set to true if this copy of CDash is serving as a remote submission
// processor hosted somewhere other than the web server.
$CDASH_REMOTE_PROCESSOR = false;

// Location of the private key that allows this CDash installation to act
// as a GitHub App.
$CDASH_GITHUB_PRIVATE_KEY = dirname(__FILE__) . '/github.pem';

// Optional secret used to secure webhooks.
$CDASH_WEBHOOK_SECRET = null;

/* DO NOT EDIT AFTER THIS LINE */
if (!isset($ONLY_LOAD_DEFAULTS)) {
    $localConfig = dirname(__FILE__) . '/config.local.php';
    if ((strpos(__FILE__, 'config.local.php') === false) && file_exists($localConfig)) {
        include $localConfig;
    }
}
