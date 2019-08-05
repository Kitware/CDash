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

use CDash\Test\OAuthTestHelper;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

class GitHubTest extends TestCase
{
    use OAuthTestHelper;

    public function testGetProvider()
    {
        $sut = new GitHub();
        $expected = \League\OAuth2\Client\Provider\Github::class;
        $actual = $sut->getProvider();
        $this->assertInstanceOf($expected, $actual);
    }

    /**
     * @throws \ReflectionException
     * @throws IdentityProviderException
     */
    public function testGetEmail()
    {
        $provider = $this->getProvider(['getAuthenticatedRequest', 'getResponse', 'getBody']);

        $request = $this->getMockForAbstractClass(RequestInterface::class);

        $provider->expects($this->once())
            ->method('getAuthenticatedRequest')
            ->with(
                GitHub::AUTH_REQUEST_METHOD,
                GitHub::AUTH_REQUEST_URI,
                $this->accessToken)
            ->willReturn($request);

        $provider->expects($this->once())
            ->method('getResponse')
            ->with($request)
            ->willReturnSelf();

        $provider->expects($this->once())
            ->method('getBody')
            ->willReturn(json_encode([
                (object)['email' => 'ricky.bobby@taladega.tld', 'primary' => false],
                (object)['email' => 'cal.naughton@taladega.tld', 'primary' => true],
            ]));

        $sut = new GitHub();
        $sut->setProvider($provider);

        $email_collection = $sut->getEmail();
        $email = $email_collection->get(0);
        $expected = 'ricky.bobby@taladega.tld';
        $actual = $email->email;
        $this->assertEquals($expected, $actual);
        $this->assertFalse($email->primary);

        $email = $email_collection->get(1);
        $expected = 'cal.naughton@taladega.tld';
        $actual = $email->email;
        $this->assertEquals($expected, $actual);
        $this->assertTrue($email->primary);
    }
}
