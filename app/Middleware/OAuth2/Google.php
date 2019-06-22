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

use CDash\Middleware\OAuth2;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\Google as GoogleProvider;

/**
 * Class Google
 * @package CDash\Middleware\OAuth2
 */
class Google extends OAuth2
{
    /**
     * Google constructor.
     */
    public function __construct()
    {
        $this->AuthorizationOptions =
            [ 'scope' => ['https://www.googleapis.com/auth/userinfo.email'] ];
    }

    /**
     * @return string
     * @throws IdentityProviderException
     */
    public function getOwnerName()
    {
        $details = $this->getOwnerDetails();
        return "{$details->getFirstName()} {$details->getLastName()}";
    }

    /**
     * @return GoogleProvider
     */
    public function getProvider()
    {
        if (!$this->Provider) {
            $uri = $this->getRedirectUri();
            $settings = array_merge(
                ['hostedDomain' => '*', 'redirectUri' => $uri],
                config('oauth2.google')
            );
            $this->Provider = new GoogleProvider($settings);
        }
        return $this->Provider;
    }
}
