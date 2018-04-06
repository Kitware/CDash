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

use CDash\Controller\Auth\Session;
use CDash\ServiceContainer;

include dirname(__DIR__) . '/config/config.php';
include_once 'include/common.php';
require_once 'include/pdo.php';
include_once 'include/version.php';
include_once 'include/login_functions.php';

$loginerror = '';

// --------------------------------------------------------------------------------------
// main
// --------------------------------------------------------------------------------------
$mysession = ['login' => false, 'passwd' => false, 'ID' => false, 'valid' => false, 'langage' => false];
$uri = basename($_SERVER['PHP_SELF']);
$session_OK = 0;
$service = ServiceContainer::getInstance();
/** @var Session $session */
$session = $service->get(Session::class);

if (!auth(@$SessionCachePolicy) && !@$noforcelogin) {
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

if ($CDASH_USER_CREATE_PROJECTS && isset($_SESSION['cdash'])) {
    $_SESSION['cdash']['user_can_create_project'] = 1;
}

// If we should use the local/prelogin.php
if (file_exists('local/prelogin.php')) {
    include 'local/prelogin.php';
}
