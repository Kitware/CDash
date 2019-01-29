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
use CDash\Middleware\OAuth2\GitLab;
use CDash\Model\User;
use CDash\System;
use CDash\Test\CDashTestCase;
use League\OAuth2\Client\Provider\AbstractProvider;

class GitLabTest extends CDashTestCase
{
    private $system;
    private $session;
    private $config;
    private $gitlab;

    public function setUp()
    {
        parent::setUp();
        $this->config = Config::getInstance();

        $this->session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->system = $this->getMockBuilder(System::class)
            ->getMock();

        $this->gitlab = $this->getMockBuilder(GitLab::class)
            ->setConstructorArgs([$this->system, $this->session, $this->config])
            ->setMethods(['loadNameParts'])
            ->getMock();
    }

    public function testGetFirstName()
    {
        $this->gitlab->setNameParts(['John', 'Doe']);
        $this->gitlab
            ->expects($this->once())
            ->method('loadNameParts');
        $this->assertEquals('John', $this->gitlab->getFirstName());
    }

    public function testGetLastName()
    {
        $this->gitlab->setNameParts(['John', 'Doe']);
        $this->gitlab
            ->expects($this->once())
            ->method('loadNameParts');
        $this->assertEquals('Doe', $this->gitlab->getLastName());
    }

    public function testGetEmail()
    {
        $this->gitlab->setEmail('a@b.com');
        $user = $this->getMockBuilder(User::class)->getMock();
        $this->assertEquals('a@b.com', $this->gitlab->getEmail($user));
    }
}
