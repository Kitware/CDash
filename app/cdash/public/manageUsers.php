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

require_once 'include/pdo.php';
require_once 'include/common.php';

use App\Http\Controllers\Auth\LoginController;
use App\Models\User;
use CDash\Config;

$config = Config::getInstance();

if (Auth::check()) {
    $userid = Auth::id();
    // Checks
    if (!isset($userid) || !is_numeric($userid) || $userid < 1) {
        echo 'Not a valid usersessionid!';
        return;
    }

    $current_user = Auth::user();
    if (!$current_user->admin) {
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
        $post_user = User::find($postuserid);
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
                $new_user = User::where('email', $email)->first();
                if (!is_null($new_user)) {
                    $xml .= add_XML_value('error', 'Email already registered!');
                } else {
                    $new_user = new User();
                    $passwordHash = password_hash($passwd, PASSWORD_DEFAULT);
                    if ($passwordHash === false) {
                        $xml .= add_XML_value('error', 'Failed to hash password');
                    } else {
                        $new_user->email = $email;
                        $new_user->password = $passwordHash;
                        $new_user->firstname = $fname;
                        $new_user->lastname = $lname;
                        $new_user->institution = $institution;
                        if ($new_user->save()) {
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
            $post_user->admin = 0;
            $post_user->save();
            $xml .= "<warning>$post_user->full_name is not administrator anymore.</warning>";
        } else {
            $xml .= '<error>Administrator should remain admin.</error>';
        }
    } elseif (isset($_POST['makeadmin'])) {
        $post_user->admin = 1;
        $post_user->save();
        $xml .= "<warning>$post_user->full_name is now an administrator.</warning>";
    } elseif (isset($_POST['removeuser'])) {
        $name = $post_user->full_name;
        $post_user->delete();
        $xml .= "<warning>$name has been removed.</warning>";
    }

    if (isset($_POST['search'])) {
        $xml .= '<search>' . $_POST['search'] . '</search>';
    }

    if ($config->get('CDASH_FULL_EMAIL_WHEN_ADDING_USER') == 1) {
        $xml .= add_XML_value('fullemail', '1');
    }

    $xml .= '</cdash>';

    // Now doing the xslt transition
    generate_XSLT($xml, 'manageUsers');
} else {
    return LoginController::staticShowLoginForm();
}
