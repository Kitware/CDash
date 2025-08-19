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

/**
 *    db object to allow the user to interact with
 *    a database
 */
class database
{
    public $dbo;
    public $type;

    public function __construct($type)
    {
        switch ($type) {
            case 'pgsql':
                $this->dbo = new dbo_pgsql();
                $this->type = 'pgsql';
                break;

            default:
                $status = "database type unsupported.\n";

                trigger_error(
                    __FILE__ . ": $status",
                    E_USER_ERROR);
        }
    }

    public function getType()
    {
        return $this->type;
    }

    public function connect()
    {
        $this->dbo->connect();
        return $this->dbo->getDbConnect();
    }

    public function disconnect()
    {
        $this->dbo->disconnect();
        return $this->dbo->getDbConnect();
    }

    public function getInstance()
    {
        return $this->dbo;
    }

    public function setHost($host): void
    {
        $this->dbo->setHost($host);
    }

    public function setPort($port): void
    {
        $this->dbo->setPort($port);
    }

    public function setDb($db): void
    {
        $this->dbo->setDb($db);
    }

    public function setPassword($password): void
    {
        $this->dbo->setPassword($password);
    }

    public function setUser($user): void
    {
        $this->dbo->setUser($user);
    }

    public function create($db)
    {
        return $this->dbo->create($db);
    }

    public function drop($db)
    {
        return $this->dbo->drop($db);
    }

    public function query($query)
    {
        return $this->dbo->query($query);
    }

    public function setConnection($connection): void
    {
        $this->dbo->setConnection($connection);
    }
}

class dbo
{
    public $host;
    public $port;
    public $user;
    public $password;
    public $db;
    public $dbconnect;
    public $connection = 'host';

    public function getDbConnect()
    {
        return $this->dbconnect;
    }

    public function setHost($host): void
    {
        $this->host = $host;
    }

    public function setPort($port): void
    {
        $this->port = $port;
    }

    public function setDb($db): void
    {
        $this->db = $db;
    }

    public function setUser($user): void
    {
        $this->user = $user;
    }

    public function setPassword($pasword): void
    {
        $this->password = $pasword;
    }

    public function setConnection($connection): void
    {
        $this->connection = $connection;
    }
}

class dbo_pgsql extends dbo
{
    public function disconnect(): void
    {
        $this->dbconnect = null;
    }

    public function create($db)
    {
        $sql = "CREATE DATABASE $db";
        $dsn = "pgsql:{$this->connection}={$this->host}";
        $pdo = new PDO($dsn, $this->user, $this->password);
        $pdo->exec($sql);

        return $pdo->errorCode() === PDO::ERR_NONE;
    }

    public function drop($db)
    {
        $sql = "DROP DATABASE IF EXISTS $db (FORCE)";
        $dsn = "pgsql:{$this->connection}={$this->host}";
        $pdo = new PDO($dsn, $this->user, $this->password);
        $pdo->exec($sql);

        return $pdo->errorCode() === PDO::ERR_NONE;
    }

    public function query($query)
    {
        $resource = pdo_query($query);
        if (!$resource) {
            return false;
        }
        $result = [];
        while ($row = pdo_fetch_array($resource, PDO::FETCH_ASSOC)) {
            $result[] = $row;
        }
        $this->disconnect();
        return $result;
    }
}
