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
require_once 'include/version.php';

use CDash\Config;
use CDash\Model\User;
use CDash\Model\UserProject;

$config = Config::getInstance();

if ($session_OK) {
    require_once 'include/common.php';

    $xml = begin_XML_for_XSLT();
    $xml .= '<title>CDash - My Profile</title>';
    $xml .= '<backurl>user.php</backurl>';
    $xml .= '<title>CDash - My Profile</title>';
    $xml .= '<menutitle>CDash</menutitle>';
    $xml .= '<menusubtitle>My Profile</menusubtitle>';

    $userid = $_SESSION['cdash']['loginid'];
    $user = new User();
    $user->Id = $userid;
    $user->Fill();

    $pdo = get_link_identifier()->getPdo();

    @$updateprofile = $_POST['updateprofile'];
    if ($updateprofile) {
        $email = $_POST['email'];
        if (strlen($email) < 3 || strpos($email, '@') === false) {
            $xml .= '<error>Email should be a valid address.</error>';
        } else {
            $user->Email = $email;
            $user->Institution = $_POST['institution'];
            $user->LastName = $_POST['lname'];
            $user->FirstName = $_POST['fname'];
            if ($user->Save()) {
                $xml .= '<error>Your profile has been updated.</error>';
            } else {
                $xml .= '<error>Cannot update profile.</error>';
            }
        }
    }

    // Update the password
    @$updatepassword = $_POST['updatepassword'];
    if ($updatepassword) {
        $oldpasswd = $_POST['oldpasswd'];
        $passwd = $_POST['passwd'];
        $passwd2 = $_POST['passwd2'];

        $password_is_good = true;
        $error_msg = '';

        if (!password_verify($oldpasswd, $user->Password) && md5($oldpasswd) != $user->Password) {
            $password_is_good = false;
            $error_msg = 'Your old password is incorrect.';
        }

        if ($password_is_good && $passwd != $passwd2) {
            $password_is_good = false;
            $error_msg = 'Passwords do not match.';
        }

        $minimum_length = $config->get('CDASH_MINIMUM_PASSWORD_LENGTH');
        if ($password_is_good && strlen($passwd) < $minimum_length) {
            $password_is_good = false;
            $error_msg = "Password must be at least $minimum_length characters.";
        }

        $password_hash = User::PasswordHash($passwd);
        if ($password_hash === false) {
            $password_is_good = false;
            $error_msg = 'Failed to hash password.  Contact an admin.';
        }

        if ($password_is_good && $config->get('CDASH_PASSWORD_EXPIRATION') > 0) {
            $query = 'SELECT password FROM password WHERE userid=?';
            $unique_count = $config->get('CDASH_UNIQUE_PASSWORD_COUNT');
            if ($unique_count) {
                $query .= " ORDER BY date DESC LIMIT $unique_count";
            }
            $stmt = $pdo->prepare($query);
            pdo_execute($stmt, [$userid]);
            while ($row = $stmt->fetch()) {
                if (password_verify($passwd, $row['password'])) {
                    $password_is_good = false;
                    $error_msg = 'You have recently used this password.  Please select a new one.';
                    break;
                }
            }
        }

        if ($password_is_good) {
            $complexity = getPasswordComplexity($passwd);
            $minimum_complexity = $config->get('CDASH_MINIMUM_PASSWORD_COMPLEXITY');
            $complexity_count = $config->get('CDASH_PASSWORD_COMPLEXITY_COUNT');
            if ($complexity < $minimum_complexity) {
                $password_is_good = false;
                if ($complexity_count > 1) {
                    $error_msg = "Your password must contain at least $complexity_count characters from $minimum_complexity of the following types: uppercase, lowercase, numbers, and symbols.";
                } else {
                    $error_msg = "Your password must contain at least $minimum_complexity of the following: uppercase, lowercase, numbers, and symbols.";
                }
            }
        }

        if (!$password_is_good) {
            $xml .= "<error>$error_msg</error>";
        } else {
            $user->Password = $password_hash;
            if ($user->Save()) {
                $xml .= '<error>Your password has been updated.</error>';
                unset($_SESSION['cdash']['redirect']);
            } else {
                $xml .= '<error>Cannot update password.</error>';
            }
        }
    }

    $xml .= '<user>';
    $xml .= add_XML_value('id', $userid);
    $xml .= add_XML_value('firstname', $user->FirstName);
    $xml .= add_XML_value('lastname', $user->LastName);
    $xml .= add_XML_value('email', $user->Email);
    $xml .= add_XML_value('institution', $user->Institution);

    // Update the credentials
    @$updatecredentials = $_POST['updatecredentials'];
    if ($updatecredentials) {
        $credentials = $_POST['credentials'];
        $UserProject = new UserProject();
        $UserProject->ProjectId = 0;
        $UserProject->UserId = $userid;
        $credentials[] = $user->Email;
        $UserProject->UpdateCredentials($credentials);
    }

    // List the credentials
    // First the email one (which cannot be changed)
    $stmt = $pdo->prepare(
        'SELECT credential FROM user2repository
        WHERE userid = :userid AND projectid = 0 AND credential = :credential');
    $stmt->bindParam(':userid', $userid);
    $stmt->bindParam(':credential', $user->Email);
    pdo_execute($stmt);
    $row = $stmt->fetch();
    if (!$row) {
        $xml .= add_XML_value('credential_0', 'Not found (you should really add it)');
    } else {
        $xml .= add_XML_value('credential_0', $user->Email);
    }

    $stmt = $pdo->prepare(
        'SELECT credential FROM user2repository
        WHERE userid = :userid AND projectid = 0 AND credential != :credential');
    $stmt->bindParam(':userid', $userid);
    $stmt->bindParam(':credential', $user->Email);
    pdo_execute($stmt);
    $credential_num = 1;
    while ($row = $stmt->fetch()) {
        $xml .= add_XML_value('credential_' . $credential_num++, stripslashes($row['credential']));
    }

    $xml .= '</user>';

    if (array_key_exists('reason', $_GET) && $_GET['reason'] == 'expired') {
        $xml .= '<error>Your password has expired.  Please set a new one.</error>';
    }

    $xml .= '</cdash>';

    generate_XSLT($xml, 'editUser');
}
