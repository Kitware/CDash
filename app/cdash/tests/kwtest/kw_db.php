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

require_once dirname(dirname(__FILE__)) . '/config.test.php';
require_once dirname(dirname(dirname(__FILE__))) . '/include/pdo.php';

/**
 *    db object to allow the user to interact with
 *    a database
 */
class database
{
    public $dbo = null;
    public $type = null;

    public function __construct($type)
    {
        switch ($type) {
            case 'mysql':
                $this->dbo = new dbo_mysql();
                $this->type = 'mysql';
                break;

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

    public function setHost($host)
    {
        $this->dbo->setHost($host);
    }

    public function setPort($port)
    {
        $this->dbo->setPort($port);
    }

    public function setDb($db)
    {
        $this->dbo->setDb($db);
    }

    public function setPassword($password)
    {
        $this->dbo->setPassword($password);
    }

    public function setUser($user)
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

    public function setConnection($connection)
    {
        $this->dbo->setConnection($connection);
    }
}

class dbo
{
    public $host = null;
    public $port = null;
    public $user = null;
    public $password = null;
    public $db = null;
    public $dbconnect = null;
    public $connection = 'host';

    public function getDbConnect()
    {
        return $this->dbconnect;
    }

    public function setHost($host)
    {
        $this->host = $host;
    }

    public function setPort($port)
    {
        $this->port = $port;
    }

    public function setDb($db)
    {
        $this->db = $db;
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

    public function setPassword($pasword)
    {
        $this->password = $pasword;
    }

    public function setConnection($connection)
    {
        $this->connection = $connection;
    }
}

class dbo_mysql extends dbo
{
    public function connect()
    {
    }

    public function getDSN()
    {
        return "mysql:{$this->connection}={$this->host}";
    }

    public function disconnect()
    {
        $this->dbconnect = null;
    }

    public function create($db)
    {
        $dbh = new PDO($this->getDSN(), $this->user, $this->password);
        if (!$dbh->query("CREATE DATABASE IF NOT EXISTS $db")) {
            $this->disconnect();
            return false;
        }
        $this->disconnect();
        return true;
    }

    public function drop($db)
    {
        $dbh = new PDO($this->getDSN(), $this->user, $this->password);
        if (!$dbh->query("DROP DATABASE IF EXISTS $db")) {
            $this->disconnect();
            return false;
        }
        $this->disconnect();
        return true;
    }

    public function connectToDb()
    {
        $db = \CDash\Database::getInstance();
        return ($db->getPdo() instanceof PDO);
    }

    public function query($query)
    {
        $this->connectToDb();
        $resource = pdo_query($query);
        var_dump($resource);
        if (!$resource || $resource === true) {
            return false;
        }
        $result = array();
        while ($row = pdo_fetch_array($resource, PDO::FETCH_ASSOC)) {
            $result[] = $row;
        }
        $this->disconnect();
        return $result;
    }
}

class dbo_pgsql extends dbo
{
    public function disconnect()
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
        $sql = "DROP DATABASE IF EXISTS $db";
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
        $result = array();
        while ($row = pdo_fetch_array($resource, PDO::FETCH_ASSOC)) {
            $result[] = $row;
        }
        $this->disconnect();
        return $result;
    }
}
