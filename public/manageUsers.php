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
include 'public/login.php';
require_once 'include/common.php';
require_once 'include/version.php';
require_once 'models/user.php';

if ($session_OK) {
    $userid = $_SESSION['cdash']['loginid'];
    // Checks
    if (!isset($userid) || !is_numeric($userid) || $userid < 1) {
        echo 'Not a valid usersessionid!';
        return;
    }

    $current_user = new User();
    $current_user->Id = $userid;

    if (!$current_user->IsAdmin()) {
        echo "You don't have the permissions to access this page!";
        return;
    }

    $xml = begin_XML_for_XSLT();
    $xml .= '<backurl>user.php</backurl>';
    $xml .= '<title>CDash - Manage Users</title>';
    $xml .= '<menutitle>CDash</menutitle>';
    $xml .= '<menusubtitle>Manage Users</menusubtitle>';

    @$postuserid = $_POST['userid'];
    if ($postuserid != null && $postuserid > 0) {
        $post_user = new User();
        $post_user->Id = $postuserid;
        $post_user->Fill();
    }

    if (isset($_POST['adduser'])) {
        // arrive from register form
        $email = $_POST['email'];
        $passwd = $_POST['passwd'];
        $passwd2 = $_POST['passwd2'];
        if (!($passwd == $passwd2)) {
            $xml .= add_XML_value('error', 'Passwords do not match!');
        } else {
            $fname = $_POST['fname'];
            $lname = $_POST['lname'];
            $institution = $_POST['institution'];
            if ($email && $passwd && $passwd2 && $fname && $lname && $institution) {
                $new_user = new User();
                if ($new_user->GetIdFromEmail($email)) {
                    $xml .= add_XML_value('error', 'Email already registered!');
                } else {
                    $passwordHash = User::PasswordHash($passwd);
                    if ($passwordHash === false) {
                        $xml .= add_XML_value('error', 'Failed to hash password');
                    } else {
                        $new_user->Email = $email;
                        $new_user->Password = $passwordHash;
                        $new_user->FirstName = $fname;
                        $new_user->LastName = $lname;
                        $new_user->Institution = $institution;
                        if ($new_user->Save()) {
                            $xml .= add_XML_value('warning', 'User ' . $email . ' added successfully with password:' . $passwd);
                        } else {
                            $xml .= add_XML_value('error', 'Cannot add user');
                        }
                    }
                }
            } else {
                $xml .= add_XML_value('error', 'Please fill in all of the required fields');
            }
        }
    } elseif (isset($_POST['makenormaluser'])) {
        if ($postuserid > 1) {
            $post_user->Admin = 0;
            $post_user->Save();
            $xml .= "<warning>$post_user->FirstName $post_user->LastName is not administrator anymore.</warning>";
        } else {
            $xml .= '<error>Administrator should remain admin.</error>';
        }
    } elseif (isset($_POST['makeadmin'])) {
        $post_user->Admin = 1;
        $post_user->Save();
        $xml .= "<warning>$post_user->FirstName $post_user->LastName is now an administrator.</warning>";
    } elseif (isset($_POST['removeuser'])) {
        $name = $post_user->GetName();
        $post_user->Delete();
        $xml .= "<warning>$name has been removed.</warning>";
    }

    if (isset($_POST['search'])) {
        $xml .= '<search>' . $_POST['search'] . '</search>';
    }

    if (isset($CDASH_FULL_EMAIL_WHEN_ADDING_USER) && $CDASH_FULL_EMAIL_WHEN_ADDING_USER == 1) {
        $xml .= add_XML_value('fullemail', '1');
    }

    $xml .= '</cdash>';

// Now doing the xslt transition
    generate_XSLT($xml, 'manageUsers');
}
