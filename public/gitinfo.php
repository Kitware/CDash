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
require_once 'models/user.php';

function echo_git_output($cmd)
{
    // Assumes being able to run 'git' on the web server in the CDash
    // directory...
    //
    $git_output = `git $cmd`;

    echo '<h3>git ' . $cmd . '</h3>';
    echo '<pre>';
    echo htmlentities($git_output);
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

    $user = new User();
    $user->Id = $userid;
    if ($user->IsAdmin()) {
        echo_git_output('--version');
        echo_git_output('remote -v');
        echo_git_output('status');
        echo_git_output('diff');

        global $CDASH_ROOT_DIR;
        echo_file_contents($CDASH_ROOT_DIR . '/config/config.local.php');
        echo_file_contents($CDASH_ROOT_DIR . '/tests/config.test.local.php');
        echo '<br/>';
    } else {
        echo 'Admin login required to display git info.';
    }
}
