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

use Doctrine\DBAL\Driver\PDO\Connection;
use PDO;
use PDOException;

/**
 * Class Database
 * This class is meant to serve as a minimal lazy database abstraction. The
 * file pdo.php will use this extensively.
 */
class Database extends Singleton
{
    /** @var \PDO $pdo */
    private $pdo;

    public function __construct()
    {
    }

    /**
     * Get the underlying PDO object or false if it cannot be created.
     * @param bool $log_error
     * @return PDO
     */
    public function getPdo($log_error = true)
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
        } catch (PDOException $exception) {
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
        } catch (\PDOException $exception) {
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
}
