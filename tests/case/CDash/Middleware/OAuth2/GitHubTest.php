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
use CDash\Middleware\OAuth2\GitHub;
use CDash\Model\User;
use CDash\System;
use League\OAuth2\Client\Provider\AbstractProvider;

class GitHubTest extends \PHPUnit_Framework_TestCase
{
    private $system;
    private $session;
    private $config;
    private $github;

    public function setUp()
    {
        parent::setUp();
        $this->config = Config::getInstance();

        $this->session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->system = $this->getMockBuilder(System::class)
            ->getMock();

        $this->github = $this->getMockBuilder(GitHub::class)
            ->setConstructorArgs([$this->system, $this->session, $this->config])
            ->setMethods(['loadNameParts'])
            ->getMock();
    }

    public function testGetFirstName()
    {
        $this->github->setNameParts(['John', 'Doe']);
        $this->github
            ->expects($this->once())
            ->method('loadNameParts');
        $this->assertEquals('John', $this->github->getFirstName());
    }

    public function testGetLastName()
    {
        $this->github->setNameParts(['John', 'Doe']);
        $this->github
            ->expects($this->once())
            ->method('loadNameParts');
        $this->assertEquals('Doe', $this->github->getLastName());
    }

    public function testGetEmail()
    {
        $emails = [];

        $email1 = new \stdClass();
        $email1->primary = false;
        $email1->email = 'a@b.com';
        $emails[] = $email1;

        $email2 = new \stdClass();
        $email2->primary = true;
        $email2->email = 'b@c.com';
        $emails[] = $email2;

        $this->github->setEmails($emails);
        $user = $this->getMockBuilder(User::class)->getMock();
        $this->assertEquals('b@c.com', $this->github->getEmail($user));
    }
}
