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

use CDash\Middleware\OAuth2\OAuth2Interface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 * Class OAuth2
 * @package CDash\Middleware
 */
abstract class OAuth2 implements OAuth2Interface
{
    protected $AuthorizationOptions = [];
    protected $OwnerDetails;
    protected $Provider;
    protected $Token;
    protected $Email;

    /** @var Request $request */
    private $request;

    /**
     * Check given state against previously stored one to mitigate CSRF attack.
     *
     * @return bool
     */
    public function checkState()
    {
        $request = $this->getRequest();
        $request_state = $request->query('state');
        $session_state = $request->session()->get('auth.oauth.state');
        return Str::is($request_state, $session_state);
    }

    /**
     * @return ResourceOwnerInterface
     * @throws IdentityProviderException
     */
    public function getOwnerDetails()
    {
        if (!$this->OwnerDetails) {
            $provider = $this->getProvider();
            $token = $this->getAccessToken();
            $this->OwnerDetails = $provider->getResourceOwner($token);
        }
        return $this->OwnerDetails;
    }

    /**
     * @return AccessTokenInterface|AccessToken
     * @throws IdentityProviderException
     */
    protected function getAccessToken()
    {
        if (!$this->Token) {
            $provider = $this->getProvider();
            $options = ['code' => $this->getRequest()->query('code')];
            $this->Token = $provider->getAccessToken('authorization_code', $options);
        }
        return $this->Token;
    }

    /**
     * @return RedirectResponse
     */
    public function authorization()
    {
        $request = $this->getRequest();
        $provider = $this->getProvider();
        $to = $provider->getAuthorizationUrl($this->AuthorizationOptions);
        $request->session()
            ->put('auth.oauth.state', $provider->getState());

        return redirect($to)
            ->header('Cache-Control', 'no-cache, must-revalidate')
            ->header('Expires', 'Sat, 26 Jul 1997 05:00:00GMT');
    }

    /**
     * @return string
     */
    public function getAuthorizationUrl()
    {
        $provider = $this->getProvider();
        return $provider->getAuthorizationUrl($this->AuthorizationOptions);
    }

    /**
     * @return string
     */
    public function getState()
    {
        $provider = $this->getProvider();
        return $provider->getState();
    }

    /**
     * @return string
     * @throws IdentityProviderException
     */
    public function getOwnerName()
    {
        $details = $this->getOwnerDetails();
        return $details->getName();
    }

    /**
     * @param AbstractProvider $provider
     * @return self
     */
    public function setProvider(AbstractProvider $provider)
    {
        $this->Provider = $provider;
        return $this;
    }

    /**
     * @return array|Request|string
     */
    public function getRequest()
    {
        if (!$this->request) {
            $this->request = request();
        }
        return $this->request;
    }

    /**
     * @param Request $request
     * @return self
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * @param Collection $email
     * @return OAuth2
     */
    public function setEmail(Collection $email)
    {
        $this->Email = $email;
        return $this;
    }

    /**
     * @return Collection
     * @throws IdentityProviderException
     */
    public function getEmail()
    {
        if (!$this->Email) {
            $details = $this->getOwnerDetails();
            $email = (object)[
                'email' => strtolower($details->getEmail()),
            ];
            $this->Email = collect([$email]);
        }
        return $this->Email;
    }

    /**
     * If an associated primary attribute is available for an email address that email will
     * be returned, otherwise we return the first email
     *
     * @return string
     * @throws IdentityProviderException
     */
    public function getPrimaryEmail()
    {
        $email = $this->getEmail();
        $primary = $email->firstWhere('primary', true) ?: $email->first();
        return $primary ? $primary->email : '';
    }

    /**
     * @return string
     */
    protected function getRedirectUri()
    {
        $service = strtolower(class_basename(static::class));
        return route('oauth.callback', ['service' => $service]);
    }
}
