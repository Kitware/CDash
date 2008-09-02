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
include_once("common.php");
include("config.php");
require_once("pdo.php");
include_once("version.php"); 

$loginerror = 0;

/** Authentication function */
function auth($SessionCachePolicy='private_no_expire')
{  
  include "config.php";
  $loginid= 1231564132;
  global $loginerror;
  $loginerror = 0;

  if(isset($CDASH_EXTERNAL_AUTH) && $CDASH_EXTERNAL_AUTH
     && isset($_SERVER['REMOTE_USER'])) 
    {
    $login = $_SERVER['REMOTE_USER'];
    $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
    pdo_select_db("$CDASH_DB_NAME",$db);
    $sql="SELECT * FROM ".qid("user")." WHERE email='$login'";
    $result = pdo_query("$sql"); 
    if ($user_array = pdo_fetch_array($result)) 
      {
      session_name("CDash");
      session_cache_limiter($SessionCachePolicy);
      session_set_cookie_params($CDASH_COOKIE_EXPIRATION_TIME);
      session_start();  
      // create the session array 
      $mysession2 = array ("login" => $login, "password" => 'this is not a v
alid password', "passwd" => $user_array['password'], "ID" => session_id(), "vali
d" => 1, "loginid" => $user_array["id"]);  
       // Use $HTTP_SESSION_VARS with PHP 4.0.6 or less
       if (!isset($_SESSION['cdash'])) 
         {
         $_SESSION['cdash'] = $mysession2;
         } 
       else 
         {
         $_SESSION['cdash'] = $mysession2;
         }                  
       pdo_free_result($result);
       return 1;                               // authentication succeeded 
       }
     @pdo_free_result($result);
     }
     
  if (@$_GET["logout"]) 
    {                             // user requested logout            
    session_name("CDash");
    session_cache_limiter('nocache');
    @session_start(); 
    unset($_SESSION['cdash']);  
    session_destroy(); 
    echo "<script language=\"javascript\">window.location='index.php'</script>";             
    return 0; 
    }
  if(isset($_POST["sent"])) // arrive from login form 
   {
    $login_ok = 0; 
    $login = $_POST["login"];
    $passwd = $_POST["passwd"];
    if ($login and $passwd)
      {
      $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
     pdo_select_db("$CDASH_DB_NAME",$db);
     $sql="SELECT * FROM ".qid("user")." WHERE email='$login'";
     $result = pdo_query("$sql"); 
     while ($user_array = pdo_fetch_array($result)) 
        {
        $pass = $user_array["password"];
        if (md5($passwd)==$pass)
          {
          session_name("CDash");
          session_cache_limiter($SessionCachePolicy);
          session_set_cookie_params($CDASH_COOKIE_EXPIRATION_TIME);
          session_start();  
          // create the session array 
          $mysession2 = array ("login" => $login, "password" => $passwd, "passwd" => $pass, "ID" => session_id(), "valid" => 1, "loginid" => $user_array["id"]);  
          // Use $HTTP_SESSION_VARS with PHP 4.0.6 or less
       if (!isset($_SESSION['cdash'])) 
            {
            $_SESSION['cdash'] = $mysession2;
            } 
          else 
            {
            $_SESSION['cdash'] = $mysession2;
            }                  
          return 1;                               // authentication succeeded 
          $login_ok = 1;  
          } 
        }
      }
    if(!$login_ok)
      {
      $loginerror = 1;
      return 0;                                   // access denied 
      }  
    }
  else
    {                                         // arrive from session var 
    session_name("CDash");
    session_cache_limiter($SessionCachePolicy);
    session_set_cookie_params($CDASH_COOKIE_EXPIRATION_TIME);
    session_start();     
    $login_ok = 0;  
    $login = @$_SESSION['cdash']["login"];                         // added by jds 
    $passwd = @$_SESSION['cdash']["passwd"];                      // added by jds 
    $password = @$_SESSION['cdash']["password"];
    $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
    pdo_select_db("$CDASH_DB_NAME",$db);
    $sql="SELECT * FROM ".qid("user")." WHERE email='$login'";
    $result = pdo_query("$sql");
    while ($user_array = pdo_fetch_array($result)) 
      {   
      $pass = $user_array["password"];
      if ($passwd==$pass)
        {
        $mysession2 = array ("login" => $login, "password" => $password,"passwd" => $pass, "ID" => session_id(), "valid" => 1, "loginid" => $user_array["id"]);  
        $_SESSION['cdash'] = $mysession2;        
        return 1;                                 // authentication succeeded 
        $login_ok = 1;  
        break;  
        }  
    }
    if(!$login_ok)
      { 
      return 0;                                   // access denied 
      }  
    }
  }  
  
/** Login Form function */
function LoginForm($loginerror)
{  
  include("config.php");
  require_once("pdo.php");
  include_once("common.php"); 
  include("version.php");
    
  $xml = "<cdash>";
  $xml .= "<title>Login</title>";
  $xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
  $xml .= "<version>".$CDASH_VERSION."</version>";
   
  if(@$_GET['note'] == "register")
    {
    $xml .= "<message>Registration Complete. Please login with your email and password.</message>";
    }
  
  if($loginerror == 1)
    {
    $xml .= "<message>Wrong email or password.</message>";
    }     
    
  $xml .= "</cdash>";
  generate_XSLT($xml,"login");
}

// -------------------------------------------------------------------------------------- 
// main 
// -------------------------------------------------------------------------------------- 
$mysession = array ("login"=>FALSE,"password" => FALSE, "passwd"=>FALSE, "ID"=>FALSE, "valid"=>FALSE, "langage"=>FALSE);  
$uri = basename($_SERVER['PHP_SELF']);  
$stamp = md5(srand(5));  
$session_OK = 0;

if(!auth(@$SessionCachePolicy) && !@$noforcelogin):                 // authentication failed 
  LoginForm($loginerror); // display login form 
  $session_OK=0;
else:                        // authentication was successful 
  $tmp = session_id();       // session is already started 
  $session_OK = 1;
endif;

// If we should use the local/prelogin.php
if(file_exists("local/prelogin.php"))
  {
  include("local/prelogin.php");
  }
?>
