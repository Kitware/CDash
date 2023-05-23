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
use PDOStatement;

/**
 * Class Database
 * This class is meant to serve as a minimal lazy database abstraction. The
 * file pdo.php will use this extensively.
 */
class Database extends Singleton
{
    private ?PDO $pdo = null;

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
        if ($this->pdo === null) {
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

    /**
     * @deprecated 04/22/2023  Use Laravel query builder or Eloquent instead
     */
    public function execute(PDOStatement $stmt, ?array $input_parameters = null): bool
    {
        try {
            $stmt->execute($input_parameters);
        } catch (PDOException) {
            Log::error($stmt->errorInfo()[2]);
            return false;
        }
        return true;
    }

    /**
     * @deprecated 04/22/2023  Use Laravel query builder or Eloquent instead
     */
    public function prepare(string $sql, array $options = []): PDOStatement|false
    {
        if ($this->pdo === null) {
            $this->getPdo();
        }

        return $this->pdo->prepare($sql, $options);
    }

    /**
     * @deprecated 04/22/2023  Use Laravel query builder or Eloquent instead
     */
    public function query(string $sql): PDOStatement|false
    {
        $pdo = $this->getPdo();
        try {
            $stmt = $pdo->query($sql);
        } catch (PDOException) {
            Log::error($pdo->errorInfo()[2]);
            $stmt = false;
        }
        return $stmt;
    }

    /**
     * Takes a SQL string and parameters, and returns the result of the query.
     * This function effectively combines prepare(), execute(), and fetch() in one function.
     * Returns an empty array if no rows were returned, and false on failure.
     *
     * @deprecated 04/22/2023  Use Laravel query builder or Eloquent instead
     */
    public function executePrepared(string $sql, ?array $params = null): array|false
    {
        $stmt = $this->prepare($sql);
        $this->execute($stmt, $params);
        return $stmt->fetchAll();
    }

    /**
     * Similar to executePrepared(), except that only one row is returned (if applicable).
     * Returns false on failure.
     *
     * @deprecated 04/22/2023  Use Laravel query builder or Eloquent instead
     */
    public function executePreparedSingleRow(string $sql, ?array $params = null): array|null|false
    {
        $stmt = $this->prepare($sql);
        $this->execute($stmt, $params);

        $num_rows = pdo_num_rows($stmt);
        if ($num_rows === false) {
            return false;
        }
        if ($num_rows === 0) {
            return [];
        }
        return $stmt->fetch();
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
