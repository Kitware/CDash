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

    public function __construct()
    {
    }

    /**
     * Get the underlying PDO object or false if it cannot be created.
     * @return PDO
     */
    public function getPdo()
    {
        if (!$this->pdo) {
            $db = app()->make('db');
            $pdo = $db->getPdo();

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
     * @param \PDOStatement $stmt
     * @param null $input_parameters
     * @return bool
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
            Log::getInstance()->error($e);
        }
    }

    /**
     * Takes a SQL string and parameters, and returns the result of the query.
     * This function effectively combines prepare(), execute(), and fetch() in one function.
     * Returns an empty array if no rows were returned, and false on failure.
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
