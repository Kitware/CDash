<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: common.php,v $
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
include("config.php");
include("common.php"); 
include("version.php"); 

$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
  
$xml .= "<connectiondb_host>".$CDASH_DB_HOST."</connectiondb_host>";
$xml .= "<connectiondb_login>".$CDASH_DB_LOGIN."</connectiondb_login>";
$xml .= "<connectiondb_name>".$CDASH_DB_NAME."</connectiondb_name>";

// Step 1: Check if we can connect to the database
@$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
if(!$db)
  {
  $xml .= "<connectiondb>0</connectiondb>";
  }
else
  {
  $xml .= "<connectiondb>1</connectiondb>";
  }    
  
if(xslt_create() === FALSE)
  {
  $xml .= "<xslt>0</xslt>";
  }
else
  {
  $xml .= "<xslt>1</xslt>";
  }

if(function_exists("curl_init") == FALSE)
  {  
  $xml .= "<phpcurl>0</phpcurl>";
  }
else
  {
  $xml .= "<phpcurl>1</phpcurl>";
  }
  
// If the database already exists and we have all the tables
if(@mysql_select_db("$CDASH_DB_NAME",$db) === TRUE
   && mysql_query("SELECT id FROM user LIMIT 1",$db))
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
  if(!mysql_query("CREATE DATABASE IF NOT EXISTS $CDASH_DB_NAME"))
    {
    $xml .= "<db_created>0</db_created>";
    $xml .= "<alert>".mysql_error()."</alert>";
    }
  else
  {
  mysql_select_db("$CDASH_DB_NAME",$db);
  $sqlfile = "sql/cdash.sql";
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
         $result = mysql_query($query);
         if (!$result)
           { 
           $xml .= "<db_created>0</db_created>";
           die(mysql_error());
           }
         $query = "";
         }
       }
     } // end for each line
  
  $sqlfile = "sql/cdashdata.sql";
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
         $result = mysql_query($query);
         if (!$result)
           { 
           $xml .= "<db_created>0</db_created>";
           die(mysql_error());
           }
         $query = "";
         }
       }
     } // end for each line*/
   $xml .= "<db_created>1</db_created>";
   } // end database created
} // end submit

} // end database doesn't exists


$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"install");
?>
