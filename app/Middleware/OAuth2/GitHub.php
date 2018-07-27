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
use League\OAuth2\Client\Provider\Github as GitHubProvider;

class GitHub extends OAuth2
{
    private $Emails;

    public function __construct(System $system, Session $session, Config $config)
    {
        parent::__construct($system, $session, $config);

        $this->AuthorizationOptions = ['scope' => ['read:user', 'user:email']];
    }

    public function getEmail(User $user)
    {
        if (empty($this->Emails)) {
            $this->loadEmails();
        }

        $email = '';
        $first_email = '';
        $primary_email = '';

        foreach ($this->Emails as $e) {
            $email = strtolower($e->email);
            if (!$first_email) {
                $first_email = $email;
            }
            if ($e->primary) {
                $primary_email = $email;
            }
            // Return any email address that corresponds to an existing
            // CDash user.
            if ($user->GetIdFromEmail($email)) {
                return $email;
            }
        }

        // Otherwise use the primary email address for this GitHub account
        // if it has one set.
        if ($primary_email) {
            return $primary_email;
        }

        // If all else fails, return the first email in the list.
        return $first_email;
    }

    private function loadEmails()
    {
        $request = $this->Provider->getAuthenticatedRequest(
                'GET',
                'https://api.github.com/user/emails',
                $this->Token
                );
        $this->setEmails(
            json_decode($this->Provider->getResponse($request)->getBody()));
    }

    public function setEmails($emails)
    {
        $this->Emails = $emails;
    }

    public function getProvider()
    {
        if (is_null($this->Provider) && array_key_exists('GitHub',
                    $this->Config->get('OAUTH2_PROVIDERS'))) {
            $github_settings = $this->Config->get('OAUTH2_PROVIDERS')['GitHub'];
            if (array_key_exists('clientId', $github_settings) &&
                    array_key_exists('clientSecret', $github_settings) &&
                    array_key_exists('redirectUri', $github_settings)) {
                $this->Provider = new GitHubProvider($github_settings);
                $this->Valid = true;
            }
        }
        return $this->Provider;
    }
}
