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

@set_time_limit(0);

// This is the installation script for CDash
if (class_exists('XsltProcessor') === false) {
    echo '<font color="#FF0000">Your PHP installation does not support XSL. Please install the XSL extension.</font>';
    return;
}

include dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';
require_once 'include/common.php';
require_once 'include/login_functions.php';
require_once 'include/version.php';

use CDash\Config;
use CDash\Database;
use CDash\Model\User;
$config = Config::getInstance();

if ($config->get('CDASH_PRODUCTION_MODE')) {
    echo 'CDash is in production mode. Install cannot be accessed. Change the variable in your config.php if you want to access the installation.';
    return;
}

$xml = begin_XML_for_XSLT();

if (function_exists('curl_init') === false) {
    $xml .= '<extcurl>0</extcurl>';
} else {
    $xml .= '<extcurl>1</extcurl>';
}

if (function_exists('json_encode') === false) {
    $xml .= '<extjson>0</extjson>';
} else {
    $xml .= '<extjson>1</extjson>';
}

if (function_exists('mb_detect_encoding') === false) {
    $xml .= '<extmbstring>0</extmbstring>';
} else {
    $xml .= '<extmbstring>1</extmbstring>';
}

if (class_exists('PDO') === false) {
    $xml .= '<extpdo>0</extpdo>';
} else {
    $xml .= '<extpdo>1</extpdo>';
}

if (!$config->get('CDASH_DB_TYPE')) {
    $db_type = 'mysql';
} else {
    $db_type = $config->get('CDASH_DB_TYPE');
}
$xml .= '<connectiondb_type>' . $db_type . '</connectiondb_type>';
$xml .= '<connectiondb_host>' . $config->get('CDASH_DB_HOST') . '</connectiondb_host>';
$xml .= '<connectiondb_login>' . $config->get('CDASH_DB_LOGIN') . '</connectiondb_login>';
$xml .= '<connectiondb_name>' . $config->get('CDASH_DB_NAME') . '</connectiondb_name>';

// Step 1: Check if we can connect to the database
$pdo = Database::getInstance()->getPdo();
if ($pdo) {
    $xml .= '<connectiondb>1</connectiondb>';
} else {
    $xml .= '<connectiondb>0</connectiondb>';
}

// check if the backup directory is writable
if (!is_writable($config->get('CDASH_BACKUP_DIRECTORY'))) {
    $xml .= '<backupwritable>0</backupwritable>';
} else {
    $xml .= '<backupwritable>1</backupwritable>';
}

// check if the log directory is writable
if ($config->get('CDASH_LOG_FILE') !== false && !is_writable($config->get('CDASH_LOG_DIRECTORY'))) {
    $xml .= '<logwritable>0</logwritable>';
} else {
    $xml .= '<logwritable>1</logwritable>';
}

// check if the upload directory is writable
if (!is_writable($config->get('CDASH_UPLOAD_DIRECTORY'))) {
    $xml .= '<uploadwritable>0</uploadwritable>';
} else {
    $xml .= '<uploadwritable>1</uploadwritable>';
}

// check if the rss directory is writable
if (!is_writable('rss')) {
    $xml .= '<rsswritable>0</rsswritable>';
} else {
    $xml .= '<rsswritable>1</rsswritable>';
}

// If the database already exists and we have all the tables
if (pdo_query('SELECT id FROM ' . qid('user') . ' LIMIT 1')) {
    $xml .= '<database>1</database>';
} else {
    $xml .= '<database>0</database>';
    $xml .= '<dashboard_timeframe>24</dashboard_timeframe>';

    // If we should create the tables
    @$Submit = $_POST['Submit'];
    if ($Submit) {
        $admin_email = $_POST['admin_email'];
        $admin_password = $_POST['admin_password'];

        $valid_email = true;

        if (strlen($admin_email) < 6 || strstr($admin_email, '@') === false) {
            $xml .= '<db_created>0</db_created>';
            $xml .= "<alert>* Administrator's email should be a valid email address</alert>";
            $valid_email = false;
        }
        $minimum_password_length = $config->get('CDASH_MINIMUM_PASSWORD_LENGTH');
        if ($valid_email && strlen($admin_password) < $minimum_password_length) {
            $xml .= '<db_created>0</db_created>';
            $xml .= "<alert>* Administrator's password must be at least $minimum_password_length characters</alert>";
            $valid_email = false;
        }
        if ($valid_email) {
            $complexity = getPasswordComplexity($admin_password);
            $minimum_complexity = $config->get('CDASH_MINIMUM_PASSWORD_COMPLEXITY');
            $complexity_count = $config->get('CDASH_PASSWORD_COMPLEXITY_COUNT');
            if ($complexity < $minimum_complexity) {
                $xml .= "<alert>* Administrator's password is not complex enough. ";
                if ($complexity_count > 1) {
                    $xml .= "It must contain at least $complexity_count characters from $minimum_complexity of the following types: uppercase, lowercase, numbers, and symbols.";
                } else {
                    $xml .= "It must contain at least $minimum_complexity of the following: uppercase, lowercase, numbers, and symbols.";
                }
                $xml .= '</alert>';
                $valid_email = false;
            }
        }

        if ($valid_email) {
            $db_created = true;
            // If this is MySQL we try to create the database
            if ($db_type == 'mysql') {
                $db_name = $config->get('CDASH_DB_NAME');
                if (!pdo_query("CREATE DATABASE IF NOT EXISTS `$db_name`")) {
                    $xml .= '<db_created>0</db_created>';
                    $xml .= '<alert>' . pdo_error() . '</alert>';
                    $db_created = false;
                }
            }

            /** process an SQL file */
            function _processSQLfile($filename)
            {
                $file_content = file($filename);
                $query = '';
                foreach ($file_content as $sql_line) {
                    $tsl = trim($sql_line);
                    if (($sql_line != '') && (substr($tsl, 0, 2) != '--') && (substr($tsl, 0, 1) != '#')) {
                        $query .= $sql_line;
                        if (preg_match("/;\s*$/", $sql_line)) {
                            // We need to remove only the last semicolon
                            $pos = strrpos($query, ';');
                            if ($pos !== false) {
                                $query = substr($query, 0, $pos) . substr($query, $pos + 1);
                            }

                            $result = pdo_query($query);
                            if (!$result) {
                                $xml = '<db_created>0</db_created>';
                                die(pdo_error());
                            }
                            $query = '';
                        }
                    }
                }
            }

            if ($db_created) {
                $sqlfile = $config->get('CDASH_ROOT_DIR') . "/sql/$db_type/cdash.sql";
                _processSQLfile($sqlfile);

                // If we have a local directory we process the sql in that directory
                if ($config->get('CDASH_USE_LOCAL_DIRECTORY')) {
                    $sqlfile = $config->get('CDASH_ROOT_DIR') . "/local/sql/$db_type/cdash.sql";
                    if (file_exists($sqlfile)) {
                        _processSQLfile($sqlfile);
                    }
                }

                // If we are with PostGreSQL we need to add some extra functions
                if ($db_type == 'pgsql') {
                    $sqlfile = $config->get('CDASH_ROOT_DIR') . '/sql/pgsql/cdash.ext.sql';

                    // Create the language. PgSQL has no way to know if the language already
                    // exists
                    @pdo_query('CREATE LANGUAGE plpgsql');

                    $file_content = file($sqlfile);
                    $query = '';
                    foreach ($file_content as $sql_line) {
                        $tsl = trim($sql_line);
                        if (($sql_line != '') && (substr($tsl, 0, 2) != '--')) {
                            $query .= $sql_line;
                            $possemicolon = strrpos($query, ';');
                            if ($possemicolon !== false && substr_count($query, '\'', 0, $possemicolon) % 2 == 0) {
                                // We need to remove only the last semicolon
                                $pos = strrpos($query, ';');
                                if ($pos !== false) {
                                    $query = substr($query, 0, $pos) . substr($query, $pos + 1);
                                }
                                $result = pdo_query($query);
                                if (!$result) {
                                    $xml .= '<db_created>0</db_created>';
                                    die(pdo_error());
                                }
                                $query = '';
                            }
                        }
                    }

                    // Check the version of PostgreSQL
                    $result_version = pdo_query('SELECT version()');
                    $version_array = pdo_fetch_array($result_version);
                    if (strpos(strtolower($version_array[0]), 'postgresql 9.') !== false) {
                        // For PgSQL 9.0 we need to set the bytea_output to 'escape' (it was changed to hexa)
                        @pdo_query('ALTER DATABASE ' . $config->get('CDASH_DB_NAME') . " SET bytea_output TO 'escape'");
                    }
                }

                $passwordHash = User::PasswordHash($admin_password);
                if ($passwordHash === false) {
                    $xml .= '<alert>Failed to hash password</alert>';
                } else {
                    $user = new User();
                    $user->Email = $admin_email;
                    $user->Password = $passwordHash;
                    $user->FirstName = 'administrator';
                    $user->Institution = 'Kitware Inc.';
                    $user->Admin = 1;
                    $user->Save();
                }
                $xml .= '<db_created>1</db_created>';

                // Set the database version
                setVersion();
            }
        }
    }
}

$xml .= '</cdash>';

// Now doing the xslt transition
generate_XSLT($xml, 'install');
