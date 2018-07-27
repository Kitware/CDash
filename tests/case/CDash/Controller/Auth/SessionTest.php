<?php
namespace CDash\Controller\Auth;

use CDash\Config;
use CDash\System;
use CDash\Model\User;

class SessionTest extends \PHPUnit_Framework_TestCase
{
    /** @var System|\PHPUnit_Framework_MockObject_MockObject */
    private $system;

    public function setUp()
    {
        $this->system = $this->getMockBuilder(System::class)
            ->getMock();
    }

    public function testStart()
    {
        $cache_policy = Session::CACHE_NOCACHE;
        $config = Config::getInstance();
        $expires = $config->get('CDASH_COOKIE_EXPIRATION_TIME');
        $gc_max = $expires + Session::EXTEND_GC_LIFETIME;


        $this->system
            ->expects($this->once())
            ->method('session_name')
            ->with('CDash');

        $this->system
            ->expects($this->once())
            ->method('session_cache_limiter')
            ->with($cache_policy);

        $this->system
            ->expects($this->once())
            ->method('session_set_cookie_params')
            ->with($expires);

        $this->system
            ->expects($this->once())
            ->method('session_start');

        $this->system
            ->expects($this->once())
            ->method('ini_set')
            ->with('session.gc_maxlifetime', $gc_max);

        $sut = new Session($this->system, Config::getInstance());
        $sut->start($cache_policy);
    }

    public function testRegenerateId()
    {
        $this->system
            ->expects($this->once())
            ->method('session_regenerate_id');

        $sut = new Session($this->system, Config::getInstance());
        $sut->regenerateId();
    }

    public function testGetSessionVar()
    {
        $login = 'Ricky Bobby';
        $paswd = 'ShakeNBake';
        $cdash = [
            'user' => $login,
            'pass' => $paswd,
        ];
        $_SESSION['cdash'] = $cdash;

        $sut = new Session(new System(), Config::getInstance());

        $this->assertEquals($login, $sut->getSessionVar('cdash.user'));
        $this->assertEquals($paswd, $sut->getSessionVar('cdash.pass'));
        $this->assertEquals($cdash, $sut->getSessionVar('cdash'));
    }

    public function testGetSessionId()
    {
        $this->system
            ->expects($this->once())
            ->method('session_id')
            ->willReturn(1011);

        $sut = new Session($this->system, Config::getInstance());
        $this->assertEquals(1011, $sut->getSessionId());
    }

    public function testExists()
    {
        $this->system
            ->expects($this->exactly(2))
            ->method('session_id')
            ->willReturnOnConsecutiveCalls('', 101);


        $sut = new Session($this->system, Config::getInstance());
        $this->assertFalse($sut->exists());
        $this->assertTrue($sut->exists());
    }

    public function testSetRememberMeCookieWithHttpsOff()
    {
        $key = 'abcde12345fghij';
        $config = Config::getInstance();
        $orig_https = $config->get('CDASH_USE_HTTPS');
        $config->set('CDASH_USE_HTTPS', false);

        $time = time() + Session::REMEMBER_ME_EXPIRATION;
        $baseUrl = $config->getBaseUrl();
        $url = parse_url($baseUrl);
        $name = Session::REMEMBER_ME_PREFIX . $url['host'];
        $path = "{$url['path']}; samesite=strict";

        /** @var User|\PHPUnit_Framework_MockObject_MockObject $mock_user */
        $mock_user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock_user->Id = 201;
        $mock_user
            ->expects($this->once())
            ->method('SetCookieKey')
            ->with($key)
            ->willReturn(true);

        $this->system
            ->expects($this->once())
            ->method('setcookie')
            ->with(
                $this->equalTo($name),
                $this->equalTo("{$mock_user->Id}{$key}"),
                $this->greaterThanOrEqual($time),
                $this->equalTo($path),
                $this->equalTo($url['host']),
                $this->equalTo(false),
                $this->equalTo(true)
            );

        $sut = new Session($this->system, $config);

        $sut->setRememberMeCookie($mock_user, $key);
        $config->set('CDASH_USE_HTTPS', $orig_https);
    }

    public function testSetRememberMeCookieWithHttpsOn()
    {
        $key = 'abcde12345fghij';
        $config = Config::getInstance();
        $orig_https = $config->get('CDASH_USE_HTTPS');
        $config->set('CDASH_USE_HTTPS', true);

        $time = time() + Session::REMEMBER_ME_EXPIRATION;
        $baseUrl = $config->getBaseUrl();
        $url = parse_url($baseUrl);
        $name = Session::REMEMBER_ME_PREFIX . $url['host'];
        $path = "{$url['path']}; samesite=strict";

        /** @var User|\PHPUnit_Framework_MockObject_MockObject $mock_user */
        $mock_user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock_user->Id = 201;
        $mock_user
            ->expects($this->once())
            ->method('SetCookieKey')
            ->with($key)
            ->willReturn(true);

        $this->system
            ->expects($this->once())
            ->method('setcookie')
            ->with(
                $this->equalTo($name),
                $this->equalTo("{$mock_user->Id}{$key}"),
                $this->greaterThanOrEqual($time),
                $this->equalTo($path),
                $this->equalTo($url['host']),
                $this->equalTo(true),
                $this->equalTo(true)
            );

        $sut = new Session($this->system, $config);

        $sut->setRememberMeCookie($mock_user, $key);
        $config->set('CDASH_USE_HTTPS', $orig_https);
    }

    public function testIsActive()
    {
        $this->system
            ->expects($this->exactly(3))
            ->method('session_status')
            ->willReturnOnConsecutiveCalls(
                PHP_SESSION_DISABLED,
                PHP_SESSION_NONE,
                PHP_SESSION_ACTIVE
            );

        $sut = new Session($this->system, Config::getInstance());
        $this->assertFalse($sut->isActive());
        $this->assertFalse($sut->isActive());
        $this->assertTrue($sut->isActive());
    }

    public function testSetSessionVarWithDottedPath()
    {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }

        $path = "cdash.oauth2.github";
        $expected = ['id' => 1, 'email' => 'ricky.bobby@talladega.tld'];
        $sut = new Session($this->system, Config::getInstance());
        $sut->setSessionVar($path, $expected);
        $actual = $sut->getSessionVar($path);
        $this->assertEquals($expected, $actual);
    }
}
