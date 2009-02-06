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
      case "postgres":
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

?>
