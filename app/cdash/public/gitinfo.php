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

use CDash\Config;

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

if (Auth::check()) {
    $user = Auth::user();
    if ($user->admin) {
        echo_git_output('--version');
        echo_git_output('remote -v');
        echo_git_output('status');
        echo_git_output('diff');

        $config = Config::getInstance();
        echo_file_contents($config->get('CDASH_ROOT_DIR') . '../../.env');
        echo_file_contents($config->get('CDASH_ROOT_DIR') . '/tests/config.test.local.php');
        echo '<br/>';
    } else {
        echo 'Admin login required to display git info.';
    }
}
