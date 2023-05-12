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
namespace CDash;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PDO;
use PDOException;

/**
 * Class Database
 * This class is meant to serve as a minimal lazy database abstraction. The
 * file pdo.php will use this extensively.
 */
class Database extends Singleton
{
    /** @var PDO $pdo */
    private $pdo;

    /**
     * An array of the form [query_text: [query_values: query_result]]
     * @var array<string, array<string, mixed>>
     */
    private static array $queryCache = [];

    public function __construct()
    {
    }

    /**
     * Get the underlying PDO object or false if it cannot be created.
     * @return PDO
     *
     * @deprecated 04/22/2023  Use Laravel query builder or Eloquent instead
     */
    public function getPdo()
    {
        if (!$this->pdo) {
            $pdo = DB::connection()->getPdo();

            // The best of a number of bad  solutions. Essentially if a SQL statement
            // contains the same token more than once, e.g.:
            //   SELECT * FROM a WHERE b=:token OR c=:token
            // the $stmt->bindValue(':token', $token) does not take into account the
            // second token with the same name.
            // @see https://stackoverflow.com/a/35375592/1373710
            // TODO: Find out if this can be set at application bootstrap
            //       by extending the DatabaseServiceProvider class
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $this->pdo = $pdo;
        }

        return $this->pdo;
    }

    private function cacheQuery(string $query_string, array $query_params, mixed $query_result): void
    {
        $query_string = preg_replace('/\s+/', '', $query_string);
        self::$queryCache[$query_string][serialize($query_params)] = $query_result;
    }

    /**
     * Return the result associated with the specified query string and query params,
     * or null if the specified query does not exist in the cache.
     */
    private function getCachedQuery(string $query_string, ?array $query_params): mixed
    {
        $query_string = preg_replace('/\s+/', '', $query_string);
        if (array_key_exists($query_string, self::$queryCache)
            && array_key_exists(serialize($query_params), self::$queryCache[$query_string])
        ) {
            return self::$queryCache[$query_string][serialize($query_params)];
        }

        return null;
    }

    /**
     * @deprecated 04/22/2023  Use Laravel query builder or Eloquent instead
     */
    public function execute(\PDOStatement $stmt, $input_parameters = null)
    {
        try {
            $stmt->execute($input_parameters);
        } catch (PDOException) {
            $this->logPdoError($stmt->errorInfo());
            return false;
        }
        return true;
    }

    /**
     * @param \PDOStatement $stmt
     * @param null $input_parameters
     * @return string
     *
     * @deprecated 04/22/2023  Use Laravel query builder or Eloquent instead
     */
    public function insert(\PDOStatement $stmt, $input_parameters = null)
    {
        $this->execute($stmt, $input_parameters);
        $pdo = $this->getPdo();
        return $pdo->lastInsertId();
    }

    /**
     * @param $sql
     * @param array $options
     * @return bool|\PDOStatement
     *
     * @deprecated 04/22/2023  Use Laravel query builder or Eloquent instead
     */
    public function prepare($sql, array $options = [])
    {
        if (!$this->pdo) {
            $this->getPdo();
        }

        return $this->pdo->prepare($sql, $options);
    }

    /**
     * @param $sql
     * @return bool|\PDOStatement
     *
     * @deprecated 04/22/2023  Use Laravel query builder or Eloquent instead
     */
    public function query($sql)
    {
        $pdo = $this->getPdo();
        try {
            $stmt = $pdo->query($sql);
        } catch (\PDOException) {
            $this->logPdoError($pdo->errorInfo());
            $stmt = false;
        }
        return $stmt;
    }


    public function logPdoError($error_info)
    {
        if (isset($error_info[2]) && $error_info[0] !== '00000') {
            $e = new \RuntimeException($error_info[2]);
            Log::error($e);
        }
    }

    /**
     * Takes a SQL string and parameters, and returns the result of the query.
     * This function effectively combines prepare(), execute(), and fetch() in one function.
     * Returns an empty array if no rows were returned, and false on failure.
     *
     * @deprecated 04/22/2023  Use Laravel query builder or Eloquent instead
     */
    public function executePrepared(string $sql, ?array $params = null, bool $cache = false): array|false
    {
        if ($cache) {
            $cache_result = $this->getCachedQuery($sql, $params);
            if ($cache_result !== null) {
                return $cache_result;
            }
        }

        $stmt = $this->prepare($sql);
        $this->execute($stmt, $params);
        $result = $stmt->fetchAll();

        if ($cache) {
            $this->cacheQuery($sql, $params, $result);
        }

        return $result;
    }

    /**
     * Similar to executePrepared(), except that only one row is returned (if applicable).
     * Returns false on failure.
     *
     * @deprecated 04/22/2023  Use Laravel query builder or Eloquent instead
     */
    public function executePreparedSingleRow(string $sql, ?array $params = null, bool $cache = false): array|null|false
    {
        if ($cache) {
            $cache_result = $this->getCachedQuery($sql, $params);
            if ($cache_result !== null) {
                return $cache_result;
            }
        }

        $stmt = $this->prepare($sql);
        $this->execute($stmt, $params);

        $num_rows = pdo_num_rows($stmt);
        if ($num_rows === false) {
            $result = false;
        } elseif ($num_rows === 0) {
            $result = [];
        } else {
            $result = $stmt->fetch();
        }

        if ($cache) {
            $this->cacheQuery($sql, $params, $result);
        }

        return $result;
    }

    /**
     * Returns a SQL list filled with question marks to be used for a prepared statement.
     * Example: createPreparedArray(5) returns "(?,?,?,?,?)"
     */
    public function createPreparedArray(int $length): string
    {
        if ($length < 0) {
            throw new InvalidArgumentException('Invalid length specified');
        }

        return '(' . implode(', ', array_fill(0, $length, '?')) . ')';
    }
}
