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

use CDash\Config;
use CDash\ServiceContainer;
use CDash\System;

/**
 * @runTestsInSeparateProcesses
 */
class GitHubWebhookTest extends CDash\Test\CDashApiTestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->setEndpoint('GitHub/webhook');
        $container = ServiceContainer::container();

        $this->mock_system = $this->getMockBuilder(System::class)
            ->disableOriginalConstructor()
            ->setMethods(['system_exit'])
            ->getMock();
        $container->set(System::class, $this->mock_system);

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->config = Config::getInstance();
        $this->config->set('CDASH_WEBHOOK_SECRET', 'mock secret');
    }

    private function expectExit()
    {
        $this->mock_system
             ->expects($this->once())
             ->method('system_exit')
             ->willThrowException(new Exception('Exit!'));
    }

    public function testWebhookWithoutRequiredSignature()
    {
        $this->expectExit();
        $actual = $this->getEndpointResponse();
        $expected = new stdClass();
        $expected->error = "HTTP header 'X-Hub-Signature' is missing.";
        $this->assertEquals($expected, $actual);
    }

    public function testWebhookWithUnsupportedAlgorithm()
    {
        $this->expectExit();
        $_SERVER['HTTP_X_HUB_SIGNATURE'] = 'zzz=foo';
        $actual = $this->getEndpointResponse();
        $expected = new stdClass();
        $expected->error = "Hash algorithm 'zzz' is not supported.";
        $this->assertEquals($expected, $actual);
    }

    public function testWebhookWithWrongSignature()
    {
        $this->expectExit();
        $_SERVER['HTTP_X_HUB_SIGNATURE'] = 'sha1=wrong secret';
        $actual = $this->getEndpointResponse();
        $expected = new stdClass();
        $expected->error = 'Hook secret does not match.';
        $this->assertEquals($expected, $actual);
    }

    public function testWebhookWithCorrectSignature()
    {
        $hash = hash_hmac('sha1', '', 'mock secret');
        $_SERVER['HTTP_X_HUB_SIGNATURE'] = "sha1=$hash";
        $actual = $this->getEndpointResponse();
        $this->assertNull($actual);
    }
}
