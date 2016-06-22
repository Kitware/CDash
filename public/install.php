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

if ($CDASH_PRODUCTION_MODE) {
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

if (!isset($CDASH_DB_TYPE)) {
    $db_type = 'mysql';
} else {
    $db_type = $CDASH_DB_TYPE;
}
$xml .= '<connectiondb_type>' . $db_type . '</connectiondb_type>';
$xml .= '<connectiondb_host>' . $CDASH_DB_HOST . '</connectiondb_host>';
$xml .= '<connectiondb_login>' . $CDASH_DB_LOGIN . '</connectiondb_login>';
$xml .= '<connectiondb_name>' . $CDASH_DB_NAME . '</connectiondb_name>';

// Step 1: Check if we can connect to the database
@$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
if (!$db) {
    $xml .= '<connectiondb>0</connectiondb>';
} else {
    // If we are installing a database other than mysql we need to
    // have the database already created
    if (!pdo_select_db($CDASH_DB_NAME, null)) {
        $xml .= '<connectiondb>0</connectiondb>';
    } else {
        $xml .= '<connectiondb>1</connectiondb>';
    }
}

// check if the backup directory is writable
if (!is_writable($CDASH_BACKUP_DIRECTORY)) {
    $xml .= '<backupwritable>0</backupwritable>';
} else {
    $xml .= '<backupwritable>1</backupwritable>';
}

// check if the log directory is writable
if ($CDASH_LOG_FILE !== false && !is_writable($CDASH_LOG_DIRECTORY)) {
    $xml .= '<logwritable>0</logwritable>';
} else {
    $xml .= '<logwritable>1</logwritable>';
}

// check if the upload directory is writable
if (!is_writable($CDASH_UPLOAD_DIRECTORY)) {
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
if (true === @pdo_select_db("$CDASH_DB_NAME", $db)
    && pdo_query('SELECT id FROM ' . qid('user') . ' LIMIT 1', $db, false)
) {
    $xml .= '<database>1</database>';
} else {
    $xml .= '<database>0</database>';
    $xml .= '<dashboard_timeframe>24</dashboard_timeframe>';

    // If we should create the tables
    @$Submit = $_POST['Submit'];
    if ($Submit) {
        if ($db_type == 'mysql') {
            pdo_select_db('');
        } else {
            pdo_select_db("$CDASH_DB_NAME");
        }
        $admin_email = htmlspecialchars(pdo_real_escape_string($_POST['admin_email']));
        $admin_password = htmlspecialchars(pdo_real_escape_string($_POST['admin_password']));

        $valid_email = true;

        if (strlen($admin_email) < 6 || strstr($admin_email, '@') === false) {
            $xml .= '<db_created>0</db_created>';
            $xml .= "<alert>* Administrator's email should be a valid email address</alert>";
            $valid_email = false;
        }

        global $CDASH_MINIMUM_PASSWORD_LENGTH,
               $CDASH_MINIMUM_PASSWORD_COMPLEXITY,
               $CDASH_PASSWORD_COMPLEXITY_COUNT;
        if ($valid_email && strlen($admin_password) < $CDASH_MINIMUM_PASSWORD_LENGTH) {
            $xml .= '<db_created>0</db_created>';
            $xml .= "<alert>* Administrator's password must be at least $CDASH_MINIMUM_PASSWORD_LENGTH characters</alert>";
            $valid_email = false;
        }
        if ($valid_email) {
            $complexity = getPasswordComplexity($admin_password);
            if ($complexity < $CDASH_MINIMUM_PASSWORD_COMPLEXITY) {
                $xml .= "<alert>* Administrator's password is not complex enough. ";
                if ($CDASH_PASSWORD_COMPLEXITY_COUNT > 1) {
                    $xml .= "It must contain at least $CDASH_PASSWORD_COMPLEXITY_COUNT characters from $CDASH_MINIMUM_PASSWORD_COMPLEXITY of the following types: uppercase, lowercase, numbers, and symbols.";
                } else {
                    $xml .= "It must contain at least $CDASH_MINIMUM_PASSWORD_COMPLEXITY of the following: uppercase, lowercase, numbers, and symbols.";
                }
                $xml .= '</alert>';
                $valid_email = false;
            }
        }

        if ($valid_email) {
            $db_created = true;
            // If this is MySQL we try to create the database
            if ($db_type == 'mysql') {
                pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
                if (!pdo_query("CREATE DATABASE IF NOT EXISTS `$CDASH_DB_NAME`")) {
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
                pdo_select_db("$CDASH_DB_NAME", $db);
                $sqlfile = "$CDASH_ROOT_DIR/sql/" . $db_type . '/cdash.sql';
                _processSQLfile($sqlfile);

                // If we have a local directory we process the sql in that directory
                if ($CDASH_USE_LOCAL_DIRECTORY) {
                    $sqlfile = "$CDASH_ROOT_DIR/local/sql/" . $db_type . '/cdash.sql';
                    if (file_exists($sqlfile)) {
                        _processSQLfile($sqlfile);
                    }
                }

                // If we are with PostGreSQL we need to add some extra functions
                if ($db_type == 'pgsql') {
                    $sqlfile = "$CDASH_ROOT_DIR/sql/pgsql/cdash.ext.sql";

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
                        @pdo_query('ALTER DATABASE ' . $CDASH_DB_NAME . " SET bytea_output TO 'escape'");
                    }
                }

                pdo_query('INSERT INTO ' . qid('user') . " (email,password,firstname,lastname,institution,admin) VALUES ('" . $admin_email . "', '" . md5($admin_password) . "', 'administrator', '','Kitware Inc.', 1)");
                echo pdo_error();

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
