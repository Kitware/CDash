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

require dirname(__DIR__) . '/vendor/autoload.php';

require_once 'config/config.php';

/**
 * Connect to the database.
 * Using PDO, we cannot connect without a database name, so store the
 * information for later use.
 * @param null|string $server the server to connect to
 * @param null|string $username the database user
 * @param null|string $password the password to use
 * @param null|string $database the name of the database
 * @return CDash\Database
 */
function pdo_connect($server = null, $username = null, $password = null, $database = null)
{
    global $CDASH_DB_PORT, $CDASH_DB_TYPE, $CDASH_MAX_QUERY_RETRIES,
           $CDASH_USE_PERSISTENT_MYSQL_CONNECTION, $CDASH_DB_NAME,
           $CDASH_SSL_KEY, $CDASH_SSL_CERT, $CDASH_SSL_CA, $CDASH_DB_CONNECTION_TYPE;
    $db_name = is_null($database) ? $CDASH_DB_NAME : $database;
    return new CDash\Database(
        $CDASH_DB_TYPE, $server, $username, $password, $CDASH_DB_PORT,
        $db_name, $CDASH_USE_PERSISTENT_MYSQL_CONNECTION,
        $CDASH_MAX_QUERY_RETRIES, $CDASH_SSL_KEY, $CDASH_SSL_CERT,
        $CDASH_SSL_CA, $CDASH_DB_CONNECTION_TYPE
    );
}

function get_link_identifier($link_identifier = null)
{
    global $CDASH_DB_HOST,
           $CDASH_DB_LOGIN,
           $CDASH_DB_PASS,
           $CDASH_DB_NAME,
           $cdash_database_connection;
    if (!is_null($link_identifier) and $link_identifier instanceof CDash\Database) {
        $cdash_database_connection = $link_identifier;
    } elseif (isset($cdash_database_connection) and $cdash_database_connection instanceof CDash\Database) {
        // $cdash_database_connection is good to go
    } else {
        $cdash_database_connection = pdo_connect(
            $CDASH_DB_HOST, $CDASH_DB_LOGIN, $CDASH_DB_PASS, $CDASH_DB_NAME);
    }
    return $cdash_database_connection;
}

function pdo_select_db($database, $link_identifier = null)
{
    global $CDASH_DB_HOST,
           $CDASH_DB_LOGIN,
           $CDASH_DB_PASS,
           $cdash_database_connection;
    if (!is_null($link_identifier) and
        $link_identifier instanceof CDash\Database and
        $cdash_database_connection->getDatabaseName() === $database
    ) {
        $cdash_database_connection = $link_identifier;
    } else {
        $cdash_database_connection = pdo_connect($CDASH_DB_HOST, $CDASH_DB_LOGIN, $CDASH_DB_PASS, $database);
    }
    return true;
}

/**
 * Get the last pdo error or empty string in the case of no error.
 * @param CDash\Database|null $link_identifier
 * @return string containing error message (or not in the case of production)
 */
function pdo_error($link_identifier = null, $log_error = true)
{
    global $CDASH_PRODUCTION_MODE, $CDASH_CRITICAL_PDO_ERRORS;
    $error_info = get_link_identifier($link_identifier)->getPdo()->errorInfo();
    if (isset($error_info[2]) && $error_info[0] !== '00000') {
        if ($log_error) {
            add_log($error_info[2], 'pdo_error', LOG_ERR);
        }
        if (in_array($error_info[1], $CDASH_CRITICAL_PDO_ERRORS)) {
            http_response_code(500);
            exit();
        }
        if ($CDASH_PRODUCTION_MODE) {
            return 'SQL error encountered, query hidden.';
        }
        return $error_info[2];
    } else {
        return ''; // no error;
    }
}

/**
 * A light wrapper around PDOStatement::fetch.
 * @param PDOStatement $result the statementto fetch results from
 * @param int $result_type passes through to fetch
 * @return array|false
 */
function pdo_fetch_array($result, $result_type = PDO::FETCH_BOTH)
{
    if ($result === false) {
        return false;
    } else {
        return $result->fetch($result_type);
    }
}

/**
 * Emulate mysql_fetch_row with PDO.
 * @param PDOStatement $result
 * @return array|false
 */
function pdo_fetch_row($result)
{
    return pdo_fetch_array($result, PDO::FETCH_NUM);
}

/**
 * Emulate mysql_free_result with PDO.
 * @param PDOStatement $result
 * @return bool
 */
function pdo_free_result($result)
{
    if ($result instanceof \PDOStatement) {
        return $result->closeCursor();
    }
}

/**
 * Emulate mysql_insert_id using PDO
 * @param string $table_name
 * @return string
 */
function pdo_insert_id($table_name)
{
    global $CDASH_DB_TYPE;
    $seq = '';
    if ($CDASH_DB_TYPE === 'pgsql') {
        $seq = $table_name . '_id_seq';
    }
    return get_link_identifier(null)->getPdo()->lastInsertId($seq);
}

/**
 * Emulate mysql_num_rows with PDO.
 *   Do not use for select queries.
 *
 * @param PDOStatement $result
 * @return false|int
 */
function pdo_num_rows($result)
{
    if (!$result) {
        return false;
    } else {
        // The documentation here: http://us.php.net/manual/en/pdostatement.rowcount.php
        // suggests that rowCount may be inappropriate for using on SELECT
        // queries. It is the equivalent of the mysql_affected_rows function
        // not the mysql_num_rows function. This seems like it might be a bug
        // waiting to be reported. This can be fixed by running a COUNT(*) with
        // the same predicates as the SELECT. TODO.
        return $result->rowCount();
    }
}

/**
 * Emulate mysql_affected_rows with PDO.
 * @param PDOStatement $result
 * @return int
 */
function pdo_affected_rows($result)
{
    return pdo_num_rows($result);
}

/**
 * Emulate mysql_query with PDO.
 * @param string $query
 * @param PDO|null $link_identifier
 * @param bool $log_error
 * @return PDOStatement|false
 */
function pdo_query($query, $link_identifier = null, $log_error = true)
{
    $cur_pdo = get_link_identifier($link_identifier)->getPdo($log_error);
    if ($cur_pdo === false) {
        return false;
    } else {
        return $cur_pdo->query($query);
    }
}

/**
 * Lock a table. This is bad. Don't use this function.
 * @deprecated
 * @param array $tables an array of table names
 * @return bool
 */
function pdo_lock_tables($tables)
{
    global $CDASH_DB_TYPE;

    $table_str = implode(', ', $tables);

    if (isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE == 'pgsql') {
        // PgSql table locking syntax:
        // http://www.postgresql.org/docs/8.1/static/sql-lock.html
        pdo_query('BEGIN WORK');
        $locked = pdo_query('LOCK TABLE ' . $table_str);
        if (!$locked) {
            pdo_query('COMMIT WORK');
        }
        return $locked;
    } else {
        // MySQL table locking:
        // http://dev.mysql.com/doc/refman/5.0/en/lock-tables.html
        return pdo_query('LOCK TABLES ' . $table_str . ' WRITE');
    }
}

/**
 * Unlock tables. This is bad. Don't lock or unlock tables manually.
 * @deprecated
 * @return bool|false
 */
function pdo_unlock_tables()
{
    global $CDASH_DB_TYPE;

    if (isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE === 'pgsql') {
        // Unlock occurs automatically at transaction end for PgSql, according to:
        // http://www.postgresql.org/docs/8.1/static/sql-lock.html
        pdo_query('COMMIT WORK');
        return true;
    } else {
        return pdo_query('UNLOCK TABLES');
    }
}

/**
 * Emulate mysql_real_escape_string with PDO.
 * @param string $unescaped_string
 * @param PDO|null $link_identifier
 * @return string
 */
function pdo_real_escape_string($unescaped_string, $link_identifier = null)
{
    $str = get_link_identifier($link_identifier)->getPdo()->quote($unescaped_string);
    return substr($str, 1, strlen($str) - 2); // remove enclosing quotes
}

/**
 * Case for numeric empty string in Postgres.
 * @param string $unescaped_string
 * @param PDO|null $link_identifier
 * @return string
 */
function pdo_real_escape_numeric($unescaped_string, $link_identifier = null)
{
    global $CDASH_DB_TYPE;

    if (isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE == 'pgsql' && $unescaped_string == '') {
        // MySQL interprets an empty string as zero when assigned to a numeric field,
        // for PostgreSQL this must be done explicitly:
        $unescaped_string = '0';
    }

    // Return zero if we don't end up with a numeric value.
    $escaped_string =
        pdo_real_escape_string($unescaped_string, $link_identifier);
    if (!is_numeric($escaped_string)) {
        return 0;
    }
    return $escaped_string;
}

/**
 * Begin a transaction with PDO.
 * @param PDO|null $link_identifier
 */
function pdo_begin_transaction($link_identifier = null)
{
    get_link_identifier($link_identifier)->getPdo()->beginTransaction();
}

/**
 * Commit a transaction with PDO.
 * @param PDO|null $link_identifier
 */
function pdo_commit($link_identifier = null)
{
    get_link_identifier($link_identifier)->getPdo()->commit();
}

/**
 * Roll back a transaction with PDO.
 * @param PDO|null $link_identifier
 */
function pdo_rollback($link_identifier = null)
{
    get_link_identifier($link_identifier)->getPdo()->rollBack();
}

global $cdash_database_connection;
global $CDASH_DB_HOST;
global $CDASH_DB_LOGIN;
global $CDASH_DB_PASS;
global $CDASH_DB_NAME;
global $CDASH_DB_PORT;

if (!isset($cdash_database_connection)) {
    $cdash_database_connection = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS", $CDASH_DB_NAME);
}
