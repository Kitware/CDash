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

namespace {
    require_once dirname(dirname(__FILE__)) . '/log.php';
}

namespace CDash {

    /**
     * Class Database
     * This class is meant to serve as a minimal lazy database abstraction. The
     * file pdo.php will use this extensively.
     */
    class Database
    {
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
         * @param string $database_type
         * @param string $hostname
         * @param string $username
         * @param string $password
         * @param int|null $database_port
         * @param string|null $database_name
         * @param bool $use_persistent_connections
         * @param int $retries
         */
        public function __construct($database_type, $hostname, $username, $password,
                                    $database_port = null, $database_name = null,
                                    $use_persistent_connections = false, $retries = 1,
                                    $ssl_key = null, $ssl_cert = null, $ssl_ca = null, $connection_type = 'host')
        {
            $dsn = $database_type . ':' . $connection_type . '=' . $hostname;
            $this->attributes = array(\PDO::ATTR_PERSISTENT => $use_persistent_connections);

            // The unix_socket connection type can't be used with a port
            // http://php.net/manual/en/ref.pdo-mysql.connection.php
            if (!is_null($database_port) and $database_port !== '' and $connection_type != 'unix_socket') {
                $dsn = $dsn . ';port=' . strval($database_port);
            }
            if (!is_null($database_name) and $database_name != '') {
                $dsn = $dsn . ';dbname=' . $database_name;
            } else {
                $this->database_name = '';
            }
            $this->dsn = $dsn;
            $this->database_name = $database_name;
            $this->username = $username;
            $this->password = $password;
            $this->retries = $retries;
            if (!is_null($ssl_ca)) {
                $this->attributes[\PDO::MYSQL_ATTR_SSL_CA] = $ssl_ca;
                if (!is_null($ssl_cert) && !is_null($ssl_key)) {
                    $this->attributes[\PDO::MYSQL_ATTR_SSL_KEY] = $ssl_key;
                    $this->attributes[\PDO::MYSQL_ATTR_SSL_CERT] = $ssl_cert;
                }
            }
        }

        /**
         * Get the underlying PDO object or false if it cannot be created.
         * @param bool $log_error
         * @return bool|\PDO
         */
        public function getPdo($log_error = true)
        {
            if (is_null($this->pdo)) {
                try {
                    $this->pdo = new \PDO($this->dsn, $this->username, $this->password,
                        $this->attributes);
                } catch (\PDOException $e) {
                    if ($log_error) {
                        add_log($e->getMessage() . PHP_EOL . $e->getTraceAsString(), 'getPdo', LOG_ERR);
                    }
                    return false;
                }
            }
            return $this->pdo;
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
         * Query the database (with exponential backoff)
         * @param string $sql
         * @return false|\PDOStatement
         */
        public function query($sql)
        {
            $this->_exponential_backoff(function () use ($sql) {
                if ($this->getPdo() === false) {
                    return false;
                }
                try {
                    return $this->pdo->query($sql);
                } catch (\PDOException $e) {
                    add_log($e->getMessage() . PHP_EOL . $e->getTraceAsString(), 'query', LOG_ERR);
                    return false;
                }
            });
        }
    }
}
