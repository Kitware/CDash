<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
// This is the installation script for CDash
if (class_exists('XsltProcessor') == FALSE) 
  {
  echo "<font color=\"#FF0000\">Your PHP install does not support xslt, please install the PHP_XSLT package.<br><br>  ";
  echo "WAMP Hint: uncomment extension=php_xsl.dll in php.ini.<br></font>";
  exit();
  }

include("config.php");
require_once("pdo.php");
require_once("common.php"); 
require_once("version.php"); 

$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";

if(!isset($CDASH_DB_TYPE))
  {
  $db_type = 'mysql';
  }
else
  {
  $db_type = $CDASH_DB_TYPE;
  }
$xml .= "<connectiondb_type>".$db_type."</connectiondb_type>";
$xml .= "<connectiondb_host>".$CDASH_DB_HOST."</connectiondb_host>";
$xml .= "<connectiondb_login>".$CDASH_DB_LOGIN."</connectiondb_login>";
$xml .= "<connectiondb_name>".$CDASH_DB_NAME."</connectiondb_name>";

// Step 1: Check if we can connect to the database
@$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
if(!$db)
  {
  $xml .= "<connectiondb>0</connectiondb>";
  }
else
  {  
  // If we are installing a database other than mysql we need to 
  // have the database already created
  if(isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE!="mysql")
    {
    if(!pdo_select_db($CDASH_DB_NAME,$link))
      {
      $xml .= "<connectiondb>0</connectiondb>";
      }
    else
      {  
      $xml .= "<connectiondb>1</connectiondb>";
      }
    }
  if(isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE=="mysql")
    {
    if(@!mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS"))
      {
      $xml .= "<connectiondb>0</connectiondb>";
      }
    else
      {  
      $xml .= "<connectiondb>1</connectiondb>";
      }
    }
  else
    {  
    $xml .= "<connectiondb>1</connectiondb>";
    }
  }    

if(xslt_create() == FALSE)
  {
  $xml .= "<xslt>0</xslt>";
  }
else
  {
  $xml .= "<xslt>1</xslt>";
  }

// Check if curl is installed
if(function_exists("curl_init") == FALSE)
  {  
  $xml .= "<phpcurl>0</phpcurl>";
  }
else
  {
  $xml .= "<phpcurl>1</phpcurl>";
  }
  
// check if the backup directory is writable 
if(!is_writable($CDASH_BACKUP_DIRECTORY))
  {  
  $xml .= "<backupwritable>0</backupwritable>";
  }
else
  {
  $xml .= "<backupwritable>1</backupwritable>";
  }
  
// check if the rss directory is writable 
if(!is_writable("rss"))
  {  
  $xml .= "<rsswritable>0</rsswritable>";
  }
else
  {
  $xml .= "<rsswritable>1</rsswritable>";
  }
  
// If the database already exists and we have all the tables
if(@pdo_select_db("$CDASH_DB_NAME",$db) === TRUE
   && pdo_query("SELECT id FROM ".qid("user")." LIMIT 1",$db))
  {
  $xml .= "<database>1</database>";
  }
else
  {
  $xml .= "<database>0</database>";
  $xml .= "<dashboard_timeframe>24</dashboard_timeframe>";
  

// If we should create the tables
@$Submit = $_POST["Submit"];
if($Submit)
{
  $db_created = true;
  
  // If this is MySQL we try to create the database
  if($db_type=='mysql')
    {
    mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
    if(!mysql_query("CREATE DATABASE IF NOT EXISTS $CDASH_DB_NAME"))
      {
      $xml .= "<db_created>0</db_created>";
      $xml .= "<alert>".mysql_error()."</alert>";
      $db_created = false;
      }
    }
    
 if($db_created)
  {
  pdo_select_db("$CDASH_DB_NAME",$db);
  $sqlfile = "sql/".$db_type."/cdash.sql";
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
         $result = pdo_query($query);
         if (!$result)
           { 
           $xml .= "<db_created>0</db_created>";
           die(pdo_error());
           }
         $query = "";
         }
       }
     } // end for each line
  
  $sqlfile = "sql/".$db_type."/cdashdata.sql";
  $file_content = file($sqlfile);
  //print_r($file_content);
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
         $result = pdo_query($query);
         if (!$result)
           { 
           $xml .= "<db_created>0</db_created>";
           die(pdo_error());
           }
         $query = "";
         }
       }
     } // end for each line*/
   $xml .= "<db_created>1</db_created>";
   
   // Set the database version
   setVersion();
   } // end database created
} // end submit

} // end database doesn't exists


$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"install");
?>
