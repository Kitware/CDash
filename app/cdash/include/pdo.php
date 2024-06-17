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
 * DEPRECATED - use Database::getInstance()->execute(...)
 *
 * Execute a prepared statement and log any errors that occur.
 *
 * @deprecated v2.5.0 01/22/2018
 */
function pdo_execute(PDOStatement $stmt, array|null $input_parameters=null): bool
{
    $db = Database::getInstance();
    return $db->execute($stmt, $input_parameters);
}
