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


// Hostname of the database server or name of unix socket
$CDASH_DB_HOST = 'localhost';
// Login for database access
$CDASH_DB_LOGIN = 'root';
// Port for the database (leave empty to use default)
$CDASH_DB_PORT = '';
// Password for database access
$CDASH_DB_PASS = '';
// Name of the database
$CDASH_DB_NAME = 'cdash';
// Database type
$CDASH_DB_TYPE = 'mysql';
// Must be one of host, unix_socket
$CDASH_DB_CONNECTION_TYPE = 'host';

// PDO error codes which should result in an internal server error
$CDASH_CRITICAL_PDO_ERRORS = array();

// host for Selenium testing
$CDASH_SELENIUM_HUB='localhost';

$CDASH_TESTING_MODE = false;
$CDASH_TESTING_RENAME_LOGS = false;

/**
  * If a Bernard Driver is available then CDASH_BERNARD_SUBMISSION can be enabled
  * to allow processing of submissions to take place in the background on other
  * machines.
  * Note: Enabling these require CDASH_SERVER_NAME be set properly for emails, since they may
  * be constructed on other machines (with a different SERVER_NAME).
  **/
$CDASH_BERNARD_SUBMISSION = false;
$CDASH_BERNARD_DRIVER = false;
$CDASH_BERNARD_CONSUMERS_WHITELIST = false;

// EXPERIMENTAL: Whether or not to use Bernard for submitting coverage jobs
$CDASH_BERNARD_COVERAGE_SUBMISSION = false;

// Should we use asynchronous submission
$CDASH_ASYNCHRONOUS_SUBMISSION = false;
// How long to keep finished async submissions in the DB.
// Set to 0 to delete them right away.
$CDASH_ASYNC_EXPIRATION_TIME = 691200; // 8 days.
// How many asynchronous workers to use.
// Only increase this above 1 for MySQL (not Postgres).
$CDASH_ASYNC_WORKERS = 1;

// Should CDash only register valid emails
$CDASH_REGISTRATION_EMAIL_VERIFY = true;
// If CDash should use the SendGrid API for email
$CDASH_USE_SENDGRID = false;
// API Key for SendGrid
$CDASH_SENDGRID_API_KEY = null;
// Duration of the cookie session (in seconds)
$CDASH_COOKIE_EXPIRATION_TIME = '3600';
// Minimum password length for CDash accounts.
$CDASH_MINIMUM_PASSWORD_LENGTH = 5;
// Of these four kinds of characters: (uppercase, lowercase, numbers, symbols)
// How many must be present in a password for it to be considered valid?
$CDASH_MINIMUM_PASSWORD_COMPLEXITY = 1;
// For a given character type (defined above), how many characters in the
// password must match this type for the password to get credit for it?
// For example, if you set this value to 2, then a password would need at least
// two numbers to get a +1 to its complexity score for containing numbers.
$CDASH_PASSWORD_COMPLEXITY_COUNT = 1;

// Using HTTPS protocol to access CDash
$CDASH_USE_HTTPS = '0';
// Name of the server running CDash.
// Leave empty to use current name and default port.
$CDASH_SERVER_NAME = '';
$CDASH_BASE_URL = '';
// CSS file
$CDASH_CSS_FILE = 'css/cdash.css';
// Backup directory
$CDASH_BACKUP_DIRECTORY = $CDASH_ROOT_DIR . '/backup';
// Log directory
$CDASH_LOG_DIRECTORY = $CDASH_ROOT_DIR . '/log';
// Log file location. Set to false to log to the syslog.
$CDASH_LOG_FILE = $CDASH_LOG_DIRECTORY . '/cdash.log';
// Upload directory (absolute or relative)
$CDASH_UPLOAD_DIRECTORY = $CDASH_ROOT_DIR . '/public/upload';
// The relative path from the CDash 'public' dir to the $CDASH_UPLOAD_DIRECTORY,
// used for downloading files.
// http://<CDASH_URL>/<CDASH_DIR>/$CDASH_DOWNLOAD_RELATIVE_URL/<SHA-1>/<FILENAME>
$CDASH_DOWNLOAD_RELATIVE_URL = 'upload';
// Should normal user allowed to create projects
$CDASH_USER_CREATE_PROJECTS = false;
// Backup timeframe.
// Set to '0' if you do not wish to backup parsed .xml files.
$CDASH_BACKUP_TIMEFRAME = '48'; // 48 hours
// Request full email address to add new users
// instead of displaying a list
$CDASH_FULL_EMAIL_WHEN_ADDING_USER = '0';
$CDASH_DEFAULT_IP_LOCATIONS = array();

// Google Map API
$CDASH_GOOGLE_MAP_API_KEY = array();
$CDASH_GOOGLE_MAP_API_KEY['localhost'] = 'ABQIAAAAT7I3XxP5nXC2xZUbg5AhLhQlpUmSySBnNeRIYFXQdqJETZJpYBStoWsCJtLvtHDiIJzsxJ953H3rgg';
// Enable Google Analytics
$CDASH_DEFAULT_GOOGLE_ANALYTICS = '';
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
// Maximum size of large text fields, in php-strlen units, 0 for unlimited
$CDASH_LARGE_TEXT_LIMIT = '0';

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

// Should we show the last submission for a project or subproject?
// Disabling this feature can improve rendering performance of index.php
// for projects with lots of subproject builds.
$CDASH_SHOW_LAST_SUBMISSION = 1;

// How long should passwords last for? (in days)
// Password rotation is disabled when this is set to 0.
$CDASH_PASSWORD_EXPIRATION = 0;

// Unique password count (new password cannot match last X)
// 0 means you can never reuse a password.
$CDASH_UNIQUE_PASSWORD_COUNT = 0;

// Whether or not to use Memcache for certain pages
$CDASH_MEMCACHE_ENABLED = false;

// Array of (server, port) to access Memcache on
$CDASH_MEMCACHE_SERVER = array('localhost', 11211);

// A prefix in the case of multiple applications using memcache
// Note: Memcache limits key size to 250 characters
$CDASH_MEMCACHE_PREFIX = 'cdash';

// Whether to use the AWS ElastiCache Auto Discovery feature
$CDASH_USE_ELASTICACHE_AUTO_DISCOVERY = false;

// Should CDash should post build/test results to a build's pull request?
// This is enabled by default but requires CTEST_CHANGE_ID to be set by the
// client.  Set this variable to FALSE to prevent CDash from commenting on
// pull requests.
$CDASH_NOTIFY_PULL_REQUEST = true;

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
