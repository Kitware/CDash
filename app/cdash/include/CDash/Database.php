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

use Doctrine\DBAL\Driver\PDOConnection;
use PDO;
use PDOException;

/**
 * Class Database
 * This class is meant to serve as a minimal lazy database abstraction. The
 * file pdo.php will use this extensively.
 */
class Database extends Singleton
{
    const DB_CONNECTION_TYPE_SOCKET = 'unix_socket';
    const DB_CONNECTION_TYPE_HOST = 'host';

    const DB_TYPE_MYSQL = 'mysql';
    const DB_TYPE_PGSQL = 'pgsql';

    /** @var \PDO $pdo */
    private $pdo;

    /** @var string $dsn */
    private $dsn;

    /** @var string $database_name */
    private $database_name;

    /** @var string|null $username */
    private $username;

    /** @var string|null $password */
    private $password;
    private $retries;
    private $attributes;
    private $type;
    private $connection_type;
    private $host;
    private $port;
    private $error;

    /**
     * Private function for exponential back-off as detailed here:
     *   https://cloud.google.com/storage/docs/json_api/v1/how-tos/upload#exp-backoff
     * Pass in a function that takes no arguments to have it retried.
     * @param \Closure $closure a function that returns false when it fails
     * @return false on failure mixed otherwise
     */
    private function _exponential_backoff($closure)
    {
        // Random exponential back-off. See the following for more information:
        // https://cloud.google.com/storage/docs/json_api/v1/how-tos/upload#exp-backoff
        $ret = false;
        for ($retry_count = 0; $retry_count < $this->retries; ++$retry_count) {
            $ret = $closure();
            if ($ret === false) {
                // No need to sleep the last time through the loop.
                if ($retry_count < $this->retries - 1) {
                    $wait_time = (2 ^ $retry_count) * 1000 + rand(0, 1000);
                    usleep($wait_time * 1000);
                }
            } else {
                return $ret; // Success
            }
        }
        return $ret; // Failure after $this->retries attempts
    }

    /**
     * Create a Database object with the given attributes.
     */
    public function __construct()
    {
        $config = Config::getInstance();
        $this->port = $config->get('CDASH_DB_PORT');
        $this->type = $config->get('CDASH_DB_TYPE');
        $this->host = $config->get('CDASH_DB_HOST');
        $this->database_name = $config->get('CDASH_DB_NAME');
        $this->username = $config->get('CDASH_DB_LOGIN');
        $this->password = $config->get('CDASH_DB_PASS');
        $this->retries = $config->get('CDASH_MAX_QUERY_RETRIES');
        $this->connection_type = $config->get('CDASH_DB_CONNECTION_TYPE');

        if ($this->type === self::DB_TYPE_MYSQL) {
            $this->attributes = [
                PDO::ATTR_PERSISTENT => $config->get('CDASH_USE_PERSISTENT_MYSQL_CONNECTION')
            ];
        }

        $ssl_ca = $config->get('CDASH_SSL_CA');
        $ssl_key = $config->get('CDASH_SSL_KEY');
        $ssl_cert = $config->get('CDASH_SSL_CERT');

        if (!is_null($ssl_ca)) {
            $this->attributes[PDO::MYSQL_ATTR_SSL_CA] = $ssl_ca;
            if (!is_null($ssl_cert) && !is_null($ssl_key)) {
                $this->attributes[PDO::MYSQL_ATTR_SSL_KEY] = $ssl_key;
                $this->attributes[PDO::MYSQL_ATTR_SSL_CERT] = $ssl_cert;
            }
        }
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
            /** @var PDO $pdo */
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

    public function setPdo(PDOConnection $pdo)
    {
        $this->pdo = $pdo;
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

    public function insertByTrasaction(\PDOStatement $stmt, $input_parameters = null)
    {
        $pdo = $this->getPdo();
        $pdo->beginTransaction();
        $lastInsertId = $this->insert($stmt, $input_parameters);
        $pdo->commit();
        return $lastInsertId;
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
            $critical_pdo_errors = Config::getInstance()->get('CDASH_CRITICAL_PDO_ERRORS');
            $e = new \RuntimeException($error_info[2]);
            Log::getInstance()->error($e);
            if (in_array($error_info[1], $critical_pdo_errors)) {
                http_response_code(500);
                exit();
            }
        }
    }

    /**
     * @param string|null $database_name
     * @return string
     */
    public function buildDsn($database_name = null)
    {
        $dsn = "{$this->type}:{$this->connection_type}={$this->host}";
        if (!empty($this->port) && $this->connection_type !== self::DB_CONNECTION_TYPE_SOCKET) {
            $dsn .= ';port=' . strval($this->port);
        }

        if (!empty($database_name)) {
            $dsn .= ";dbname={$database_name}";
        }
        return $dsn;
    }

    /**
     * Return the name of the database.
     * @return string
     */
    public function getDatabaseName()
    {
        return $this->database_name;
    }

    /**
     * @return bool
     */
    public function hasError()
    {
        $this->error = $this->pdo->errorInfo();
        return (bool) $this->error[0] !== PDO::ERR_NONE;
    }

    /**
     * Returns the human readable error message
     * @return string|null
     */
    public function getError()
    {
        return isset($this->error[2]) ? $this->error[2] : null;
    }

    /**
     * Query the database (with exponential backoff)
     * @param string $sql
     * @return false|\PDOStatement
     */
    public function queryWithBackoff($sql)
    {
        $this->_exponential_backoff(function () use ($sql) {
            if ($this->getPdo() === false) {
                return false;
            }
            try {
                return $this->pdo->query($sql);
            } catch (PDOException $e) {
                add_log($e->getMessage() . PHP_EOL . $e->getTraceAsString(), 'query', LOG_ERR);
                return false;
            }
        });
    }
}
