<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

include dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';
require_once 'include/login_functions.php';
include 'public/login.php';
include 'include/version.php';
include_once 'models/user.php';
include_once 'models/userproject.php';

if ($session_OK) {
    include_once 'include/common.php';

    $xml = begin_XML_for_XSLT();
    $xml .= '<title>CDash - My Profile</title>';
    $xml .= '<backurl>user.php</backurl>';
    $xml .= '<title>CDash - My Profile</title>';
    $xml .= '<menutitle>CDash</menutitle>';
    $xml .= '<menusubtitle>My Profile</menusubtitle>';

    $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
    pdo_select_db("$CDASH_DB_NAME", $db);

    $userid = $_SESSION['cdash']['loginid'];

    @$updateprofile = $_POST['updateprofile'];
    if ($updateprofile) {
        $institution = pdo_real_escape_string($_POST['institution']);
        $email = pdo_real_escape_string($_POST['email']);

        if (strlen($email) < 3 || strpos($email, '@') === false) {
            $xml .= '<error>Email should be a valid address.</error>';
        } else {
            $lname = pdo_real_escape_string($_POST['lname']);
            $fname = pdo_real_escape_string($_POST['fname']);

            if (pdo_query('UPDATE ' . qid('user') . " SET email='$email',
                        institution='$institution',
                        firstname='$fname',
                        lastname='$lname' WHERE id='$userid'")) {
                $xml .= '<error>Your profile has been updated.</error>';
            } else {
                $xml .= '<error>Cannot update profile.</error>';
            }
            add_last_sql_error('editUser.php');
        }
    }

    // Update the password
    @$updatepassword = $_POST['updatepassword'];
    if ($updatepassword) {
        $passwd = htmlspecialchars(pdo_real_escape_string($_POST['passwd']));
        $passwd2 = htmlspecialchars(pdo_real_escape_string($_POST['passwd2']));

        global $CDASH_MINIMUM_PASSWORD_LENGTH,
               $CDASH_MINIMUM_PASSWORD_COMPLEXITY,
               $CDASH_PASSWORD_COMPLEXITY_COUNT,
               $CDASH_PASSWORD_EXPIRATION,
               $CDASH_UNIQUE_PASSWORD_COUNT;

        $password_is_good = true;
        $error_msg = '';

        if ($passwd != $passwd2) {
            $password_is_good = false;
            $error_msg = 'Passwords do not match.';
        }

        if ($password_is_good && strlen($passwd) < $CDASH_MINIMUM_PASSWORD_LENGTH) {
            $password_is_good = false;
            $error_msg = "Password must be at least $CDASH_MINIMUM_PASSWORD_LENGTH characters.";
        }

        $md5pass = md5($passwd);
        $md5pass = pdo_real_escape_string($md5pass);

        if ($password_is_good && $CDASH_PASSWORD_EXPIRATION > 0) {
            $query = "SELECT password FROM password WHERE userid=$userid";
            if ($CDASH_UNIQUE_PASSWORD_COUNT) {
                $query .= " ORDER BY date DESC LIMIT $CDASH_UNIQUE_PASSWORD_COUNT";
            }
            $result = pdo_query($query);
            while ($row = pdo_fetch_array($result)) {
                if ($md5pass == $row['password']) {
                    $password_is_good = false;
                    $error_msg = 'You have recently used this password.  Please select a new one.';
                    break;
                }
            }
        }

        if ($password_is_good) {
            $complexity = getPasswordComplexity($passwd);
            if ($complexity < $CDASH_MINIMUM_PASSWORD_COMPLEXITY) {
                $password_is_good = false;
                if ($CDASH_PASSWORD_COMPLEXITY_COUNT > 1) {
                    $error_msg = "Your password must contain at least $CDASH_PASSWORD_COMPLEXITY_COUNT characters from $CDASH_MINIMUM_PASSWORD_COMPLEXITY of the following types: uppercase, lowercase, numbers, and symbols.";
                } else {
                    $error_msg = "Your password must contain at least $CDASH_MINIMUM_PASSWORD_COMPLEXITY of the following: uppercase, lowercase, numbers, and symbols.";
                }
            }
        }

        if (!$password_is_good) {
            $xml .= "<error>$error_msg</error>";
        } else {
            $user = new User();
            $user->Id = $userid;
            $user->Fill();
            $user->Password = $md5pass;
            if ($user->Save()) {
                $xml .= '<error>Your password has been updated.</error>';
                unset($_SESSION['cdash']['redirect']);
            } else {
                $xml .= '<error>Cannot update password.</error>';
            }
            add_last_sql_error('editUser.php');
        }
    }

    $xml .= '<user>';
    $user = pdo_query('SELECT * FROM ' . qid('user') . " WHERE id='$userid'");
    $user_array = pdo_fetch_array($user);
    $xml .= add_XML_value('id', $userid);
    $xml .= add_XML_value('firstname', $user_array['firstname']);
    $xml .= add_XML_value('lastname', $user_array['lastname']);
    $xml .= add_XML_value('email', $user_array['email']);
    $xml .= add_XML_value('institution', $user_array['institution']);

    // Update the credentials
    @$updatecredentials = $_POST['updatecredentials'];
    if ($updatecredentials) {
        $credentials = $_POST['credentials'];
        $UserProject = new UserProject();
        $UserProject->ProjectId = 0;
        $UserProject->UserId = $userid;
        $credentials[] = $user_array['email'];
        $UserProject->UpdateCredentials($credentials);
    }

    // List the credentials
    // First the email one (which cannot be changed)
    $credential = pdo_query("SELECT credential FROM user2repository WHERE userid='$userid'
            AND projectid=0 AND credential='" . $user_array['email'] . "'");
    if (pdo_num_rows($credential) == 0) {
        $xml .= add_XML_value('credential_0', 'Not found (you should really add it)');
    } else {
        $xml .= add_XML_value('credential_0', $user_array['email']);
    }

    $credential = pdo_query("SELECT credential FROM user2repository WHERE userid='$userid'
            AND projectid=0 AND credential!='" . $user_array['email'] . "'");
    $credential_num = 1;
    while ($credential_array = pdo_fetch_array($credential)) {
        $xml .= add_XML_value('credential_' . $credential_num++, stripslashes($credential_array['credential']));
    }

    $xml .= '</user>';

    if (array_key_exists('reason', $_GET) && $_GET['reason'] == 'expired') {
        $xml .= '<error>Your password has expired.  Please set a new one.</error>';
    }

    $xml .= '</cdash>';

    generate_XSLT($xml, 'editUser');
}
