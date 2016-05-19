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
include_once 'include/common.php';
require_once 'include/pdo.php';
include_once 'include/version.php';
include_once 'include/login_functions.php';

$loginerror = '';

// --------------------------------------------------------------------------------------
// main
// --------------------------------------------------------------------------------------
$mysession = array('login' => false, 'passwd' => false, 'ID' => false, 'valid' => false, 'langage' => false);
$uri = basename($_SERVER['PHP_SELF']);
$session_OK = 0;

if (!auth(@$SessionCachePolicy) && !@$noforcelogin) {
    // authentication failed


    $csrfToken = null;
    if (session_id() != '') {
        session_destroy();
        // Re-use any existing csrf token.  This prevents token mismatch
        // when this page gets included multiple times during an authentication
        // session.
        if (!empty($_SESSION['cdash']) && !empty($_SESSION['cdash']['csrfToken'])) {
            $csrfToken = $_SESSION['cdash']['csrfToken'];
        }
    }

    if (is_null($csrfToken)) {
        // Generate a new random csrf token.
        // This is used by Google OAuth2 to prevent forged logins.
        $csrfToken = bin2hex(random_bytes(16));
    }

    session_name('CDash');
    session_cache_limiter(@$SessionCachePolicy);
    session_set_cookie_params($CDASH_COOKIE_EXPIRATION_TIME);
    @ini_set('session.gc_maxlifetime', $CDASH_COOKIE_EXPIRATION_TIME + 600);
    session_start();
    $sessionArray = array('csrfToken' => $csrfToken);

    $_SESSION['cdash'] = $sessionArray;
    LoginForm($loginerror); // display login form
    $session_OK = 0;
} else {
    // authentication was successful
    session_regenerate_id();
    $session_OK = 1;

    // Check if we should be redirecting the user to another page.
    // This happens when they have to change their password because it expired.
    if (isset($_SESSION['cdash']) &&
            array_key_exists('redirect', $_SESSION['cdash']) &&
            !empty($_SESSION['cdash']['redirect'])) {
        $destination = $_SESSION['cdash']['redirect'];

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
