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

include dirname(__DIR__) . "/config/config.php";
include_once "include/common.php";
require_once "include/pdo.php";

/** Google authentication */
function googleAuthenticate($code)
{
    include dirname(__DIR__) . "/config/config.php";
    global $CDASH_DB_HOST, $CDASH_DB_LOGIN, $CDASH_DB_PASS, $CDASH_DB_NAME;
    $SessionCachePolicy = 'private_no_expire';

    // initialize the session
    session_name("CDash");
    session_cache_limiter($SessionCachePolicy);
    session_set_cookie_params($CDASH_COOKIE_EXPIRATION_TIME);
    @ini_set('session.gc_maxlifetime', $CDASH_COOKIE_EXPIRATION_TIME + 600);
    session_start();

    if (!isset($_GET["state"])) {
        add_log("no state value passed via GET", LOG_ERR);
        return;
    }

    // Both the anti-forgery token and the user's requested URL are specified
    // in the same "state" GET parameter.  Split them out here.
    $splitState = explode("_AND_STATE_IS_", $_GET["state"]);
    if (sizeof($splitState) != 2) {
        add_log("Expected two values after splitting state parameter, found " .
            sizeof($splitState), LOG_ERR);
        return;
    }
    $requestedURI = $splitState[0];
    @$state = $splitState[1];

    // don't send the user back to login.php if that's where they came from
    if (strpos($requestedURI, "login.php") !== false) {
        $requestedURI = "user.php";
    }

    // check that the anti-forgery token is valid
    if ($state != $_SESSION['cdash']['state']) {
        add_log("state anti-forgery token mismatch: " . $state .
            " vs " . $_SESSION['cdash']['state'], LOG_ERR);
        return;
    }

    // Request the access token
    $headers = array(
        'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
        'Connection: Keep-Alive'
    );

    $redirectURI = strtok(get_server_URI(false), '?');
    // The return value of get_server_URI can be inconsistent.
    // It simply returns $CDASH_BASE_URL if that variable is set, yielding a
    // return value like http://mydomain.com/CDash.
    // If this variable is not set, then it will return the full URI including
    // the current script, ie
    // http://mydomain.com/CDash/googleauth_callback.php.
    //
    // Make sure that redirectURI contains the path to our callback script.
    if (strpos($redirectURI, "googleauth_callback.php") === false) {
        $redirectURI .= '/googleauth_callback.php';
    }

    $postData = implode('&', array(
        'grant_type=authorization_code',
        'code=' . $_GET["code"],
        'client_id=' . $GOOGLE_CLIENT_ID,
        'client_secret=' . $GOOGLE_CLIENT_SECRET,
        'redirect_uri=' . $redirectURI
    ));

    $url = 'https://accounts.google.com/o/oauth2/token';
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_PORT, 443);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    $resp = curl_exec($curl);

    $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($httpStatus != 200) {
        add_log("Google access token request failed: $resp", LOG_ERR);
        return;
    }

    $resp = json_decode($resp);
    $accessToken = $resp->access_token;
    $tokenType = $resp->token_type;

    // Use the access token to get the user's email address
    $headers = array(
        'Authorization: ' . $tokenType . ' ' . $accessToken
    );
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, 'https://www.googleapis.com/plus/v1/people/me');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_PORT, 443);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    $resp = curl_exec($curl);

    $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($httpStatus != 200) {
        add_log("Get Google user email address request failed: $resp", LOG_ERR);
        return;
    }

    // Extract the user's email address from the response.
    $resp = json_decode($resp);
    $email = strtolower($resp->emails[0]->value);

    // Check if this email address appears in our user database
    $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
    pdo_select_db("$CDASH_DB_NAME", $db);
    $sql = "SELECT id,password FROM " . qid("user") . " WHERE email='" . pdo_real_escape_string($email) . "'";
    $result = pdo_query("$sql");

    if (pdo_num_rows($result) == 0) {
        // if no match is found, redirect to pre-filled out registration page
        pdo_free_result($result);
        $firstname = $resp->name->givenName;
        $lastname = $resp->name->familyName;
        header("Location: register.php?firstname=$firstname&lastname=$lastname&email=$email");
        return false;
    }

    $user_array = pdo_fetch_array($result);
    $pass = $user_array["password"];

    $sessionArray = array(
        "login" => $email,
        "passwd" => $user_array['password'],
        "ID" => session_id(),
        "valid" => 1,
        "loginid" => $user_array["id"]);
    $_SESSION['cdash'] = $sessionArray;
    session_write_close();
    pdo_free_result($result);
    header("Location: $requestedURI");
    return true;                               // authentication succeeded
}

// Google account login entry point
if (isset($_GET["code"])) {
    return googleAuthenticate($_GET["code"]);
}
