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

use CDash\Database;

/**
 * pdo_single_row_query returns a single row. Useful for SELECT
 * queries that are expected to return 0 or 1 rows.
 *
 * @deprecated 04/01/2023
 */
function pdo_single_row_query($qry): array|null|false
{
    $result = pdo_query($qry);
    if (false === $result) {
        add_log('error: pdo_query failed: ' . pdo_error(),
            'pdo_single_row_query', LOG_ERR);
        return [];
    }

    $num_rows = pdo_num_rows($result);
    if (0 !== $num_rows && 1 !== $num_rows) {
        add_log('error: at most 1 row should be returned, not ' . $num_rows,
            'pdo_single_row_query', LOG_ERR);
        add_log('warning: returning the first row anyway even though result ' .
            'contains ' . $num_rows . ' rows',
            'pdo_single_row_query', LOG_WARNING);
    }

    $row = pdo_fetch_array($result);

    if ($result instanceof \PDOStatement && !$result->closeCursor()) {
        return false;
    }

    return $row;
}

/**
 * @deprecated 04/22/2023
 */
function get_link_identifier(): Database
{
    return Database::getInstance();
}

/**
 * Get the last pdo error or empty string in the case of no error.
 *
 * @deprecated 04/01/2023
 * @return string containing error message (or not in the case of production)
 */
function pdo_error(): string
{
    $error_info = get_link_identifier()->getPdo()->errorInfo();
    if (isset($error_info[2]) && $error_info[0] !== '00000') {
        if (config('app.env') === 'production') {
            return 'SQL error encountered, query hidden.';
        }
        return $error_info[2];
    } else {
        return ''; // no error;
    }
}

/**
 * A light wrapper around PDOStatement::fetch.
 *
 * @deprecated 04/01/2023
 */
function pdo_fetch_array(PDOStatement|false $result, int $result_type = PDO::FETCH_BOTH): array|null|false
{
    if ($result === false) {
        return false;
    } else {
        return $result->fetch($result_type);
    }
}

/**
 * Emulate mysql_insert_id using PDO
 *
 * @deprecated 04/01/2023
 */
function pdo_insert_id(string $table_name): string|false
{
    $seq = '';
    if (config('database.default') === 'pgsql') {
        $seq = $table_name . '_id_seq';
    }
    return get_link_identifier()->getPdo()->lastInsertId($seq);
}

/**
 * Emulate mysql_num_rows with PDO.
 * Do not use for select queries.
 *
 * @deprecated 04/01/2023
 * @param PDOStatement $result
 */
function pdo_num_rows($result): int|false
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
 * Emulate mysql_query with PDO.
 *
 * @deprecated 04/01/2023
 */
function pdo_query(string $query): PDOStatement|false
{
    $db = Database::getInstance();
    return $db->query($query);
}

/**
 * Emulate mysql_real_escape_string with PDO.
 *
 * @deprecated 04/01/2023
 */
function pdo_real_escape_string(mixed $unescaped_string): string
{
    $str = get_link_identifier()->getPdo()->quote($unescaped_string ?? '');
    return substr($str, 1, strlen($str) - 2); // remove enclosing quotes
}

/**
 * Case for numeric empty string in Postgres.
 *
 * @deprecated 04/01/2023
 */
function pdo_real_escape_numeric(mixed $unescaped_string): float|int|string
{
    if (config('database.default') === 'pgsql' && $unescaped_string == '') {
        // MySQL interprets an empty string as zero when assigned to a numeric field,
        // for PostgreSQL this must be done explicitly:
        $unescaped_string = '0';
    }

    // Return zero if we don't end up with a numeric value.
    $escaped_string = pdo_real_escape_string($unescaped_string);
    if (!is_numeric($escaped_string)) {
        return 0;
    }
    return $escaped_string;
}

/**
 * DEPRECATED - use Database::getInstance()->execute(...)
 *
 * Execute a prepared statement and log any errors that occur.
 *
 * @deprecated v2.5.0 01/22/2018
 */
function pdo_execute(PDOStatement $stmt, array|null $input_parameters=null): bool|null
{
    $db = Database::getInstance();
    return $db->execute($stmt, $input_parameters);
}

function pdo_get_vendor_version()
{
    $version = get_link_identifier()->getPdo()->query('SELECT version()')->fetchColumn();

    if (config('database.default') === 'pgsql') {
        // Postgress returns version string similar to:
        //   PostgreSQL 9.6.1 on x86_64-apple-darwin16.1.0, compiled by Apple LLVM version 8.0.0 (clang-800.0.42.1), 64-bit
        $build = explode(" ", $version);
        $version = $build[1];
    }

    return $version;
}
