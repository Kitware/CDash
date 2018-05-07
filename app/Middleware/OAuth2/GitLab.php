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
use CDash\System;
use League\OAuth2\Client\Provider\AbstractProvider;
use Omines\OAuth2\Client\Provider\Gitlab as GitLabProvider;

class GitLab extends OAuth2
{
    private $Email;

    public function __construct(System $system, Session $session, Config $config)
    {
        parent::__construct($system, $session, $config);
        $this->AuthorizationOptions = ['scope' => ['read_user']];
        $this->Email = '';
    }

    public function getEmail()
    {
        if (empty($this->Email)) {
            $this->loadEmail();
        }
        return $this->Email;
    }

    public function setEmail($email)
    {
        $this->Email = $email;
    }

    private function loadEmail()
    {
        $this->loadOwnerDetails();
        $this->setEmail(strtolower($this->OwnerDetails->getEmail()));
    }

    public function getProvider()
    {
        if (is_null($this->Provider) && array_key_exists('GitLab',
                    $this->Config->get('OAUTH2_PROVIDERS'))) {
            $gitlab_settings = $this->Config->get('OAUTH2_PROVIDERS')['GitLab'];
            if (array_key_exists('clientId', $gitlab_settings) &&
                    array_key_exists('clientSecret', $gitlab_settings) &&
                    array_key_exists('redirectUri', $gitlab_settings)) {
                $this->Provider = new GitLabProvider($gitlab_settings);
                $this->Valid = true;
            }
        }
        return $this->Provider;
    }
}
