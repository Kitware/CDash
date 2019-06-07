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
namespace CDash\Middleware;

require_once 'include/common.php';

use CDash\Config;
use CDash\Controller\Auth\Session;
use CDash\Middleware\OAuth2\OAuth2Interface;
use CDash\Model\User;
use CDash\System;
use Exception;
use League\OAuth2\Client\Provider\AbstractProvider;

abstract class OAuth2 implements OAuth2Interface
{
    public $Valid;

    protected $AuthorizationOptions;
    protected $NameParts;
    protected $OwnerDetails;
    protected $Provider;
    protected $Token;

    protected $Config;
    private $Session;
    private $System;

    public function __construct(System $system, Session $session, Config $config)
    {
        $this->AuthorizationOptions = [];
        $this->NameParts = null;
        $this->OwnerDetails = null;
        $this->Provider = null;
        $this->Token = null;
        $this->Valid = false;

        $this->System = $system;
        $this->Session = $session;
        $this->Config = $config;
    }

    public function initializeSession()
    {
        if (!$this->Session->isActive()) {
            $this->Session->start(Session::CACHE_PRIVATE_NO_EXPIRE);
            if (!$this->Session->getSessionVar('cdash')) {
                $this->Session->setSessionVar('cdash', []);
            }
            if (array_key_exists('dest', $_GET)) {
                $this->Session->setSessionVar('cdash.dest', $_GET['dest']);
            }
        }
    }

    public function getAuthorizationCode()
    {
        $provider = $this->getProvider();
        // If we don't have an authorization code then get one
        $authUrl = $provider->getAuthorizationUrl($this->AuthorizationOptions);
        $this->Session->setSessionVar('cdash.oauth2state', $provider->getState());

        // Prevent the browser from caching this redirect.
        $this->System->header("Cache-Control: no-cache, must-revalidate");
        $this->System->header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
        $this->System->header('Location: '. $authUrl);
    }

    public function checkState()
    {
        $session_state = $this->Session->getSessionVar('cdash.oauth2state');
        if (empty($_GET['state']) || ($_GET['state'] !== $session_state)) {
            throw new Exception("OAuth: Invalid state");
        }
    }

    public function loadOwnerDetails()
    {
        if (!is_null($this->OwnerDetails)) {
            return true;
        }

        if (!$this->Token) {
            return false;
        }

        $provider = $this->getProvider();
        $this->setOwnerDetails($provider->getResourceOwner($this->Token));
        return true;
    }

    public function setOwnerDetails($details)
    {
        $this->OwnerDetails = $details;
    }

    protected function loadNameParts()
    {
        if (!is_null($this->NameParts)) {
            return;
        }
        $this->loadOwnerDetails();
        $name = $this->OwnerDetails->getName();
        $this->setNameParts(explode(' ', $name));
    }

    public function setNameParts($parts)
    {
        $this->NameParts = $parts;
    }

    public function getFirstName()
    {
        $this->loadNameParts();
        return $this->NameParts[0];
    }

    public function getLastName()
    {
        $this->loadNameParts();
        return $this->NameParts[1];
    }


    public function auth(User $user)
    {
        $this->initializeSession();
        $provider = $this->getProvider();
        // Get an authorization code if we do not already have one.
        if (!isset($_GET['code'])) {
            return $this->getAuthorizationCode();
        }

        // Check given state against previously stored one to mitigate
        // CSRF attack.
        $this->checkState();

        // Try to get an access token using the authorization code grant.
        $this->Token = $provider->getAccessToken('authorization_code',
                [ 'code' => $_GET['code'] ]);

        // Use the access token to get the user's email.
        try {
            $email = $this->getEmail($user);
            if (!$email) {
                throw new Exception('Could not determine email address for user.');
            } else {
                // Check if this email address appears in our user database.
                $userid = $user->GetIdFromEmail($email);
                if (!$userid) {
                    // if no match is found, redirect to pre-filled out
                    // registration page.
                    $firstname = $this->getFirstName();
                    $lastname = $this->getLastName();
                    $baseUrl = $this->Config->get('CDASH_BASE_URL');
                    $this->System->header("Location: $baseUrl/register.php?firstname=$firstname&lastname=$lastname&email=$email");
                    return false;
                }

                $user->Id = $userid;
                $user->Fill();

                // Set "remember me" cookie.
                $key = generate_password(32);
                $this->Session->setRememberMeCookie($user, $key);

                $dest = $this->Session->getSessionVar('cdash.dest');
                $sessionArray = array(
                        'login' => $email,
                        'passwd' => $user->Password,
                        'ID' => $this->Session->getSessionId(),
                        'valid' => 1,
                        'loginid' => $user->Id);
                $this->Session->setSessionVar('cdash', $sessionArray);
                $this->Session->writeClose();
                $this->System->header("Location: {$dest}");
                // Authentication succeeded.
                return true;
            }
        } catch (Exception $e) {
            json_error_response(['error' => $e->getMessage()]);
        }
    }

    /**
     * @param AbstractProvider $provider
     * @return void
     */
    public function setProvider(AbstractProvider $provider)
    {
        $this->Provider = $provider;
    }
}
