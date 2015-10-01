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

include_once("cdash/common.php");
include_once('cdash/version.php');
redirect_to_https();

include_once("cdash/config.php");
require_once("cdash/cdashmail.php");

$reg = "";

/** Authentication function */
function register()
{
    global $reg;
    include("cdash/config.php");
    require_once("cdash/pdo.php");

    if (isset($_GET["key"])) {
        $key = pdo_real_escape_string($_GET["key"]);
        $sql = "SELECT * FROM ".qid("usertemp")." WHERE registrationkey='$key'";
        $query = pdo_query($sql);
        if (pdo_num_rows($query) == 0) {
            $reg = "The key is invalid.";
            return 0;
        }

        $query_array = pdo_fetch_array($query);

        $email = $query_array['email'];
        $passwd = $query_array['password'];
        $fname = $query_array['firstname'];
        $lname = $query_array['lastname'];
        $institution = $query_array['institution'];

    // We copy the data from usertemp to user
    $sql="INSERT INTO ".qid("user")." (email,password,firstname,lastname,institution)
          VALUES ('$email','$passwd','$fname','$lname','$institution')";

        if (pdo_query($sql)) {
            pdo_query("DELETE FROM usertemp WHERE email='".$email."'");
            return 1;
        } else {
            $reg = pdo_error();
            return 0;
        }
    } elseif (isset($_POST["sent"])) {
        // arrive from register form

    $url   = $_POST["url"];
        if ($url != "catchbot") {
            $reg = "Bots are not allowed to obtain CDash accounts!";
            return 0;
        }
        $email = $_POST["email"];
        $passwd = $_POST["passwd"];
        $passwd2 = $_POST["passwd2"];
        if (!($passwd == $passwd2)) {
            $reg = "Passwords do not match!";
            return 0;
        }
        $fname = $_POST["fname"];
        $lname = $_POST["lname"];
        $institution = $_POST["institution"];
        if ($email && $passwd && $passwd2 && $fname && $lname && $institution) {
            $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
            pdo_select_db("$CDASH_DB_NAME", $db);
            $passwd = md5($passwd);
            $email = pdo_real_escape_string($email);

            $sql = "SELECT email FROM ".qid("user")." WHERE email='$email'";
            if (pdo_num_rows(pdo_query($sql)) > 0) {
                $reg = "$email is already registered.";
                return 0;
            }
            $sql = "SELECT email  FROM ".qid("usertemp")." WHERE email='$email'";
            if (pdo_num_rows(pdo_query($sql)) > 0) {
                $reg = "$email is already registered. Check your email if you haven't received the link to activate yet.";
                return 0;
            }

            $passwd = pdo_real_escape_string($passwd);
            $fname = pdo_real_escape_string($fname);
            $lname = pdo_real_escape_string($lname);
            $institution = pdo_real_escape_string($institution);

            if ($CDASH_REGISTRATION_EMAIL_VERIFY) {
                // Create a key
        srand(microtime_float());

                $keychars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
                $length = 40;

                $key = "";
                $max=strlen($keychars)-1;
                for ($i=0;$i<$length;$i++) {
                    $key .= substr($keychars, rand(0, $max), 1);
                }

                $date = date(FMT_DATETIME);
                $sql="INSERT INTO ".qid("usertemp")." (email,password,firstname,lastname,institution,registrationkey,registrationdate)
              VALUES ('$email','$passwd','$fname','$lname','$institution','$key','$date')";
            } else {
                $sql="INSERT INTO ".qid("user")." (email,password,firstname,lastname,institution)
              VALUES ('$email','$passwd','$fname','$lname','$institution')";
            }
            if (pdo_query($sql)) {
                if ($CDASH_REGISTRATION_EMAIL_VERIFY) {
                    $currentURI = get_server_URI();

          // Send the email
          $emailtitle = "Welcome to CDash!";
                    $emailbody = "Hello ".$fname.",\n\n";
                    $emailbody .= "Welcome to CDash! In order to validate your registration please follow this link: \n";
                    $emailbody .= $currentURI."/register.php?key=".$key."\n";

                    $serverName = $CDASH_SERVER_NAME;
                    if (strlen($serverName) == 0) {
                        $serverName = $_SERVER['SERVER_NAME'];
                    }
                    $emailbody .= "\n-CDash on ".$serverName."\n";

                    if (cdashmail("$email", $emailtitle, $emailbody,
          "From: CDash <".$CDASH_EMAIL_FROM.">\nReply-To: ".$CDASH_EMAIL_REPLY."\nContent-type: text/plain; charset=utf-8\nX-Mailer: PHP/" . phpversion()."\nMIME-Version: 1.0")) {
                        add_log("email sent to: ".$email, "Registration");
                    } else {
                        add_log("cannot send email to: ".$email, "Registration", LOG_ERR);
                    }

                    $reg = "A confirmation email has been sent. Check your email (including your spam folder) to confirm your registration!\n";
                    $reg .= "You need to activate your account within 24 hours.";
                    return 0;
                }
                return 1;
            } else {
                $reg = pdo_error();
                return 0;
            }
        } else {
            $reg = "Please fill in all of the required fields";
            return 0;
        }
    } // end register

  return 0;
}

/** Login Form function */
function RegisterForm($regerror)
{
    include("cdash/config.php");
    require_once("cdash/pdo.php");
    include_once("cdash/common.php");

    if (isset($CDASH_NO_REGISTRATION) && $CDASH_NO_REGISTRATION==1) {
        die("You cannot access this page. Contact your administrator if you think that's an error.");
    }

    $xml = begin_XML_for_XSLT();
    $xml .= "<title>CDash - Registration</title>";
    $xml .= "<error>" . $regerror . "</error>";
    if (isset($_GET["firstname"])) {
        $xml .= "<firstname>" . $_GET["firstname"] . "</firstname>";
    } else {
        $xml .= "<firstname></firstname>";
    }
    if (isset($_GET["lastname"])) {
        $xml .= "<lastname>" . $_GET["lastname"] . "</lastname>";
    } else {
        $xml .= "<lastname></lastname>";
    }
    if (isset($_GET["email"])) {
        $xml .= "<email>" . $_GET["email"] . "</email>";
    } else {
        $xml .= "<email></email>";
    }
    $xml .= "</cdash>";

    generate_XSLT($xml, "register");
}

// --------------------------------------------------------------------------------------
// main
// --------------------------------------------------------------------------------------
if (!register()) {                 // registration failed
  RegisterForm($reg);
}    // display register form
else {
    header('location: user.php?note=register');
}
