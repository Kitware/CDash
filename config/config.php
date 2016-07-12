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
require dirname(__DIR__) . '/vendor/autoload.php';
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

// Support for SSL database connections.
$CDASH_SSL_KEY = null;
$CDASH_SSL_CERT = null;
$CDASH_SSL_CA = null;

// Turn this variable ON when CDash has been installed
// Prevents from running the install.php again
$CDASH_PRODUCTION_MODE = false;
$CDASH_TESTING_MODE = false;
$CDASH_TESTING_RENAME_LOGS = false;

// Whether or not CDash submissions should trigger daily updates.
// Disable this if you want more fine grained control over when/how
// daily updates are triggered (e.g. cron).
$CDASH_DAILY_UPDATES = true;

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

// Should we use asynchronous submission
$CDASH_ASYNCHRONOUS_SUBMISSION = false;
// How long to keep finished async submissions in the DB.
// Set to 0 to delete them right away.
$CDASH_ASYNC_EXPIRATION_TIME = 691200; // 8 days.
// How many asynchronous workers to use.
// Only increase this above 1 for MySQL (not Postgres).
$CDASH_ASYNC_WORKERS = 1;

// Main title and subtitle for the index page
$CDASH_MAININDEX_TITLE = 'CDash';
$CDASH_MAININDEX_SUBTITLE = 'Projects';
// Default from email
$CDASH_EMAILADMIN = 'admin@cdash.org';
$CDASH_EMAIL_FROM = 'admin@cdash.org';
$CDASH_EMAIL_REPLY = 'noreply@cdash.org';
// Hostname of the SMTP server or null to use the PHP mail() function.
$CDASH_EMAIL_SMTP_HOST = null;
// Port for the SMTP server.
$CDASH_EMAIL_SMTP_PORT = 25;
// Either 'ssl' for SSL encryption, 'tls' for TLS encryption, or null for no
// encryption. For 'ssl' or 'tls', PHP must have the appropriate OpenSSL
// transport wrappers installed.
$CDASH_EMAIL_SMTP_ENCRYPTION = null;
// Login for the SMTP server or null for anonymous.
$CDASH_EMAIL_SMTP_LOGIN = null;
// Password for the SMTP server.
$CDASH_EMAIL_SMTP_PASS = null;
// Should CDash only register valid emails
$CDASH_REGISTRATION_EMAIL_VERIFY = true;
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
$CDASH_SERVER_PORT = '';
// If the remote request should use localhost or the full name
// This variable should be set to 1 in most of the server configurations
$CDASH_CURL_REQUEST_LOCALHOST = '1';
$CDASH_CURL_LOCALHOST_PREFIX = '';
$CDASH_BASE_URL = '';
// Define the location of the local directory
$CDASH_USE_LOCAL_DIRECTORY = '0';
// CSS file
$CDASH_CSS_FILE = 'css/cdash.css';
// Must be writable by the web server
$CDASH_DATA_ROOT_DIRECTORY = $CDASH_ROOT_DIR;
// Backup directory
$CDASH_BACKUP_DIRECTORY = $CDASH_DATA_ROOT_DIRECTORY . '/backup';
// Log directory
$CDASH_LOG_DIRECTORY = $CDASH_DATA_ROOT_DIRECTORY . '/log';
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
// Maximum size allocated for the logs
// CDash creates 10 files spanning the total size allocated
$CDASH_LOG_FILE_MAXSIZE_MB = 50;
// Log level
$CDASH_LOG_LEVEL = LOG_WARNING;
// Using external authentication
$CDASH_EXTERNAL_AUTH = '0';
// Backup timeframe.
// Set to '0' if you do not wish to backup parsed .xml files.
$CDASH_BACKUP_TIMEFRAME = '48'; // 48 hours
// Request full email address to add new users
// instead of displaying a list
$CDASH_FULL_EMAIL_WHEN_ADDING_USER = '0';
// Warn about unregistered committers: default to '1' to keep
// the behavior the same as previous versions. Set to '0' to
// avoid "is not registered (or has no email)" warning messages.
$CDASH_WARN_ABOUT_UNREGISTERED_COMMITTERS = '0';
// Use getIPfromApache script to get IP addresses
// when using forwarding script
$CDASH_FORWARDING_IP = '192.%'; // should be an SQL format
// Use hostip.info to geolocate IP addresses
$CDASH_GEOLOCATE_IP_ADDRESSES = true;
$CDASH_DEFAULT_IP_LOCATIONS = array();
// Use compression (default on)
$CDASH_USE_COMPRESSION = '1';
// Use LDAP
$CDASH_USE_LDAP = '0';
$CDASH_LDAP_HOSTNAME = 'localhost';
$CDASH_LDAP_BASEDN = 'ou=people,dc=organization,dc=com';
$CDASH_LDAP_PROTOCOL_VERSION = '3';
// Additional LDAP query filters to restrict authorized user list
// Example: To restrict users to a specific Active Directory group:
// '(memberOf=cn=superCoolRescrictedGroup,cn=Users,dc=example,dc=com)'
$CDASH_LDAP_FILTER = '';
// For authentication against Active Directory, set CDASH_LDAP_AUTHENTICATED to '1'
// CDASH_LDAP_OPT_REFERRALS to '0', and specify a bind DN and password
$CDASH_LDAP_OPT_REFERRALS = '1';
$CDASH_LDAP_AUTHENTICATED = '0';
$CDASH_LDAP_BIND_DN = 'cn=user,ou=people,dc=orgranization,dc=com';
$CDASH_LDAP_BIND_PASSWORD = 'password';
// Allow rememberme
$CDASH_ALLOW_LOGIN_COOKIE = '1';
// Set to start the autoremoval on the first build of the day
$CDASH_AUTOREMOVE_BUILDS = '0';
// Google Map API
$CDASH_GOOGLE_MAP_API_KEY = array();
$CDASH_GOOGLE_MAP_API_KEY['localhost'] = 'ABQIAAAAT7I3XxP5nXC2xZUbg5AhLhQlpUmSySBnNeRIYFXQdqJETZJpYBStoWsCJtLvtHDiIJzsxJ953H3rgg';
// Enable Google Analytics
$CDASH_DEFAULT_GOOGLE_ANALYTICS = '';
// How long since the last submission before considering a project
// non active
$CDASH_ACTIVE_PROJECT_DAYS = '7'; // a week
// Use CDash to manage build submissions
// This feature is currently experimental
$CDASH_MANAGE_CLIENTS = '0';
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
// Maximum per-project upload quota, in GB
$CDASH_MAX_UPLOAD_QUOTA = '10';
// Maximum size of large text fields, in php-strlen units, 0 for unlimited
$CDASH_LARGE_TEXT_LIMIT = '0';

// for Google oauth2 support
$GOOGLE_CLIENT_ID = '';
$GOOGLE_CLIENT_SECRET = '';

// Should we use CDash's feed feature?  Disabling this feature can improve
// submission processing time.
$CDASH_ENABLE_FEED = 1;

// Should we show the last submission for a project or subproject?
// Disabling this feature can improve rendering performance of index.php
// for projects with lots of subproject builds.
$CDASH_SHOW_LAST_SUBMISSION = 1;

// How many times to retry queries via random exponential back-off
$CDASH_MAX_QUERY_RETRIES = 1;

// Whether to use persistent mysql connections (mysql_connectp)
$CDASH_USE_PERSISTENT_MYSQL_CONNECTION = false;

// Whether to delete existing subprojects and/or dependencies that aren't
// mentioned by a newly uploaded Project.xml
$CDASH_DELETE_OLD_SUBPROJECTS = true;

// How long should passwords last for? (in days)
// Password rotation is disabled when this is set to 0.
$CDASH_PASSWORD_EXPIRATION = 0;

// Unique password count (new password cannot match last X)
// 0 means you can never reuse a password.
$CDASH_UNIQUE_PASSWORD_COUNT = 0;

// Lock user account after N failed login attempts.
// Account lockout functionality is disabled when this is set to 0.
// Note that account lockout functionality is only supported for authentication
// using CDash's database (not LDAP or Google account login).
$CDASH_LOCKOUT_ATTEMPTS = 0;

// How long to lock an account for? (in minutes)
$CDASH_LOCKOUT_LENGTH = 0;

// Whether or not to use Memcache for certain pages
$CDASH_MEMCACHE_ENABLED = false;

// Array of (server, port) to access Memcache on
$CDASH_MEMCACHE_SERVER = array('localhost', 11211);

// A prefix in the case of multiple applications using memcache
// Note: Memcache limits key size to 250 characters
$CDASH_MEMCACHE_PREFIX = 'cdash';

// Whether to use the AWS ElastiCache Auto Discovery feature
$CDASH_USE_ELASTICACHE_AUTO_DISCOVERY = false;

/* DO NOT EDIT AFTER THIS LINE */
$localConfig = dirname(__FILE__) . '/config.local.php';
if ((strpos(__FILE__, 'config.local.php') === false) && file_exists($localConfig)) {
    include $localConfig;
}
