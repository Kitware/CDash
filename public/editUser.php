<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id: buildOverview.php 1161 2008-09-19 14:56:14Z jjomier $
  Language:  PHP
  Date:      $Date: 2007-10-16 11:23:29 -0400 (Tue, 16 Oct 2007) $
  Version:   $Revision: 12 $

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include(dirname(__DIR__)."/config/config.php");
require_once("include/pdo.php");
include('public/login.php');
include("include/version.php");
include_once("models/userproject.php");

if ($session_OK) {
    include_once("include/common.php");
  
    $xml = begin_XML_for_XSLT();
    $xml .= "<title>CDash - My Profile</title>";
    $xml .= "<backurl>user.php</backurl>";
    $xml .= "<title>CDash - My Profile</title>";
    $xml .= "<menutitle>CDash</menutitle>";
    $xml .= "<menusubtitle>My Profile</menusubtitle>";
  
    $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
    pdo_select_db("$CDASH_DB_NAME", $db);

    $userid = $_SESSION['cdash']['loginid'];

    @$updateprofile = $_POST["updateprofile"];
    if ($updateprofile) {
        $institution = pdo_real_escape_string($_POST["institution"]);
        $email = pdo_real_escape_string($_POST["email"]);

        if (strlen($email)<3 || strpos($email, "@")===false) {
            $xml .= "<error>Email should be a valid address.</error>";
        } else {
            $lname = pdo_real_escape_string($_POST["lname"]);
            $fname = pdo_real_escape_string($_POST["fname"]);

            if (pdo_query("UPDATE ".qid("user")." SET email='$email',
                   institution='$institution',
                   firstname='$fname',
                   lastname='$lname' WHERE id='$userid'")) {
                $xml .= "<error>Your profile has been updated.</error>";
            } else {
                $xml .= "<error>Cannot update profile.</error>";
            }
            add_last_sql_error("editUser.php");
        }
    }


 // Update the password
 @$updatepassword = $_POST["updatepassword"];
    if ($updatepassword) {
        $passwd = htmlspecialchars(pdo_real_escape_string($_POST["passwd"]));
        $passwd2 = htmlspecialchars(pdo_real_escape_string($_POST["passwd2"]));

        if (strlen($passwd)<5) {
            $xml .= "<error>Password should be at least 5 characters.</error>";
        } elseif ($passwd != $passwd2) {
            $xml .= "<error>Passwords don't match.</error>";
        } else {
            $md5pass = md5($passwd);
            $md5pass = pdo_real_escape_string($md5pass);
            if (pdo_query("UPDATE ".qid("user")." SET password='$md5pass' WHERE id='$userid'")) {
                $xml .= "<error>Your password has been updated.</error>";
            } else {
                $xml .= "<error>Cannot update password.</error>";
            }
   
            add_last_sql_error("editUser.php");
        }
    } // end update password

  $xml .= "<user>";
    $user = pdo_query("SELECT * FROM ".qid("user")." WHERE id='$userid'");
    $user_array = pdo_fetch_array($user);
    $xml .= add_XML_value("id", $userid);
    $xml .= add_XML_value("firstname", $user_array["firstname"]);
    $xml .= add_XML_value("lastname", $user_array["lastname"]);
    $xml .= add_XML_value("email", $user_array["email"]);
    $xml .= add_XML_value("institution", $user_array["institution"]);

  // Update the credentials
 @$updatecredentials = $_POST["updatecredentials"];
    if ($updatecredentials) {
        $credentials = $_POST["credentials"];
        $UserProject = new UserProject();
        $UserProject->ProjectId = 0;
        $UserProject->UserId = $userid;
        $credentials[] = $user_array["email"];
        $UserProject->UpdateCredentials($credentials);
    } // end update credentials


  // List the credentials
  // First the email one (which cannot be changed)
  $credential = pdo_query("SELECT credential FROM user2repository WHERE userid='$userid'
                     AND projectid=0 AND credential='".$user_array["email"]."'");
    if (pdo_num_rows($credential) == 0) {
        $xml .= add_XML_value("credential_0", "Not found (you should really add it)");
    } else {
        $xml .= add_XML_value("credential_0", $user_array["email"]);
    }
  
    $credential = pdo_query("SELECT credential FROM user2repository WHERE userid='$userid'
                           AND projectid=0 AND credential!='".$user_array["email"]."'");
    $credential_num = 1;
    while ($credential_array = pdo_fetch_array($credential)) {
        $xml .= add_XML_value("credential_".$credential_num++, stripslashes($credential_array["credential"]));
    }
    
    $xml .= "</user>";
    $xml .= "</cdash>";
  
    generate_XSLT($xml, "editUser");
} // end session OK;
