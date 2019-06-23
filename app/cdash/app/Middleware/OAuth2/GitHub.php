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
use Illuminate\Support\Collection;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\Github as GitHubProvider;

/**
 * Class GitHub
 * @package CDash\Middleware\OAuth2
 */
class GitHub extends OAuth2
{
    const AUTH_REQUEST_METHOD = 'GET';
    const AUTH_REQUEST_URI = 'https://api.github.com/user/emails';

    /**
     * GitHub constructor
     */
    public function __construct()
    {
        $this->AuthorizationOptions = ['scope' => ['read:user', 'user:email']];
    }

    /**
     * @return Collection
     * @throws IdentityProviderException
     */
    public function getEmail()
    {
        if (!$this->Email) {
            $token = $this->getAccessToken();
            $provider = $this->getProvider();
            $request = $provider->getAuthenticatedRequest(
                self::AUTH_REQUEST_METHOD,
                self::AUTH_REQUEST_URI,
                $token
            );
            $response = $provider
                ->getResponse($request)
                ->getBody();
            $emails = (array)json_decode($response) ?: [];

            $this->Email = collect($emails);
        }
        return $this->Email;
    }

    /**
     * @return AbstractProvider
     */
    public function getProvider()
    {
        if (!$this->Provider) {
            $uri = $this->getRedirectUri();
            $settings = array_merge(
                ['redirectUri' => $uri],
                config('oauth2.github')
            );
            $this->Provider = new GitHubProvider($settings);
        }
        return $this->Provider;
    }
}
