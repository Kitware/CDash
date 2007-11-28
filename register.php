<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: common.php,v $
  Language:  PHP
  Date:      $Date: 2007-10-16 11:23:29 -0400 (Tue, 16 Oct 2007) $
  Version:   $Revision: 12 $

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include("config.php");

$reg = "";

/** Authentication function */
function register()
{ 
  global $reg; 
  include "config.php"; 

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
      $db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
     mysql_select_db("$CDASH_DB_NAME",$db);
      $passwd = md5($passwd);
     $sql="INSERT INTO user (email,password,firstname,lastname,institution) VALUES ('$email','$passwd','$fname','$lname','$institution')";
      if(mysql_query($sql))
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
  include("common.php"); 
  
  $xml = "<cdash>";
  $xml .= "<title>Registration</title>";
  $xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
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
