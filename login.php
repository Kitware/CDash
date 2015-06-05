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
include_once("cdash/common.php");
include("cdash/config.php");
require_once("cdash/pdo.php");
include_once("cdash/version.php");

$loginerror = "";

/** Database authentication */
function databaseAuthenticate($email,$password,$SessionCachePolicy,$rememberme)
{
  global $loginerror;
  $loginerror = "";

  include "cdash/config.php";

  $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  pdo_select_db("$CDASH_DB_NAME",$db);
  $sql="SELECT id,password FROM ".qid("user")." WHERE email='".pdo_real_escape_string($email)."'";
  $result = pdo_query("$sql");

  if(pdo_num_rows($result)==0)
    {
    pdo_free_result($result);
    $loginerror = "Wrong email or password.";
    return false;
    }

  $user_array = pdo_fetch_array($result);
  $pass = $user_array["password"];

  // External authentication
  if($password === NULL && isset($CDASH_EXTERNAL_AUTH) && $CDASH_EXTERNAL_AUTH)
    {
    // create the session array
    $sessionArray = array ("login" => $login, "password" => 'this is not a valid password', "passwd" => $user_array['password'], "ID" => session_id(), "valid" => 1, "loginid" => $user_array["id"]);
    $_SESSION['cdash'] = $sessionArray;
    pdo_free_result($result);
    return true;                               // authentication succeeded
    }
  else if(md5($password)==$pass)
    {
    if($rememberme)
      {
      $cookiename = "CDash-".$_SERVER['SERVER_NAME'];
      $time = time()+60*60*24*30; // 30 days;

      // Create a new password
      $keychars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
      $length = 32;

      // seed with microseconds
      function make_seed_recoverpass()
        {
        list($usec, $sec) = explode(' ', microtime());
        return (float) $sec + ((float) $usec * 100000);
        }
      srand(make_seed_recoverpass());

      $key = "";
      $max=strlen($keychars)-1;
      for ($i=0;$i<=$length;$i++)
        {
        $key .= substr($keychars, rand(0, $max), 1);
        }

      $value = $user_array['id'].$key;
      setcookie($cookiename,$value, $time);

      // Update the user key
      pdo_query("UPDATE ".qid("user")." SET cookiekey='".$key."' WHERE id=".qnum($user_array['id']));
      }

    session_name("CDash");
    session_cache_limiter($SessionCachePolicy);
    session_set_cookie_params($CDASH_COOKIE_EXPIRATION_TIME);
    @ini_set('session.gc_maxlifetime', $CDASH_COOKIE_EXPIRATION_TIME+600);
    session_start();

    // create the session array
    if(isset($_SESSION['cdash']["password"]))
      {
      $password = $_SESSION['cdash']["password"];
      }
    $sessionArray = array ("login" => $email, "passwd" => $pass, "ID" => session_id(), "valid" => 1, "loginid" => $user_array["id"]);
    $_SESSION['cdash'] = $sessionArray;
    return true;
    }

  $loginerror = "Wrong email or password.";
  return false;
}


/** LDAP authentication */
function ldapAuthenticate($email,$password,$SessionCachePolicy,$rememberme)
{
  global $loginerror;
  $loginerror = "";

  include "cdash/config.php";
  include_once "models/user.php";

  $ldap = ldap_connect($CDASH_LDAP_HOSTNAME);
  ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION,$CDASH_LDAP_PROTOCOL_VERSION);
  ldap_set_option($ldap, LDAP_OPT_REFERRALS,$CDASH_LDAP_OPT_REFERRALS);
  // Bind as the LDAP user if authenticated ldap is enabled
  if($CDASH_LDAP_AUTHENTICATED)
    {
    ldap_bind($ldap, $CDASH_LDAP_BIND_DN, $CDASH_LDAP_BIND_PASSWORD);
    }

  if(isset($ldap) && $ldap != '')
    {
    /* search for pid dn */
    $result = ldap_search($ldap,$CDASH_LDAP_BASEDN,
      '(&(mail='.$email.')'.$CDASH_LDAP_FILTER.')', array('dn','cn'));
    if ($result != 0)
      {
      $entries = ldap_get_entries($ldap, $result);
      @$principal = $entries[0]['dn'];
      if(isset($principal))
        {
        // bind as this user
        if(@ldap_bind($ldap, $principal, $password))
          {
          $sql="SELECT id,password FROM ".qid("user")." WHERE email='".pdo_real_escape_string($email)."'";
          $result = pdo_query("$sql");

          // If the user doesn't exist we add it
          if(pdo_num_rows($result)==0)
            {
            @$givenname = $entries[0]['cn'][0];
            if(!isset($givenname))
              {
              $loginerror = 'No givenname (cn) set in LDAP, cannot register user into CDash';
              return false;
              }
            $names = explode(" ",$givenname);

            $User = new User;

            if(count($names)>1)
              {
              $User->FirstName = $names[0];
              $User->LastName = $names[1];
              for($i=2;$i<count($names);$i++)
                {
                $User->LastName .= " ".$names[$i];
                }
              }
            else
              {
              $User->LastName = $names[0];
              }

            // Add the user in the database
            $storedPassword = md5($password);
            $User->Email = $email;
            $User->Password = $storedPassword;
            $User->Save();
            $userid = $User->Id;
            }
          else
            {
            $user_array = pdo_fetch_array($result);
            $storedPassword = $user_array["password"];
            $userid = $user_array["id"];

            // If the password has changed we update
            if($storedPassword != md5($password))
              {
              $User = new User;
              $User->Id = $userid;
              $User->SetPassword(md5($password));
              }
            }

          if($rememberme)
            {
            $cookiename = "CDash-".$_SERVER['SERVER_NAME'];
            $time = time()+60*60*24*30; // 30 days;

            // Create a new password
            $keychars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
            $length = 32;

            // seed with microseconds
            function make_seed_recoverpass()
              {
              list($usec, $sec) = explode(' ', microtime());
              return (float) $sec + ((float) $usec * 100000);
              }
            srand(make_seed_recoverpass());

            $key = "";
            $max=strlen($keychars)-1;
            for ($i=0;$i<=$length;$i++)
              {
              $key .= substr($keychars, rand(0, $max), 1);
              }

            $value = $userid.$key;
            setcookie($cookiename,$value, $time);

            // Update the user key
            pdo_query("UPDATE ".qid("user")." SET cookiekey='".$key."' WHERE id=".qnum($userid));
            }

          session_name("CDash");
          session_cache_limiter($SessionCachePolicy);
          session_set_cookie_params($CDASH_COOKIE_EXPIRATION_TIME);
          @ini_set('session.gc_maxlifetime', $CDASH_COOKIE_EXPIRATION_TIME+600);
          session_start();

          // create the session array
          if(isset($_SESSION['cdash']["password"]))
            {
            $password = $_SESSION['cdash']["password"];
            }
          $sessionArray = array ("login" => $email,"passwd" => $storedPassword, "ID" => session_id(), "valid" => 1, "loginid" => $userid);
          $_SESSION['cdash'] = $sessionArray;
          return true;
          }
        else
          {
          $loginerror = "Wrong email or password.";
          return false;
          }
        }
      else
        {
        $loginerror = 'User not found in LDAP';
        }
      ldap_free_result($result);
      }
    else
      {
      $loginerror = 'Error occured searching the LDAP';
      }
    ldap_close($ldap);
    }
  else
    {
    $loginerror = 'Could not connect to LDAP at '.$CDASH_LDAP_HOSTNAME;
    }
  return false;
}


/** authentication */
function authenticate($email,$password,$SessionCachePolicy,$rememberme)
{
  if(empty($email))
    {
    return 0;
    }
  include "cdash/config.php";

  if($CDASH_USE_LDAP)
    {
    // If the user is '1' we use it to login
    $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
    pdo_select_db("$CDASH_DB_NAME",$db);
    $query = pdo_query("SELECT id FROM ".qid("user")." WHERE email='$email'");
     if($query && pdo_num_rows($query)>0)
      {
      $user_array = pdo_fetch_array($query);
       if($user_array["id"] == 1)
        {
        return databaseAuthenticate($email,$password,$SessionCachePolicy,$rememberme);
        }
      }
    return ldapAuthenticate($email,$password,$SessionCachePolicy,$rememberme);
    }
  else
    {
    return databaseAuthenticate($email,$password,$SessionCachePolicy,$rememberme);
    }
}

/** Authentication function */
function auth($SessionCachePolicy='private_no_expire')
{
  include "cdash/config.php";
  $loginid= 1231564132;




  if(isset($CDASH_EXTERNAL_AUTH) && $CDASH_EXTERNAL_AUTH
     && isset($_SERVER['REMOTE_USER']))
    {
    $login = $_SERVER['REMOTE_USER'];
    return authenticate($login,NULL,$SessionCachePolicy,0); // we don't remember
    }

  if (@$_GET["logout"])
    {                             // user requested logout
    session_name("CDash");
    session_cache_limiter('nocache');
    @session_start();
    unset($_SESSION['cdash']);
    session_destroy();

    // Remove the cookie if we have one
    $cookienames = array("CDash", str_replace('.','_',"CDash-".$_SERVER['SERVER_NAME'])); // php doesn't like dot in cookie names
    foreach ($cookienames as $cookiename)
      {
      if(isset($_COOKIE[$cookiename]))
        {
        $cookievalue = $_COOKIE[$cookiename];
        $cookieuseridkey = substr($cookievalue,0,strlen($cookievalue)-33);
        $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
        pdo_select_db("$CDASH_DB_NAME",$db);

        pdo_query("UPDATE ".qid("user")." SET cookiekey='' WHERE id=".qnum($cookieuseridkey));
        setcookie ("CDash-".$_SERVER['SERVER_NAME'], "", time() - 3600);
        }
      }
    echo "<script language=\"javascript\">window.location='index.php'</script>";
    return 0;
    }

  if(isset($_POST["sent"])) // arrive from login form
    {
    @$login = $_POST["login"];
    if ($login != NULL)
      {
      $login = htmlspecialchars(pdo_real_escape_string($login));
      }

    @$passwd = $_POST["passwd"];
    if ($passwd != NULL)
      {
      $passwd = htmlspecialchars(pdo_real_escape_string($passwd));
      }

    @$rememberme = $_POST["rememberme"];
    if ($rememberme != NULL)
      {
      $rememberme = pdo_real_escape_numeric($rememberme);
      }

    return authenticate($login,$passwd,$SessionCachePolicy,$rememberme);
    }
  else
    {                                         // arrive from session var
    $cookiename = str_replace('.','_',"CDash-".$_SERVER['SERVER_NAME']); // php doesn't like dot in cookie names
    if(isset($_COOKIE[$cookiename]))
      {
      $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
      pdo_select_db("$CDASH_DB_NAME",$db);

      $cookievalue = $_COOKIE[$cookiename];
      $cookiekey = substr($cookievalue,strlen($cookievalue)-33);
      $cookieuseridkey = substr($cookievalue,0,strlen($cookievalue)-33);
      $sql =
        "SELECT email,password,id FROM ".qid("user")."
         WHERE cookiekey='".pdo_real_escape_string($cookiekey)."'";
      if (!empty($cookieuseridkey))
        {
        $sql .= " AND id='".pdo_real_escape_string($cookieuseridkey)."'";
        }
      $result = pdo_query("$sql");
      if(pdo_num_rows($result) == 1)
        {
        $user_array = pdo_fetch_array($result);
        session_name("CDash");
        session_cache_limiter($SessionCachePolicy);
        session_set_cookie_params($CDASH_COOKIE_EXPIRATION_TIME);
        @ini_set('session.gc_maxlifetime', $CDASH_COOKIE_EXPIRATION_TIME+600);
        session_start();

        $sessionArray = array ("login" => $user_array['email'],"passwd" => $user_array['password'], "ID" => session_id(), "valid" => 1, "loginid" => $user_array['id']);
        $_SESSION['cdash'] = $sessionArray;
        return true;
        }
      }

    session_name("CDash");
    session_cache_limiter($SessionCachePolicy);
    session_set_cookie_params($CDASH_COOKIE_EXPIRATION_TIME);
    @ini_set('session.gc_maxlifetime', $CDASH_COOKIE_EXPIRATION_TIME+600);
    session_start();

    $email = @$_SESSION['cdash']["login"];

    if(!empty($email))
      {
      $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
      pdo_select_db("$CDASH_DB_NAME",$db);
      $sql="SELECT id,password FROM ".qid("user")." WHERE email='".pdo_real_escape_string($email)."'";
      $result = pdo_query("$sql");

      if(pdo_num_rows($result)==0)
        {
        pdo_free_result($result);
        $loginerror = "Wrong email or password.";
        return false;
        }

      $user_array = pdo_fetch_array($result);
      if($user_array["password"] == $_SESSION['cdash']["passwd"])
        {
        return true;
        }
      $loginerror = "Wrong email or password.";
      return false;
      }
    }
  }

/** Login Form function */
function LoginForm($loginerror)
{
  include("cdash/config.php");
  require_once("cdash/pdo.php");
  include_once("cdash/common.php");
  include("cdash/version.php");

  $xml = begin_XML_for_XSLT();
  $xml .= "<title>Login</title>";
  if(isset($CDASH_NO_REGISTRATION) && $CDASH_NO_REGISTRATION==1)
    {
    $xml .= add_XML_value("noregister","1");
    }
  if(@$_GET['note'] == "register")
    {
    $xml .= "<message>Registration Complete. Please login with your email and password.</message>";
    }

  if($loginerror !=  "")
    {
    $xml .= "<message>".$loginerror."</message>";
    }

  if($CDASH_ALLOW_LOGIN_COOKIE)
    {
    $xml .= "<allowlogincookie>1</allowlogincookie>";
    }


  if($GOOGLE_CLIENT_ID != '' && $GOOGLE_CLIENT_SECRET != '')
    {
    $xml .= "<oauth2>";
    $xml .= add_XML_value("client", $GOOGLE_CLIENT_ID);
    $xml .= "</oauth2>";
    }

  $xml .= "</cdash>";

  if(!isset($NoXSLGenerate))
    {
    generate_XSLT($xml,"login");
    }
}

// --------------------------------------------------------------------------------------
// main
// --------------------------------------------------------------------------------------
$mysession = array ("login"=>FALSE, "passwd"=>FALSE, "ID"=>FALSE, "valid"=>FALSE, "langage"=>FALSE);
$uri = basename($_SERVER['PHP_SELF']);
$stamp = md5(srand(5));
$session_OK = 0;

if(!auth(@$SessionCachePolicy) && !@$noforcelogin)                  // authentication failed
  {

  // Create a session with a random "state" value.
  // This is used by Google OAuth2 to prevent forged logins.
  if (session_id() != '')
    {
    session_destroy();
    }
  session_name("CDash");
  session_cache_limiter(@$SessionCachePolicy);
  session_set_cookie_params($CDASH_COOKIE_EXPIRATION_TIME);
  @ini_set('session.gc_maxlifetime', $CDASH_COOKIE_EXPIRATION_TIME+600);
  session_start();
  $sessionArray = array ("state" => md5(rand()));
  $_SESSION['cdash'] = $sessionArray;
  LoginForm($loginerror); // display login form
  $session_OK=0;
  }
else                         // authentication was successful
  {
  $tmp = session_id();       // session is already started
  $session_OK = 1;
  }

if($CDASH_USER_CREATE_PROJECTS && isset($_SESSION['cdash']))
  {
  $_SESSION['cdash']['user_can_create_project']=1;
  }

// If we should use the local/prelogin.php
if(file_exists("local/prelogin.php"))
  {
  include("local/prelogin.php");
  }
?>
