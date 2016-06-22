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
require_once 'public/login.php';

function echo_svn_output($cmd)
{
    // Assumes being able to run 'svn' on the web server in the CDash
    // directory...
    //
    $svn_output = `git $cmd`;

    echo '<h3>git ' . $cmd . '</h3>';
    echo '<pre>';
    echo htmlentities($svn_output);
    echo '</pre>';
    echo '<br/>';
}

function echo_file_contents($filename)
{
    // Emit the contents of the named file, but only if it exists.
    // If it doesn't exist, emit nothing.
    //
    if (file_exists($filename)) {
        $contents = file_get_contents($filename);

        echo '<h3>contents of "' . $filename . '"</h3>';
        echo '<pre>';
        echo htmlentities($contents);
        echo '</pre>';
        echo '<br/>';
    }
}

if ($session_OK) {
    $userid = $_SESSION['cdash']['loginid'];

    $user_is_admin = pdo_get_field_value(
        'SELECT admin FROM ' . qid('user') . " WHERE id='$userid'",
        'admin',
        0);

    if ($user_is_admin) {
        echo_svn_output('--version');
        echo_svn_output('remote -v');
        echo_svn_output('status');
        echo_svn_output('diff');

        global $CDASH_ROOT_DIR;
        echo_file_contents($CDASH_ROOT_DIR . '/config/config.local.php');
        echo_file_contents($CDASH_ROOT_DIR . '/tests/config.test.local.php');
        echo '<br/>';
    } else {
        echo 'Admin login required to display svn info.';
    }
}
