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
use CDash\Middleware\OAuth2\Google;
use CDash\Model\User;
use CDash\System;
use League\OAuth2\Client\Provider\GoogleUser;

class GoogleTest extends \PHPUnit_Framework_TestCase
{
    private $system;
    private $session;
    private $config;
    private $google;

    public function setUp()
    {
        parent::setUp();
        $this->config = Config::getInstance();

        $this->session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->system = $this->getMockBuilder(System::class)
            ->getMock();

        $this->google = $this->getMockBuilder(Google::class)
            ->setConstructorArgs([$this->system, $this->session, $this->config])
            ->setMethods(['loadOwnerDetails'])
            ->getMock();
    }

    public function testGetFirstName()
    {
        $response = ['name' => ['givenName' => 'John']];
        $owner_details = new GoogleUser($response);
        $this->google->setOwnerDetails($owner_details);
        $this->assertEquals('John', $this->google->getFirstName());
    }

    public function testGetLastName()
    {
        $response = ['name' => ['familyName' => 'Doe']];
        $owner_details = new GoogleUser($response);
        $this->google->setOwnerDetails($owner_details);
        $this->assertEquals('Doe', $this->google->getLastName());
    }

    public function testGetEmail()
    {
        $response = ['emails' => [0 => ['value' => 'a@b.com']]];
        $owner_details = new GoogleUser($response);
        $this->google->setOwnerDetails($owner_details);
        $user = $this->getMockBuilder(User::class)->getMock();
        $this->assertEquals('a@b.com', $this->google->getEmail($user));
    }
}
