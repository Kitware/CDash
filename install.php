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
  echo "<font color=\"#FF0000\">Your PHP install does not support xslt, please install the PHP_XSLT package.<br>  ";
  echo "WAMP Hint: uncomment extension=php_xsl.dll in php.ini.<br></font>";
  exit();
  }
if (function_exists('imagecreatefromstring') == FALSE) 
  {
  echo "<font color=\"#FF0000\">Your PHP install does not support the imagecreatefromstring() function, please install the PHP_GD package.<br>  ";
  echo "WAMP Hint: uncomment extension=php_gd.dll in php.ini.<br></font>";
  exit();
  }
  
include("cdash/config.php");
require_once("cdash/pdo.php");
require_once("cdash/common.php"); 
require_once("cdash/version.php"); 
 
if($CDASH_PRODUCTION_MODE)
  {
  echo "CDash is in production mode. Install cannot be accessed. Change the variable in your config.php if you want to access the installation.";
  return;
  }

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
  $admin_email = $_POST["admin_email"];
  $admin_password = $_POST["admin_password"];
  
  $valid_email = true;
  
  if(strlen($admin_email) < 6 || strstr($admin_email,'@') === FALSE)
    {
    $xml .= "<db_created>0</db_created>";
    $xml .= "<alert>* Administrator's email should be a valid email address</alert>";
    $valid_email = false;
    }
  
  if($valid_email && strlen($admin_password)<5)
    {
    $xml .= "<db_created>0</db_created>";
    $xml .= "<alert>* Administrator's password should be at least 5 characters</alert>";
    $valid_email = false;
    }    
  
  if($valid_email)
    {
    $db_created = true;
    // If this is MySQL we try to create the database
    if($db_type=='mysql')
      {
      mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
      if(!mysql_query("CREATE DATABASE IF NOT EXISTS `$CDASH_DB_NAME`"))
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
           // We need to remove only the last semicolon
           $pos = strrpos($query,";");
           if($pos !== false)
             {
             $query = substr($query,0,$pos).substr($query,$pos+1);
             }
             
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
    
    // If we are with PostGreSQL we need to add some extra functions
    if($db_type == 'pgsql')
       {
       $sqlfile = "sql/pgsql/cdash.ext.sql";
       
       // Create the language. PgSQL has no way to know if the language already 
       // exists
       @pdo_query("CREATE LANGUAGE plpgsql");
       
       $file_content = file($sqlfile);
       $query = "";
       foreach($file_content as $sql_line)
         {
         $tsl = trim($sql_line);
         if (($sql_line != "") && (substr($tsl, 0, 2) != "--")) 
           {
           $query .= $sql_line;
           $possemicolon = strrpos($query,";");
           if($possemicolon !== false && substr_count($query,'\'',0,$possemicolon)%2==0) 
             {
             // We need to remove only the last semicolon
             $pos = strrpos($query,";");
             if($pos !== false)
               {
               $query = substr($query,0,$pos).substr($query,$pos+1);
               }
             $result = pdo_query($query);
             if (!$result)
               { 
               $xml .= "<db_created>0</db_created>";
               die(pdo_error());
               }
             $query = "";
             }
           }
         } // end foreach line
       } // end pgsql functions

     pdo_query("INSERT INTO ".qid("user")." (id,email,password,firstname,lastname,institution,admin) VALUES (1, '".$admin_email."', '".md5($admin_password)."', 'administrator', '','Kitware Inc.', 1)");
     echo pdo_error();
    
     $xml .= "<db_created>1</db_created>";
     
     // Set the database version
     setVersion();
     } // end database created
  } // end check valid username and password
} // end submit

} // end database doesn't exists


$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"install");
?>
