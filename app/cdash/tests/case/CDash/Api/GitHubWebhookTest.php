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

/**
 * @runTestsInSeparateProcesses
 */
class GitHubWebhookTest extends CDash\Test\CDashTestCase
{
    public function setUp() : void
    {
        parent::setUp();
        $this->setEndpoint('GitHub/webhook');

        $_SERVER['REQUEST_METHOD'] = 'POST';

        config(['cdash.github_webhook_secret' => 'mock secret']);
    }

    public function testWebhookWithoutRequiredSignature()
    {
        $actual = $this->getEndpointResponse();
        $expected = new stdClass();
        $expected->error = "HTTP header 'X-Hub-Signature' is missing.";
        $this->assertEquals($expected, $actual);
    }

    public function testWebhookWithUnsupportedAlgorithm()
    {
        $_SERVER['HTTP_X_HUB_SIGNATURE'] = 'zzz=foo';
        $actual = $this->getEndpointResponse();
        $expected = new stdClass();
        $expected->error = "Hash algorithm 'zzz' is not supported.";
        $this->assertEquals($expected, $actual);
    }

    public function testWebhookWithWrongSignature()
    {
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
