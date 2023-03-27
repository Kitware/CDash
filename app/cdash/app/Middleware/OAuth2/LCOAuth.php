<?php
/*========================================================================i
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
use CDash\Middleware\OAuth2\LCProvider;

/**
 * Class LCOAuth
 * @package CDash\Middleware\OAuth2
 */
class LCOAuth extends OAuth2
{
    /**
     * LCOAuth constructor.
     */
    public function __construct()
    {
        $this->AuthorizationOptions = ['scope' => ['profile']];
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
                'GET',
                config('oauth2.lcoauth.urlResourceOwnerDetails'),
                $token
            );
            
            $response = $provider
                ->getResponse($request)
                ->getBody();
            $response_decoded = json_decode($response);

            $this->Email = collect([
	        new class ($response_decoded->email){
		    public $email;

                    function __construct($em) {
                        $this->email = $em;
                    }
                }
	    ]);
        }
            return $this->Email;
    }

    /**
     * @return Collection
     * @throws IdentityProviderException
     */
    public function getName()
    {
        if (!$this->Name) {
            $token = $this->getAccessToken();
            $provider = $this->getProvider();
            $request = $provider->getAuthenticatedRequest(
                'GET',
                config('oauth2.lcoauth.urlResourceOwnerDetails'),
                $token
            );
            
            $response = $provider
                ->getResponse($request)
                ->getBody();
            $response_decoded = json_decode($response);

            $this->Name = collect([
	        new class ($response_decoded->displayName){
		    public $name;

                    function __construct($name) {
                        $this->name = $name;
                    }
                }
	    ]);
        }
            return $this->Name;
    }

    /**
     * @return \League\OAuth2\Client\Provider\GenericProvider|LCOAuthProvider
     */
    public function getProvider()
    {
        if (!$this->Provider) {
            $uri = $this->getRedirectUri();
            $settings = array_merge(
                ['redirectUri' => $uri],
                config('oauth2.lcoauth')
            );
            $this->Provider = new LCProvider($settings);
        }
        return $this->Provider;
    }
}
