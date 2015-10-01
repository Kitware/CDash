<?php
require_once(dirname(dirname(__FILE__)) . '/config.test.php');

/**
  *    db object to allow the user to interact with
  *    a database
  *    @package KWSimpletest
  */

class database
{
    public $dbo = null;
    public $type = null;

    public function __construct($type)
    {
        switch ($type) {
      case "mysql":
        $this->dbo = new dbo_mysql();
        $this->type = "mysql";
        break;

      case "pgsql":
        $this->dbo = new dbo_pgsql();
        $this->type = "pgsql";
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
}

class dbo
{
    public $host      = null;
    public $port      = null;
    public $user      = null;
    public $password  = null;
    public $db        = null;
    public $dbconnect = null;

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
}

class dbo_mysql extends dbo
{
    public function connect()
    {
        $host = $this->host;
        if (!empty($this->port)) {
            $host .= ':'.$this->port;
        }
        $this->dbconnect = mysql_connect($host,
                                      $this->user,
                                      $this->password);
    }

    public function disconnect()
    {
        mysql_close($this->dbconnect);
        $this->dbconnect = null;
    }

    public function create($db)
    {
        $this->connect();
        $this->setDb($db);
        if (!mysql_query("CREATE DATABASE IF NOT EXISTS $db")) {
            $this->disconnect();
            return false;
        }
        $this->disconnect();
        return true;
    }

    public function drop($db)
    {
        $this->connect();
        $this->setDb($db);
        if (!mysql_query("DROP DATABASE IF EXISTS $db")) {
            $this->disconnect();
            return false;
        }
        $this->disconnect();
        return true;
    }

    public function connectToDb()
    {
        if (!$this->dbconnect) {
            $this->connect();
        }
        return mysql_select_db($this->db, $this->dbconnect);
    }

    public function query($query)
    {
        $this->connectToDb();
        $resource = mysql_query($query);
        if (!$resource || $resource === true) {
            return false;
        }
        $result = array();
        while ($row = mysql_fetch_array($resource, MYSQL_ASSOC)) {
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
        $query = "";
        foreach ($file_content as $sql_line) {
            $tsl = trim($sql_line);
            if (($sql_line != "") && (substr($tsl, 0, 2) != "--") && (substr($tsl, 0, 1) != "#")) {
                $query .= $sql_line;
                if (preg_match("/;\s*$/", $sql_line)) {
                    $query = str_replace(";", "", "$query");
                    $result = mysql_query($query);
                    if (!$result) {
                        die(mysql_error());
                    }
                    $query = "";
                }
            }
        } // end for each line
     mysql_query("INSERT INTO user VALUES (1, 'simpletest@localhost', '".md5('simpletest')."', 'administrator', '','Kitware Inc.', 1, '')");
        echo mysql_error();
        $this->disconnect();
        return true;
    }
}

class dbo_pgsql extends dbo
{
    public function connect($dbname = null)
    {
        $dbname   = $this->db;
        $host     = $this->host;
        $user     = $this->user;
        $password = $this->password;
        $conn  = "host='$host' dbname='$dbname' user='$user' password='$password'";
        if (!empty($this->port)) {
            $conn .= " port='$this->port'";
        }
        @$this->dbconnect = pg_connect($conn, PGSQL_CONNECT_FORCE_NEW);
    }

    public function disconnect()
    {
        pg_close($this->dbconnect);
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
        if (!pg_query($this->dbconnect, "CREATE DATABASE $db")) {
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
        if (!pg_query($this->dbconnect, "DROP DATABASE $db")) {
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
        $resource = pg_query($this->dbconnect, $query);
        if (!$resource) {
            return false;
        }
        $result = array();
        while ($row = pg_fetch_array($resource, null, PGSQL_ASSOC)) {
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
     $query = "";
        $linnum=0;
        foreach ($file_content as $sql_line) {
            $tsl = trim($sql_line);
            if (($sql_line != "") && (substr($tsl, 0, 2) != "--") && (substr($tsl, 0,
           1) != "#")) {
                $query .= $sql_line;
                if (preg_match("/;\s*$/", $sql_line)) {
                    $query = str_replace(";", "", "$query");
                    $result = pg_query($query);
                    if (!$result) {
                        echo "Error line:".$linnum."<br/>";
                        return pg_last_error();
                    }
                    $query = "";
                }
            }
            $linnum++;
        } // end for each line
     $pwd = md5('simpletest');
        $query = "INSERT INTO \"user\" (email, password, firstname, lastname, institution, admin) ";
        $query .= "VALUES ('simpletest@localhost', '$pwd', 'administrator', '','Kitware Inc.', 1)";
        pg_query($this->dbconnect, $query);
        echo pg_last_error();

     // Create the language. PgSQL has no way to know if the language already
     // exists
     @pg_query("CREATE LANGUAGE plpgsql");

        $sqlfile = str_replace(".sql", ".ext.sql", $sqlfile);
     // If we are with PostGreSQL we need to add some extra functions
     $file_content = file($sqlfile);
        $query = "";
        foreach ($file_content as $sql_line) {
            $tsl = trim($sql_line);
            if (($sql_line != "") && (substr($tsl, 0, 2) != "--")) {
                $query .= $sql_line;
                if (strpos("CREATE ", $sql_line) !== false) {
                    // We need to remove only the last semicolon
             $pos = strrpos($query, ";");
                    if ($pos !== false) {
                        $query = substr($query, 0, $pos).substr($query, $pos+1);
                    }

                    $result = pg_query($query);
                    if (!$result) {
                        $xml .= "<db_created>0</db_created>";
                        die(pg_last_error());
                    }
                    $query = "";
                }
            }
        } // end foreach line

       // Run the last query
       $pos = strrpos($query, ";");
        if ($pos !== false) {
            $query = substr($query, 0, $pos).substr($query, $pos+1);
        }

        $result = pg_query($query);
        if (!$result) {
            $xml .= "<db_created>0</db_created>";
            die(pg_last_error());
        }
        $this->disconnect();
        return true;
    }
}
