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

                exit($status);
                break;
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

    public function connectedToDb()
    {
        return $this->dbo->connectToDb();
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

    public function fillDb($sqlfile)
    {
        return $this->dbo->fillDb($sqlfile);
    }

    public function query($query)
    {
        return $this->dbo->query($query);
    }

    public function setConnection($connection)
    {
        return $this->dbo->setConnection($connection);
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
        $host = $this->host;
        if (!empty($this->port)) {
            $host .= ':' . $this->port;
        }
        pdo_connect($host, $this->user, $this->password);
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
        return pdo_select_db($this->db);
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
        while ($row = pdo_fetch_array($resource, \PDO::FETCH_ASSOC)) {
            $result[] = $row;
        }
        $this->disconnect();
        return $result;
    }

    public function fillDb($sqlfile)
    {
        if (!$this->dbconnect) {
            $this->connect();
        }
        $this->connectToDb();
        $file_content = file($sqlfile);
        $query = '';
        foreach ($file_content as $sql_line) {
            $tsl = trim($sql_line);
            if (($sql_line != '') && (substr($tsl, 0, 2) != '--') && (substr($tsl, 0, 1) != '#')) {
                $query .= $sql_line;
                if (preg_match("/;\s*$/", $sql_line)) {
                    $query = str_replace(';', '', "$query");
                    $result = pdo_query($query);
                    if (!$result) {
                        die(pdo_error());
                    }
                    $query = '';
                }
            }
        }
        pdo_query("INSERT INTO user VALUES (1, 'simpletest@localhost', '" . password_hash('simpletest', PASSWORD_DEFAULT) . "', 'administrator', '','Kitware Inc.', 1, '')");
        echo pdo_error();
        $this->disconnect();
        return true;
    }
}

class dbo_pgsql extends dbo
{
    public function connect($dbname = null)
    {
        $dbname = $this->db;
        $host = $this->host;
        $user = $this->user;
        $password = $this->password;
        $conn = "host='$host' dbname='$dbname' user='$user' password='$password'";
        if (!empty($this->port)) {
            $conn .= " port='$this->port'";
        }
        pdo_connect($host, $user, $password);
        return pdo_select_db($dbname, $this->dbconnect);
    }

    public function disconnect()
    {
        $this->dbconnect = null;
    }

    /* attempt to connect to the default postgres database.
     * currently we try two different names: 'host' and 'postgres'
     */
    public function connectToHostDb()
    {
        $this->setDb('host');
        $this->connect();
        if ($this->dbconnect) {
            return;
        }
        $this->setDb('postgres');
        $this->connect();
        if (!$this->dbconnect) {
            echo "Error connecting to host postgres database.\n";
            echo "Tried names 'host' and 'postgres'\n";
        }
    }

    public function create($db)
    {
        $this->connectToHostDb();
        if (!pdo_query("CREATE DATABASE $db")) {
            $this->disconnect();
            return false;
        }
        $this->disconnect();
        $this->setDb($db);
        return true;
    }

    public function drop($db)
    {
        $this->connectToHostDb();
        if (!pdo_query("DROP DATABASE $db")) {
            $this->disconnect();
            return false;
        }
        $this->disconnect();
        return true;
    }

    public function connectToDb()
    {
        $this->connect();
        if (!$this->dbconnect) {
            return false;
        }
        return true;
    }

    public function query($query)
    {
        $this->connect();
        $resource = pdo_query($query);
        if (!$resource) {
            return false;
        }
        $result = array();
        while ($row = pdo_fetch_array($resource, \PDO::FETCH_ASSOC)) {
            $result[] = $row;
        }
        $this->disconnect();
        return $result;
    }

    public function fillDb($sqlfile)
    {
        if (!$this->dbconnect) {
            $this->connect();
        }
        $file_content = file($sqlfile);
        //print_r($file_content);
        $query = '';
        $line_number = 0;
        foreach ($file_content as $sql_line) {
            $tsl = trim($sql_line);
            if (($sql_line != '') && (substr($tsl, 0, 2) != '--') && (substr($tsl, 0, 1) != '#')) {
                $query .= $sql_line;
                if (preg_match("/;\s*$/", $sql_line)) {
                    $query = str_replace(';', '', "$query");
                    $result = pdo_query($query);
                    if (!$result) {
                        echo 'Error line:' . $line_number . '<br/>';
                        return pdo_error();
                    }
                    $query = '';
                }
            }
            $line_number++;
        }
        $pwd = password_hash('simpletest', PASSWORD_DEFAULT);
        $query = 'INSERT INTO "user" (email, password, firstname, lastname, institution, admin) ';
        $query .= "VALUES ('simpletest@localhost', '$pwd', 'administrator', '','Kitware Inc.', 1)";
        pdo_query($query);
        echo pdo_error();

        // Create the language. PgSQL has no way to know if the language already
        // exists
        @pdo_query('CREATE LANGUAGE plpgsql');

        $sqlfile = str_replace('.sql', '.ext.sql', $sqlfile);
        // If we are with PostGreSQL we need to add some extra functions
        $file_content = file($sqlfile);
        $query = '';
        foreach ($file_content as $sql_line) {
            $tsl = trim($sql_line);
            if (($sql_line != '') && (substr($tsl, 0, 2) != '--')) {
                $query .= $sql_line;
                if (strpos('CREATE ', $sql_line) !== false) {
                    // We need to remove only the last semicolon
                    $pos = strrpos($query, ';');
                    if ($pos !== false) {
                        $query = substr($query, 0, $pos) . substr($query, $pos + 1);
                    }

                    $result = pdo_query($query);
                    if (!$result) {
                        $xml = '<db_created>0</db_created>';
                        die(pdo_error());
                    }
                    $query = '';
                }
            }
        }

        // Run the last query
        $pos = strrpos($query, ';');
        if ($pos !== false) {
            $query = substr($query, 0, $pos) . substr($query, $pos + 1);
        }

        $result = pdo_query($query);
        if (!$result) {
            $xml = '<db_created>0</db_created>';
            die(pdo_error());
        }
        $this->disconnect();
        return true;
    }
}
