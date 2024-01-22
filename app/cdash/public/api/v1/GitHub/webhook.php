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

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/config.php';
require_once 'include/api_common.php';

use CDash\Model\Repository;

// Adapted from https://gist.github.com/milo/daed6e958ea534e4eba3
if (!function_exists('handle_error')) {
    function handle_error($msg)
    {
        add_log($msg, 'GitHub webhook', LOG_WARNING);
        json_error_response(['error' => $msg], 500);
    }
}

$hookSecret = config('cdash.github_webhook_secret');
if (!is_null($hookSecret)) {
    if (!isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
        handle_error("HTTP header 'X-Hub-Signature' is missing.");
    } elseif (!extension_loaded('hash')) {
        handle_error("Missing 'hash' extension to check the secret code validity.");
    }
    [$algo, $hash] = explode('=', $_SERVER['HTTP_X_HUB_SIGNATURE'], 2) + ['', ''];
    if (!in_array($algo, hash_algos(), true)) {
        handle_error("Hash algorithm '$algo' is not supported.");
    }
    $rawPost = file_get_contents('php://input');
    if ($hash !== hash_hmac($algo, $rawPost, $hookSecret)) {
        handle_error('Hook secret does not match.');
    }
}

init_api_request();

$event = array_key_exists('HTTP_X_GITHUB_EVENT', $_SERVER) ?  $_SERVER['HTTP_X_GITHUB_EVENT'] : '';

switch ($event) {
    case 'check_run':
        // Avoid an infinite loop of reacting to our own activity.
        if ($_REQUEST['check_run']['name'] != 'CDash' ||
                $_REQUEST['action'] == 'rerequested') {
            $sha = $_REQUEST['check_run']['head_sha'];
            Repository::createOrUpdateCheck($sha);
        }
        break;

    case 'status':
        $sha = $_REQUEST['sha'];
        Repository::createOrUpdateCheck($sha);
        break;

    default:
        break;
}
