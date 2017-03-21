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
require dirname(__DIR__) . '/vendor/autoload.php';
include_once 'include/common.php';
require_once 'include/pdo.php';
require_once 'models/user.php';

function getGoogleAuthenticateState()
{
    $requiredFields = array('csrfToken', 'rememberMe', 'requestedURI');

    if (!isset($_GET['state'])) {
        add_log('no state value passed via GET', 'getGoogleAuthenticateState', LOG_ERR);
        return false;
    }

    $state = json_decode($_GET['state']);

    if ($state === null) {
        add_log('Invalid state value passed via GET', 'getGoogleAuthenticateState', LOG_ERR);
        return false;
    }

    foreach ($requiredFields as $requiredField) {
        if (!array_key_exists($requiredField, $state)) {
            add_log('State expected ' . $requiredField, 'getGoogleAuthenticateState', LOG_ERR);
            return false;
        }
    }

    // don't send the user back to login.php if that's where they came from
    if (strpos($state->requestedURI, 'login.php') !== false) {
        $requestedURI = 'user.php';
    }

    return $state;
}

/** Google authentication */
function googleAuthenticate($code)
{
    $state = getGoogleAuthenticateState();
    if ($state === false) {
        return;
    }

    include dirname(__DIR__) . '/config/config.php';
    global $CDASH_DB_HOST, $CDASH_DB_LOGIN, $CDASH_DB_PASS, $CDASH_DB_NAME;
    $SessionCachePolicy = 'private_no_expire';

    // initialize the session
    session_name('CDash');
    session_cache_limiter($SessionCachePolicy);
    session_set_cookie_params($CDASH_COOKIE_EXPIRATION_TIME);
    @ini_set('session.gc_maxlifetime', $CDASH_COOKIE_EXPIRATION_TIME + 600);
    session_start();

    // check that the anti-forgery token is valid
    if ($state->csrfToken != $_SESSION['cdash']['csrfToken']) {
        add_log('state anti-forgery token mismatch: ' . $state->csrfToken .
                ' vs ' . $_SESSION['cdash']['csrfToken'], 'googleAuthenticate', LOG_ERR);
        return;
    }

    $redirectURI = strtok(get_server_URI(false), '?');
    // The return value of get_server_URI can be inconsistent.
    // It simply returns $CDASH_BASE_URL if that variable is set, yielding a
    // return value like http://mydomain.com/CDash.
    // If this variable is not set, then it will return the full URI including
    // the current script, ie
    // http://mydomain.com/CDash/googleauth_callback.php.
    //
    // Make sure that redirectURI contains the path to our callback script.
    if (strpos($redirectURI, 'googleauth_callback.php') === false) {
        $redirectURI .= '/googleauth_callback.php';
    }

    try {
        $client = new Google_Client();
        $client->setClientId($GOOGLE_CLIENT_ID);
        $client->setClientSecret($GOOGLE_CLIENT_SECRET);
        $client->setRedirectUri($redirectURI);
        $client->authenticate($_GET['code']);

        $oauth = new Google_Service_Oauth2($client);
        $me = $oauth->userinfo->get();
        $tokenResponse = json_decode($client->getAccessToken());
    } catch (Google_Auth_Exception $e) {
        add_log('Google access token request failed: ' . $e->getMessage(),
                'googleAuthenticate', LOG_ERR);
        return;
    }

    // Check if this email address appears in our user database
    $email = strtolower($me->getEmail());

    $user = new User();
    $userid = $user->GetIdFromEmail($email);
    if (!$userid) {
        // if no match is found, redirect to pre-filled out registration page
        pdo_free_result($result);
        $firstname = $me->getGivenName();
        $lastname = $me->getFamilyName();
        header("Location: register.php?firstname=$firstname&lastname=$lastname&email=$email");
        return false;
    }

    $user->Id = $userid;
    $user->Fill();

    if ($state->rememberMe) {
        require_once 'include/login_functions.php';
        setRememberMeCookie($user->Id);
    }

    $sessionArray = array(
        'login' => $email,
        'passwd' => $user->Password,
        'ID' => session_id(),
        'valid' => 1,
        'loginid' => $user->Id);
    $_SESSION['cdash'] = $sessionArray;
    session_write_close();
    pdo_free_result($result);
    header("Location: $state->requestedURI");
    return true;                               // authentication succeeded
}

// Google account login entry point
if (isset($_GET['code'])) {
    return googleAuthenticate($_GET['code']);
}
