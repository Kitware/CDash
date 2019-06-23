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
use Omines\OAuth2\Client\Provider\Gitlab as GitLabProvider;

/**
 * Class GitLab
 * @package CDash\Middleware\OAuth2
 */
class GitLab extends OAuth2
{
    /**
     * GitLab constructor.
     */
    public function __construct()
    {
        $this->AuthorizationOptions = ['scope' => ['read_user']];
    }

    /**
     * @return \League\OAuth2\Client\Provider\AbstractProvider|GitLabProvider
     */
    public function getProvider()
    {
        if (!$this->Provider) {
            $uri = $this->getRedirectUri();
            $settings = array_merge(
                ['redirectUri' => $uri],
                config('oauth2.gitlab')
            );
            $this->Provider = new GitLabProvider($settings);
        }
        return $this->Provider;
    }
}
