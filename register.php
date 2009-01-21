<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id: common.php,v $
  Language:  PHP
  Date:      $Date: 2007-10-16 11:23:29 -0400 (Tue, 16 Oct 2007) $
  Version:   $Revision: 12 $

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include_once("common.php");
include_once("config.php");

$reg = "";

/** Authentication function */
function register()
{ 
  global $reg; 
  include("config.php");
  require_once("pdo.php"); 

  if(isset($_POST["sent"])) // arrive from register form 
   {
    $url   = $_POST["url"];
    if($url != "catchbot")
      {
      $reg = "Bots are not allowed to obtain CDash accounts!";
      return 0;
      }
    $email = $_POST["email"];
    $passwd = $_POST["passwd"];
    $passwd2 = $_POST["passwd2"];
    if(!($passwd == $passwd2))
      {
      $reg = "Passwords do not match!";
      return 0;
      }
    $fname = $_POST["fname"];
    $lname = $_POST["lname"];
    $institution = $_POST["institution"];
    if ($email and $passwd and $passwd2 and $fname and $lname and $institution)
      {
      $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
      pdo_select_db("$CDASH_DB_NAME",$db);
      $passwd = md5($passwd);
      $email = pdo_real_escape_string($email);

      $sql = "SELECT * FROM ".qid("user")." WHERE email='$email'";
      if(pdo_num_rows(pdo_query($sql)) > 0)
        {
        $reg = "$email is already registered.";
        return 0;
        }

      $passwd = pdo_real_escape_string($passwd);
      $fname = pdo_real_escape_string($fname);
      $lname = pdo_real_escape_string($lname);
      $institution = pdo_real_escape_string($institution);
            
      $sql="INSERT INTO ".qid("user")." (email,password,firstname,lastname,institution) 
            VALUES ('$email','$passwd','$fname','$lname','$institution')";
      if(pdo_query($sql))
        {
        return 1;
        }
      else
        {
        $reg = "Database Error!";
        return 0;
        }
      }
    else
      {
      $reg = "Please fill in all of the required fields";
      return 0;
      }
    }
 else
    {
    return 0;
    }
} 
  
/** Login Form function */
function RegisterForm($regerror)
{  
  include("config.php");
  require_once("pdo.php");
  include_once("common.php");
  include_once('version.php');
  
  if(isset($CDASH_NO_REGISTRATION) && $CDASH_NO_REGISTRATION==1)
    {
    die("You cannot access this page. Contact your administrator if you think that's an error.");
    }
    
  $xml = '<?xml version="1.0"?><cdash>';
  $xml .= "<title>CDash - Registration</title>";
  $xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
  $xml .= "<version>".$CDASH_VERSION."</version>";  
  $xml .= "<error>" . $regerror . "</error>";
  $xml .= "</cdash>";
  
  generate_XSLT($xml,"register");
}

// -------------------------------------------------------------------------------------- 
// main 
// -------------------------------------------------------------------------------------- 
if(!register())                 // registration failed 
  RegisterForm($reg);    // display register form 
else
  header( 'location: user.php?note=register' );

?>
