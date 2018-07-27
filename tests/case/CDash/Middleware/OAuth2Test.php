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

class OAuth2Test extends \PHPUnit_Framework_TestCase
{
    /** @var System|\PHPUnit_Framework_MockObject_MockObject $system */
    private $system;

    /** @var Session|\PHPUnit_Framework_MockObject_MockObject */
    private $session;

    /** @var Config $config */
    private $config;

    /** @var OAuth2|\PHPUnit_Framework_MockObject_MockObject $sut */
    private $sut;

    public function setUp()
    {
        parent::setUp();
        $this->config = Config::getInstance();

        $this->session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->system = $this->getMockBuilder(System::class)
            ->getMock();

        $this->sut = $this->getMockForAbstractClass(
            OAuth2::class,
            [$this->system, $this->session, $this->config],
            '',
            true,
            true,
            true,
            ['getProvider', 'getEmail', 'getFirstName', 'getLastName']
        );
    }

    /**
     * @return AbstractProvider|\PHPUnit_Framework_MockObject_MockObject $mock_provider
     */
    private function createMockProvider()
    {
        return $this->getMockBuilder(AbstractProvider::class)
            ->setMethods([
                'getBaseAuthorizationUrl',
                'getBaseAccessTokenUrl',
                'getResourceOwnerDetailsUrl',
                'getDefaultScopes',
                'checkResponse',
                'createResourceOwner',
                'getAuthorizationUrl',
                'getState',
                'getAccessToken'])
            ->getMock();
    }

    public function testInitializeSessionWillNotStartIfAlreadyActive()
    {
        $this->session
            ->expects($this->once())
            ->method('isActive')
            ->willReturn(true);

        $this->session
            ->expects($this->never())
            ->method('start');

        $this->sut->initializeSession();
    }

    public function testInitializeSession()
    {
        $this->session
            ->expects($this->once())
            ->method('isActive')
            ->willReturn(false);

        $this->session
            ->expects($this->once())
            ->method('start')
            ->with(Session::CACHE_PRIVATE_NO_EXPIRE);

        $this->session
            ->expects($this->once())
            ->method('setSessionVar')
            ->with('cdash', []);

        $this->sut->initializeSession();
    }

    public function testInitializeSessionWithRedirect()
    {
        $_GET['dest'] = '/CDash/user.php';

        $this->session
            ->expects($this->once())
            ->method('isActive')
            ->willReturn(false);

        $this->session
            ->expects($this->once())
            ->method('start')
            ->with(Session::CACHE_PRIVATE_NO_EXPIRE);

        $this->session
            ->expects($this->exactly(2))
            ->method('setSessionVar')
            ->withConsecutive(['cdash', []], ['cdash.dest', $_GET['dest']]);

        $this->sut->initializeSession();
    }

    public function testGetAuthorizationCode()
    {
        $state = 'this is the state';
        $auth_url = 'https://service.oauth.tld/this/is/login';

        $mock_provider = $this->createMockProvider();

        $mock_provider
            ->expects($this->once())
            ->method('getState')
            ->willReturn($state);

        $mock_provider
            ->expects($this->once())
            ->method('getAuthorizationUrl')
            ->willReturn($auth_url);

        $this->session
            ->expects($this->once())
            ->method('setSessionVar')
            ->with('cdash.oauth2state', $state);

        $this->sut
            ->expects($this->any())
            ->method('getProvider')
            ->willReturn($mock_provider);

        $this->system
            ->expects($this->exactly(3))
            ->method('header')
            ->withConsecutive(
                ['Cache-Control: no-cache, must-revalidate'],
                ['Expires: Sat, 26 Jul 1997 05:00:00 GMT'],
                ["Location: {$auth_url}"]
            );

        $this->system
            ->expects($this->once())
            ->method('system_exit');

        $this->sut->getAuthorizationCode();
    }

    public function testCheckStateWithEmptyState()
    {
        $_GET = [];
        $_SESSION = [
            'cdash' => [
                'oauth2state' => true,
            ],
        ];

        $this->system
            ->expects($this->once())
            ->method('system_exit')
            ->with('Invalid state');

        $this->sut->checkState();

        $this->assertFalse(isset($_SESSION['cdash']['oauth2state']));
    }

    public function testCheckStateWhenRequestDoesNotMatchSession()
    {
        $_GET['state'] = 'abcdefg';
        $_SESSION = [
            'cdash' => [
                'oauth2state' => '123456',
            ],
        ];

        $this->system
            ->expects($this->once())
            ->method('system_exit')
            ->with('Invalid state');

        $this->sut->checkState();

        $this->assertFalse(isset($_SESSION['cdash']['oauth2state']));
    }

    public function testCheckState()
    {
        $_GET['state'] = '123456';
        $_SESSION = [
            'cdash' => [
                'oauth2state' => '123456',
            ],
        ];

        $this->session
            ->expects($this->once())
            ->method('getSessionVar')
            ->with('cdash.oauth2state')
            ->willReturn($_SESSION['cdash']['oauth2state']);

        $this->system
            ->expects($this->never())
            ->method('system_exit');

        $this->sut->checkState();

        $this->assertTrue(isset($_SESSION['cdash']['oauth2state']));
    }

    public function testAuthWithoutAuthorizationCode()
    {
        $_SESSION = [];
        $mock_provider = $this->createMockProvider();

        $this->session
            ->expects($this->once())
            ->method('isActive')
            ->willReturn(true);

        $this->sut
            ->expects($this->any())
            ->method('getProvider')
            ->willReturn($mock_provider);

        $this->system
            ->expects($this->once())
            ->method('system_exit');

        /** @var User|\PHPUnit_Framework_MockObject_MockObject $mock_user */
        $mock_user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->sut->auth($mock_user);
    }

    public function testAuthEmailNotRegistered()
    {
        $url = 'https://cdash.talladega.tld/CDash';
        $email = 'ricky.bobby@talladega.tld';
        $first = 'Rick';
        $last = 'Bobby';
        $this->config->set('CDASH_BASE_URL', $url);

        $_GET = [
            'code' => 'a.u.t.h.1.2.3.4',
            'state' => 'happy',
        ];

        $mock_provider = $this->createMockProvider();

        $this->session
            ->expects($this->once())
            ->method('isActive')
            ->willReturn(true);

        $this->session
            ->expects($this->once())
            ->method('getSessionVar')
            ->with('cdash.oauth2state')
            ->willReturn('happy');

        $this->sut
            ->expects($this->any())
            ->method('getProvider')
            ->willReturn($mock_provider);

        $this->sut
            ->expects($this->once())
            ->method('getEmail')
            ->willReturn($email);

        $this->sut
            ->expects($this->once())
            ->method('getFirstName')
            ->willReturn($first);

        $this->sut
            ->expects($this->once())
            ->method('getLastName')
            ->willReturn($last);

        $this->system
            ->expects($this->once())
            ->method('header')
            ->with("Location: {$url}/register.php?firstname={$first}&lastname={$last}&email={$email}");

        /** @var User|\PHPUnit_Framework_MockObject_MockObject $mock_user */
        $mock_user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock_user
            ->expects($this->once())
            ->method('GetIdFromEmail')
            ->willReturn(false);

        $actual = $this->sut->auth($mock_user);
        $this->assertFalse($actual);
    }

    public function testAuthSuccessFul()
    {
        $url = 'https://cdash.talladega.tld/CDash';
        $email = 'ricky.bobby@talladega.tld';
        $first = 'Rick';
        $last = 'Bobby';
        $password = 'shake-n-bake';
        $session_id = 66;
        $user_id = 26;

        $this->config->set('CDASH_BASE_URL', $url);
        $_GET = [
            'code' => 'a.u.t.h.1.2.3.4',
            'state' => 'happy',
        ];

        $mock_provider = $this->createMockProvider();

        $this->session
            ->expects($this->once())
            ->method('isActive')
            ->willReturn(true);

        $this->session
            ->expects($this->any())
            ->method('getSessionVar')
            ->willReturnMap([
                ['cdash.oauth2state', 'happy'],
                ['cdash.dest', "{$url}/user.php"]
            ]);

        $this->session
            ->expects($this->once())
            ->method('getSessionId')
            ->willReturn($session_id);

        $this->session
            ->expects($this->any())
            ->method('setSessionVar')
            ->with('cdash', [
                'login' => $email,
                'passwd' => $password,
                'ID' => $session_id,
                'valid' => 1,
                'loginid' => $user_id,
            ]);

        $this->session
            ->expects($this->once())
            ->method('writeClose');

        $this->sut
            ->expects($this->any())
            ->method('getProvider')
            ->willReturn($mock_provider);

        $this->sut
            ->expects($this->once())
            ->method('getEmail')
            ->willReturn($email);

        $this->system
            ->expects($this->once())
            ->method('header')
            ->with("Location: {$url}/user.php");

        /** @var User|\PHPUnit_Framework_MockObject_MockObject $mock_user */
        $mock_user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock_user->Password = $password;

        $mock_user
            ->expects($this->once())
            ->method('GetIdFromEmail')
            ->willReturn($user_id);

        $mock_user
            ->expects($this->once())
            ->method('Fill');

        $actual = $this->sut->auth($mock_user);
        $this->assertTrue($actual);
    }
}
