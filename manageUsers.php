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
include("config.php");
require_once("pdo.php");
include('login.php');
include_once('common.php');
include('version.php');

if ($session_OK) 
  {
  @$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  pdo_select_db("$CDASH_DB_NAME",$db);

  $usersessionid = $_SESSION['cdash']['loginid'];
  // Checks
  if(!isset($usersessionid) || !is_numeric($usersessionid))
    {
    echo "Not a valid usersessionid!";
    return;
    }
   
  $user_array = pdo_fetch_array(pdo_query("SELECT admin FROM ".qid("user")." WHERE id='$usersessionid'"));

  if($user_array["admin"]!=1)
    {
    echo "You don't have the permissions to access this page";
    return;
    }
  
  $xml = "<cdash>";
  $xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
  $xml .= "<version>".$CDASH_VERSION."</version>";
  $xml .= "<backurl>user.php</backurl>";
  $xml .= "<title>CDash - Manage Users</title>";
  $xml .= "<menutitle>CDash</menutitle>";
  $xml .= "<menusubtitle>Manage Users</menusubtitle>";
  
  if(isset($_POST["makenormaluser"]))
    {
    if($_POST["userid"] > 1)
      {
      $update_array = pdo_fetch_array(pdo_query("SELECT firstname,lastname FROM ".qid("user")." WHERE id='".$_POST["userid"]."'"));
      pdo_query("UPDATE ".qid("user")." SET admin=0 WHERE id='".$_POST["userid"]."'");
      $xml .= "<warning>".$update_array['firstname']." ".$update_array['lastname']." is not administrator anymore.</warning>";
      }
    else
      {
      $xml .= "<error>Administrator should remain admin.</error>";
      }
    }
  else if(isset($_POST["makeadmin"]))
    {
    $update_array = pdo_fetch_array(pdo_query("SELECT firstname,lastname FROM ".qid("user")." WHERE id='".$_POST["userid"]."'"));
    pdo_query("UPDATE ".qid("user")." SET admin=1 WHERE id='".$_POST["userid"]."'");
    $xml .= "<warning>".$update_array['firstname']." ".$update_array['lastname']." is now an administrator.</warning>";
    }
  else if(isset($_POST["removeuser"]))
    {
    $update_array = pdo_fetch_array(pdo_query("SELECT firstname,lastname FROM ".qid("user")." WHERE id='".$_POST["userid"]."'"));
    pdo_query("DELETE FROM ".qid("user")." WHERE id='".$_POST["userid"]."'");
    $xml .= "<warning>".$update_array['firstname']." ".$update_array['lastname']." has been removed.</warning>";
    }
    
  if(isset($_POST["search"]))
    {
    $xml .= "<search>".$_POST["search"]."</search>";
    }

if(isset($CDASH_FULL_EMAIL_WHEN_ADDING_USER) && $CDASH_FULL_EMAIL_WHEN_ADDING_USER==1)
  {
  $xml .= add_XML_value("fullemail","1");
  }
  
  
$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"manageUsers");
  } // end session
?>

