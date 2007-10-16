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
include("config.php");

/** Authentication function */
function auth()
{  
  include "config.php";
  $loginid= 1231564132;
  $m_error = 0;	
  if (@$_GET["logout"]) 
				{                             // user requested logout            
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
      $db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
	    mysql_select_db("$CDASH_DB_NAME",$db);
    	$sql="SELECT * FROM user WHERE email='$login'";
	    $result = mysql_query("$sql"); 
	    while ($user_array = mysql_fetch_array($result)) 
        {
								$pass = $user_array["password"];
								if (md5($passwd)==$pass)
          {
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
	    $m_error = 1;
      return 0;                                   // access denied 
      }  
    }
  else
    {                                         // arrive from session var 
    //session_cache_expire(5);
    session_start();     
    $login_ok = 0;  
    $login = @$_SESSION['cdash']["login"];                         // added by jds 
    $passwd = @$_SESSION['cdash']["passwd"];                      // added by jds 
				$password = @$_SESSION['cdash']["password"];
    $db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
	   mysql_select_db("$CDASH_DB_NAME",$db);
    $sql="SELECT * FROM user WHERE email='$login'";
    $result = mysql_query("$sql");
	   while ($user_array = mysql_fetch_array($result)) 
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
	 include("common.php");	
		
		$xml = "<cdash>";
		$xml .= "<title>Login</title>";
		$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
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
@$loginerror = $GLOBALS["loginerror"];
if(!auth()):                 // authentication failed 
  LoginForm(@$loginerror); // display login form 
  $session_OK=0;
else:                        // authentication was successful 
  $tmp = session_id();       // session is already started 
  $session_OK = 1;
endif;

?>
