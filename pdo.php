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

/** */
function pdo_connect($server = NULL, $username = NULL, $password = NULL, $new_link = false, $client_flags = 0)
{
  global $CDASH_DB_TYPE;

  if(isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE!="mysql") 
    {
    global $db_server, $db_username, $db_password;
    // using PDO, we cannot connect without a database name,
    // so store the information for later use:
    $db_server = $server;
    $db_username = $username;
    $db_password = $password;
    return true;
    }
  else 
    {
    global $last_link;
    if(!isset($server)) $server = ini_get("mysql.default_host");
    if(!isset($username)) $username = ini_get("mysql.default_user");
    if(!isset($password)) $password = ini_get("mysql.default_password");
    $last_link = mysql_connect($server, $username, $password, $new_link, $client_flags);
    return $last_link;
    }
}

/** */
function get_link_identifier($link_identifier=NULL)
{
  global $CDASH_DB_TYPE;
  global $last_link;

  if(isset($link_identifier))
    return $link_identifier;

  if(isset($last_link))
    return $last_link;

  $last_link = pdo_connect();
  return $last_link;
}

/** */
function pdo_error($link_identifier = NULL)
{
  global $CDASH_DB_TYPE;

  if(isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE!="mysql") 
    {
    $error_info = get_link_identifier($link_identifier)->errorInfo();
    if(isset($error_info[2]))
      {
      return $error_info[2];
      }
    else
      {
      return ""; // no error;
      }   
    }
  else 
    {
    return mysql_error(get_link_identifier($link_identifier));
    }
}

/** */
function pdo_fetch_array($result, $result_type = MYSQL_BOTH)
{
  global $CDASH_DB_TYPE;

  if(isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE!="mysql") 
    {
    if    ($result_type == MYSQL_BOTH)  $result_type = PDO::FETCH_BOTH;
    elseif($result_type == MYSQL_NUM)   $result_type = PDO::FETCH_NUM;
    elseif($result_type == MYSQL_ASSOC) $result_type = PDO::FETCH_ASSOC;
    return $result->fetch($result_type);
    }
  else 
    {
    return mysql_fetch_array($result, $result_type);
    }
}

/** */
function pdo_fetch_row($result)
{
  global $CDASH_DB_TYPE;

  if(isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE!="mysql") 
    {
    return pdo_fetch_array($result, PDO::FETCH_NUM);
    }
  else 
    {
    return mysql_fetch_row($result);
    }
}

/** */
function pdo_field_type($result, $field_offset)
{
  global $CDASH_DB_TYPE;

  if(isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE!="mysql") 
    {
    // TODO...
    }
  else 
    {
    return mysql_field_type($result, $field_offset);
    }
}

/** */
function pdo_field_len($result, $field_offset)
{
  global $CDASH_DB_TYPE;

  if(isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE!="mysql") 
    {
    // TODO...
    }
  else 
    {
    return mysql_field_len($result, $field_offset);
    }
}

/** */
function pdo_free_result($result)
{
  global $CDASH_DB_TYPE;

  if(isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE!="mysql") 
    {
    // TODO...
    }
  else 
    {
    return mysql_free_result($result);
    }
}

/** */
function pdo_insert_id($tablename)
{
  global $CDASH_DB_TYPE;

  if(isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE!="mysql") 
    {
    $seq  = "";
    // pgsql requires the sequence name
    if($CDASH_DB_TYPE == "pgsql")
      {
      $seq = $tablename."_id_seq";
      }
    return get_link_identifier(NULL)->lastInsertId($seq);
    }
  else 
    {
    return mysql_insert_id(get_link_identifier());
    }
}

/** */
function pdo_num_rows($result)
{
  global $CDASH_DB_TYPE;

  if(isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE!="mysql") 
    {
    return $result->rowCount();
    }
  else 
    {
    return mysql_num_rows($result);
    }
}

/** */
function pdo_query($query, $link_identifier = NULL)
{
  global $CDASH_DB_TYPE;

  if(isset($CDASH_DB_TYPE)  && $CDASH_DB_TYPE!="mysql") 
    {
    return get_link_identifier($link_identifier)->query($query);
    }
  else 
    {
    return mysql_query($query, get_link_identifier($link_identifier));
    }
}

/** */
function pdo_real_escape_string($unescaped_string, $link_identifier = NULL)
{
  global $CDASH_DB_TYPE;

  if(isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE!="mysql") 
    {
    $str = get_link_identifier($link_identifier)->quote($unescaped_string);
    // in contrast to mysql_real_escape_string(),
    // PDO::quote() also adds the enclosing quotes,
    // which need to be removed:
    return substr($str, 1, strlen($str) - 2);
    }
  else 
    {
    return mysql_real_escape_string($unescaped_string, get_link_identifier($link_identifier));
    }
}

/** */
function pdo_select_db($database_name, &$link_identifier)
{
  global $CDASH_DB_TYPE;

  if(isset($CDASH_DB_TYPE) && $CDASH_DB_TYPE!="mysql") 
    {
    global $db_server, $db_username, $db_password, $last_link;
    $dsn = "$CDASH_DB_TYPE:host=$db_server;dbname=$database_name";

    try 
      {
      $last_link = $link_identifier = new PDO($dsn, $db_username, $db_password);
      if($CDASH_DB_TYPE == "mysql") // necessary for looping through rows
        {
        $link_identifier->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }
      return true;
      }
    catch(PDOException $e) 
      {
      return false;
      }
    }
  else 
    {
    return mysql_select_db($database_name, get_link_identifier($link_identifier));
    }
}

?>
