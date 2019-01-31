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
use Illuminate\Support\Collection;

include dirname(__DIR__) . '/config/config.php';
include_once 'include/common.php';
require_once 'include/pdo.php';
include_once 'include/version.php';
include_once 'include/login_functions.php';

$config = Config::getInstance();

// --------------------------------------------------------------------------------------
// main
// --------------------------------------------------------------------------------------
// $mysession = ['login' => false, 'passwd' => false, 'ID' => false, 'valid' => false, 'langage' => false];

$session_OK = (int)cdash_auth();
if (!$session_OK && !@$noforcelogin) {
    $errors = Collection::make([]);
    echo view(
        'auth.login',
        [
            'title' => 'Login',
            'errors' => $errors,
        ]
    )->render();
    return;
}
/*
if (!cdash_auth() && !@$noforcelogin) {
    // authentication failed

    $csrfToken = null;
    if ($session->exists()) {
        $session->destroy();
        // Re-use any existing csrf token.  This prevents token mismatch
        // when this page gets included multiple times during an authentication
        // session.
        $csrfToken = $session->getSessionVar('cdash.csrfToken');
    }

    if (is_null($csrfToken)) {
        // Generate a new random csrf token.
        // This is used by Google OAuth2 to prevent forged logins.
        $csrfToken = bin2hex(random_bytes(16));
    }

    // TODO: exists to satisfy a couple of tests and should be removed with extreme prejudice asap
    if (!isset($SessionCachePolicy)) {
        $SessionCachePolicy = Session::CACHE_PRIVATE_NO_EXPIRE;
    }

    $session->start($SessionCachePolicy);
    $session->setSessionVar('cdash', ['csrfToken' => $csrfToken]);
    LoginForm($loginerror); // display login form
    $session_OK = 0;
} else {
    // authentication was successful
    $session->regenerateId();
    $session_OK = 1;

    // Check if we should be redirecting the user to another page.
    // This happens when they have to change their password because it expired.
    $destination = $session->getSessionVar('cdash.redirect');
    if ($destination) {
        $dest_page = substr($destination, strrpos($destination, '/') + 1);
        $pos = strpos($dest_page, '?');
        if ($pos !== false) {
            $dest_page = substr($dest_page, 0, $pos);
        }
        $redirect_from_here = true;
        $redirect_from_api = false;
        // Examine where we're coming from before redirecting.
        foreach (get_included_files() as $included_file) {
            if (strpos($included_file, $dest_page) !== false) {
                // Avoid infinite redirection if we're already on the
                // right page.
                $redirect_from_here = false;
                break;
            }
            if (strpos($included_file, '/api/') !== false) {
                // If this is an API endpoint, record & return the page that
                // we should go to.  The controller will handle the actual
                // redirection.
                $redirect_from_here = false;
                $redirect_from_api = true;
            }
        }
        if ($redirect_from_here) {
            header("Location: $destination");
            exit();
        }
        if ($redirect_from_api) {
            $response = array();
            $response['redirect'] = $destination;
            echo json_encode($response);
            exit();
        }
    }
}


if ($config->get('CDASH_USER_CREATE_PROJECTS') && isset($_SESSION['cdash'])) {
    // TODO: Use Laravel to set this value
    // TODO: But first understand why this is set in the session, why here? Why now?
    $_SESSION['cdash']['user_can_create_project'] = 1;
}
*/

// Abusing the Config singleton to get rid of globals.
$config->set('session_OK', $session_OK);
