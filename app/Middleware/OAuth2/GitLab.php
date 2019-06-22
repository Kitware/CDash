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
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Omines\OAuth2\Client\Provider\Gitlab as GitLabProvider;

/**
 * Class GitLab
 * @package CDash\Middleware\OAuth2
 */
class GitLab extends OAuth2
{
    /** @var Collection $Email */
    private $Email;

    public function __construct()
    {
        $this->AuthorizationOptions = ['scope' => ['read_user']];
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
               'email' => strtolower($details->getEmail())
           ];
           $this->Email = collect([$email]);
        }
        return $this->Email;
    }

    /**
     * @param Collection $email
     */
    public function setEmail(Collection $email)
    {
        $this->Email = $email;
    }

    /**
     * @return \League\OAuth2\Client\Provider\AbstractProvider|GitLabProvider
     */
    public function getProvider()
    {
        if (!$this->Provider) {
            $settings = config('oauth2.gitlab');
            $this->Provider = new GitLabProvider($settings);
        }
        return $this->Provider;
    }
}
