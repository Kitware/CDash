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
namespace CDash\Middleware\OAuth2;

use CDash\Config;
use CDash\Controller\Auth\Session;
use CDash\Middleware\OAuth2;
use CDash\Model\User;
use CDash\System;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Google as GoogleProvider;

class Google extends OAuth2
{
    public function __construct(System $system, Session $session, Config $config)
    {
        parent::__construct($system, $session, $config);
        $this->AuthorizationOptions =
            [ 'scope' => ['https://www.googleapis.com/auth/userinfo.email'] ];
    }

    public function getEmail(User $user)
    {
        $this->loadOwnerDetails();
        $email = strtolower($this->OwnerDetails->getEmail());
        return $email;
    }

    public function getFirstName()
    {
        $this->loadOwnerDetails();
        return $this->OwnerDetails->getFirstName();
    }

    public function getLastName()
    {
        $this->loadOwnerDetails();
        return $this->OwnerDetails->getLastName();
    }

    public function getProvider()
    {
        if (is_null($this->Provider) && array_key_exists('Google',
                    $this->Config->get('OAUTH2_PROVIDERS'))) {
            $google_settings = $this->Config->get('OAUTH2_PROVIDERS')['Google'];
            if (array_key_exists('clientId', $google_settings) &&
                    array_key_exists('clientSecret', $google_settings) &&
                    array_key_exists('redirectUri', $google_settings)) {
                // Get domain from redirect URI.
                $url_parts = parse_url($google_settings['redirectUri']);
                $hosted_domain = $url_parts['scheme'] . '://' . $url_parts['host'];
                $google_settings['hostedDomain'] = $hosted_domain;

                $this->Provider = new GoogleProvider($google_settings);
                $this->Valid = true;
            }
        }
        return $this->Provider;
    }

    public function auth(User $user)
    {
        if (!empty($_GET['error'])) {
            // Got an error, probably user denied access.
            throw new Exception($_GET['error']);
        }
        parent::auth($user);
    }
}
