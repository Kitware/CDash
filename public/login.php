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
include(dirname(__DIR__)."/config/config.php");
include_once("include/common.php");
require_once("include/pdo.php");
include_once("include/version.php");
include_once('include/login_functions.php');

$loginerror = "";

// --------------------------------------------------------------------------------------
// main
// --------------------------------------------------------------------------------------
$mysession = array("login"=>false, "passwd"=>false, "ID"=>false, "valid"=>false, "langage"=>false);
$uri = basename($_SERVER['PHP_SELF']);
$stamp = md5(random_bytes(8)); // Use 8 bytes of randomness, unsure if $stamp is used?
$session_OK = 0;

if (!auth(@$SessionCachePolicy) && !@$noforcelogin) {
    // authentication failed

    // Create a session with a random "state" value.
    // This is used by Google OAuth2 to prevent forged logins.
    if (session_id() != '') {
        session_destroy();
    }
    session_name("CDash");
    session_cache_limiter(@$SessionCachePolicy);
    session_set_cookie_params($CDASH_COOKIE_EXPIRATION_TIME);
    @ini_set('session.gc_maxlifetime', $CDASH_COOKIE_EXPIRATION_TIME+600);
    session_start();
    $sessionArray = array("state" => md5(rand()));
    $_SESSION['cdash'] = $sessionArray;
    LoginForm($loginerror); // display login form
    $session_OK=0;
} else {
    // authentication was successful
    session_regenerate_id();
    $session_OK = 1;
}

if ($CDASH_USER_CREATE_PROJECTS && isset($_SESSION['cdash'])) {
    $_SESSION['cdash']['user_can_create_project']=1;
}

// If we should use the local/prelogin.php
if (file_exists("local/prelogin.php")) {
    include("local/prelogin.php");
}
