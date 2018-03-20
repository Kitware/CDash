<?php
namespace CDash\Controller\Auth;

use CDash\Config;
use CDash\System;

class SessionTest extends \PHPUnit_Framework_TestCase
{
    public function testStart()
    {
        $cache_policy = 'nocache';
        $config = Config::getInstance();
        $expires = $config->get('CDASH_COOKIE_EXPIRATION_TIME');
        $gc_max = $expires + Session::EXTEND_GC_LIFETIME;

        /** @var System|\PHPUnit_Framework_MockObject_MockObject $mock_system */
        $mock_system = $this->getMockBuilder(System::class)
            ->getMock();

        $mock_system
            ->expects($this->once())
            ->method('session_name')
            ->with('CDash');

        $mock_system
            ->expects($this->once())
            ->method('session_cache_limiter')
            ->with($cache_policy);

        $mock_system
            ->expects($this->once())
            ->method('session_set_cookie_params')
            ->with($expires);

        $mock_system
            ->expects($this->once())
            ->method('session_start');

        $mock_system
            ->expects($this->once())
            ->method('ini_set')
            ->with('session.gc_maxlifetime', $gc_max);

        $sut = new Session($mock_system, Config::getInstance());
        $sut->start($cache_policy);
    }

    public function testRegenerateId()
    {
        /** @var System|\PHPUnit_Framework_MockObject_MockObject $mock_system */
        $mock_system = $this->getMockBuilder(System::class)
            ->getMock();

        $mock_system
            ->expects($this->once())
            ->method('session_regenerate_id');

        $sut = new Session($mock_system, Config::getInstance());
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
}
