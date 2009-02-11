<?php
/**
  *    db object to allow the user to interact with 
  *    a database
  *    @package KWSimpletest
  */

class database 
{
  var $dbo = null;
  
  function __construct($type)
   {
   switch ($type)
     {
      case "mysql":
        $this->dbo = new dbo_mysql(); 
        break;
      case "pgsql":
        $this->dbo = new dbo_pgsql();
        break;
      default:
        $status = "database type unsupported.\n";
        exit($status);
      }
   }
  
  function connect()
    {
    $this->dbo->connect();
    return $this->dbo->getDbConnect();
    }
  
  function connectedToDb()
    {
    return $this->dbo->connectToDb();
    }
  
  function disconnect()
    {
    $this->dbo->disconnect();
    return $this->dbo->getDbConnect();
    }
  
  function getInstance()
   {
   return $this->dbo;
   }
  
  function setHost($host)
   {
   $this->dbo->setHost($host);
   }
  
  function setDb($db)
   {
   $this->dbo->setDb($db);
   }
  
  function setPassword($password)
   {
   $this->dbo->setPassword($password);
   }
  
  function setUser($user)
   {
   $this->dbo->setUser($user);
   }
  
  function create($db)
   {
   return $this->dbo->create($db);
   }
  
  function drop($db)
   {
   return $this->dbo->drop($db);
   }
  
  function fillDb($sqlfile)
   {
   return $this->dbo->fillDb($sqlfile);
   }
  
  function query($query)
   {
   return $this->dbo->query($query);
   }
}

class dbo
{
   var $host      = null;
   var $user      = null;
   var $password  = null;
   var $db        = null;
   var $dbconnect = null;
  
   function getDbConnect()
     {
     return $this->dbconnect;
     }
  
   function setHost($host)
     {
      $this->host = $host;
     }
  
   function setDb($db)
     {
     $this->db = $db;
     }
  
   function setUser($user)
     {
      $this->user = $user;
     }
  
   function setPassword($pasword)
     {
     $this->password = $pasword;
     }
}

class dbo_mysql extends dbo
{
   function connect()
     {
     $this->dbconnect = mysql_connect($this->host,
                                      $this->user,
                                      $this->password);
     }
  
   function disconnect()
     {
      mysql_close($this->dbconnect);
     $this->dbconnect = null;
     }
  
   function create($db)
     {
     $this->connect();
     $this->setDb($db);
     if(!mysql_query("CREATE DATABASE IF NOT EXISTS $db"))
       {
       $this->disconnect();
       return false;
       }
     $this->disconnect();
     return true;
     }
  
   function drop($db)
     {
     $this->connect();
     $this->setDb($db);
     if(!mysql_query("DROP DATABASE IF EXISTS $db"))
       {
       $this->disconnect();
       return false;
       }
     $this->disconnect();
     return true;
     }
  
   function connectToDb()
     {
     if(!$this->dbconnect)
       {
       $this->connect();
       }
     return mysql_select_db($this->db,$this->dbconnect);
     }
  
   function query($query)
     {
     $this->connectToDb();
     $resource = mysql_query($query);
     if(!$resource)
       {
       return false;
       }
     $result = array();
     while($row = mysql_fetch_array($resource,MYSQL_ASSOC))
       {
       $result[] = $row;
       }
     $this->disconnect();
     return $result;
     }
  
   function fillDb($sqlfile)
     {
     if(!$this->dbconnect)
        {
        $this->connect();
        }
     $this->connectToDb();
     $file_content = file($sqlfile);
     $query = "";
     foreach($file_content as $sql_line)
       {
       $tsl = trim($sql_line);
        if (($sql_line != "") && (substr($tsl, 0, 2) != "--") && (substr($tsl, 0, 1) != "#"))
          {
          $query .= $sql_line;
          if(preg_match("/;\s*$/", $sql_line))
            {
            $query = str_replace(";", "", "$query");
            $result = mysql_query($query);
            if (!$result)
              {
              die(mysql_error());
              }
            $query = "";
            }
          }
       } // end for each line
     mysql_query("INSERT INTO user VALUES (1, 'simpletest@localhost', '".md5('simpletest')."', 'administrator', '','Kitware Inc.', 1)");
     echo mysql_error();
     $this->disconnect();
     return true;
     }
}

class dbo_postgre extends dbo
{
   function connect($dbname = null)
     {
     if(!$dbname)
       {
       $dbname = $this->db;
       }
     $host     = $this->host;
     $user     = $this->user;
     $password = $this->password; 
     $conn  = "host='$host' dbname='$dbname' user='$user' password='$password'";
     $this->dbconnect = pg_connect($conn, PGSQL_CONNECT_FORCE_NEW);
     }
  
   function disconnect()
     {
     pg_close($this->dbconnect);
     $this->dbconnect = null;
     }
  
   function create($db)
     {
     $this->connect('host');
     pg_query($this->dbconnect,"CREATE DATABASE IF NOT EXISTS $db");
     $this->disconnect();
     }
  
   function drop($db)
     {
     $this->connect('host');
     pg_query($this->dbconnect,"DROP DATABASE $db");
     $this->disconnect();
     }
  
   function connectToDb()
     {
     $this->connect();
     if(!$this->dbconnect)
       {
       return false;
       }
     return true;
     }
  
   function query($query)
     {
     $this->connect();
     $resource = pg_query($this->dbconnect,$query);
     if (!$result) 
       {
       return false;
       }
     $result = array();
     while($row = pg_fetch_array($result, NULL, PGSQL_ASSOC))
       {
       $result[] = $row;
       }
     $this->disconnect();
     return $result;
     }
  
   function fillDb($sqlfile)
     {
     if(!$this->dbconnect)
        {
        $this->connect();
        }
     $file_content = file($sqlfile);
     //print_r($file_content);
     $query = "";
     $linnum=0;
     foreach($file_content as $sql_line)
       {
       $tsl = trim($sql_line);
       if (($sql_line != "") && (substr($tsl, 0, 2) != "--") && (substr($tsl, 0,
           1) != "#"))
         {
         $query .= $sql_line;
         if(preg_match("/;\s*$/", $sql_line))
           {
           $query = str_replace(";", "", "$query");
           $result = pg_query($query);
           if (!$result)
             {
             echo "Error line:".$linnum."<br/>";
             return pg_last_error();
             }
           $query = "";
           }
         }
       $linnum++;
       } // end for each line
     echo pg_last_error();
     $this->disconnect();
     return true;
     }
}

?>