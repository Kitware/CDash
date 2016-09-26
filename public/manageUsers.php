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
include_once 'include/common.php';
include 'include/version.php';
include 'models/user.php';

if ($session_OK) {
    $userid = $_SESSION['cdash']['loginid'];
    // Checks
    if (!isset($userid) || !is_numeric($userid)) {
        echo 'Not a valid usersessionid!';
        return;
    }

    $user_array = pdo_fetch_array(pdo_query('SELECT admin FROM ' . qid('user') . " WHERE id='$userid'"));

    if ($user_array['admin'] != 1) {
        echo "You don't have the permissions to access this page!";
        return;
    }

    $xml = begin_XML_for_XSLT();
    $xml .= '<backurl>user.php</backurl>';
    $xml .= '<title>CDash - Manage Users</title>';
    $xml .= '<menutitle>CDash</menutitle>';
    $xml .= '<menusubtitle>Manage Users</menusubtitle>';

    @$postuserid = $_POST['userid'];
    if ($postuserid != null) {
        $postuserid = pdo_real_escape_numeric($postuserid);
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
                $User = new User();
                if ($User->GetIdFromEmail($email)) {
                    $xml .= add_XML_value('error', 'Email already registered!');
                } else {
                    $passwdencryted = md5($passwd);
                    $User->Email = $email;
                    $User->Password = $passwdencryted;
                    $User->FirstName = $fname;
                    $User->LastName = $lname;
                    $User->Institution = $institution;
                    if ($User->Save()) {
                        $xml .= add_XML_value('warning', 'User ' . $email . ' added successfully with password:' . $passwd);
                    } else {
                        $xml .= add_XML_value('error', 'Cannot add user');
                    }
                }
            } else {
                $xml .= add_XML_value('error', 'Please fill in all of the required fields');
            }
        }
    } elseif (isset($_POST['makenormaluser'])) {
        if ($postuserid > 1) {
            $update_array = pdo_fetch_array(pdo_query('SELECT firstname,lastname FROM ' . qid('user') . " WHERE id='" . $postuserid . "'"));
            pdo_query('UPDATE ' . qid('user') . " SET admin=0 WHERE id='" . $postuserid . "'");
            $xml .= '<warning>' . $update_array['firstname'] . ' ' . $update_array['lastname'] . ' is not administrator anymore.</warning>';
        } else {
            $xml .= '<error>Administrator should remain admin.</error>';
        }
    } elseif (isset($_POST['makeadmin'])) {
        $update_array = pdo_fetch_array(pdo_query('SELECT firstname,lastname FROM ' . qid('user') . " WHERE id='" . $postuserid . "'"));
        pdo_query('UPDATE ' . qid('user') . " SET admin=1 WHERE id='" . $postuserid . "'");
        $xml .= '<warning>' . $update_array['firstname'] . ' ' . $update_array['lastname'] . ' is now an administrator.</warning>';
    } elseif (isset($_POST['removeuser'])) {
        $user = new User();
        $user->Id = $postuserid;
        $user->Fill();
        $name = $user->GetName();
        $user->Delete();
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
