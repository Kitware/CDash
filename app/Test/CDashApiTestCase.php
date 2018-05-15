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
namespace CDash\Test;

use CDash\Config;
use CDash\Controller\Auth\Session;
use CDash\Model\User;
use CDash\ServiceContainer;
use CDash\System;

/**
 * Class CDashApiTestCase
 *
 * TODO: This class should not exist, but does so only to stop the bleeding
 */
class CDashApiTestCase extends CDashTestCase
{
    /** @var User|\PHPUnit_Framework_MockObject_MockObject */
    private $mock_session_user;

    /** @var String $endpoint */
    private $endpoint;

    public function setUp()
    {
        parent::setUp();

        $this->initSessionGlobalVar();

        // TODO: implement Request class to make annoyances like this go away
        $_SERVER['SERVER_NAME'] = 'bleh.yuck.meh';

        /** @var System|\PHPUnit_Framework_MockObject_MockObject $mock_system */
        $mock_system = $this->getMockBuilder(System::class)->getMock();

        $this->setDatabaseMocked();
        $this->createServiceContainerForTesting();

        $session = new Session($mock_system, Config::getInstance());
        $this->mock_session_user = $this->getMockUser();

        $container = ServiceContainer::container();
        $container->set(User::class, $this->mock_session_user);
        $container->set(Session::class, $session);

        $login = 'ricky.bobby@talladega.tld';
        $passwd = 'shake-n-bake';
        $id = 26;

        $session->setSessionVar('cdash', [
            'passwd' => $passwd,
            'login' => $login,
            'loginid' => $id,
        ]);

        $this->mock_session_user->Password = $passwd;
        $this->mock_session_user->Email = $login;
        $this->mock_session_user->Id = $id;

        $this->mock_session_user
            ->expects($this->any())
            ->method('GetIdFromEmail')
            ->willReturn($this->mock_session_user->Id);
    }

    protected function getSessionUser()
    {
        return $this->mock_session_user;
    }

    protected function setEndpoint($endpoint)
    {
        $config = Config::getInstance();
        $root = $config->get('CDASH_ROOT_DIR');
        $this->endpoint = realpath("{$root}/public/api/v1/{$endpoint}.php");
        if (!$this->endpoint) {
            throw new \Exception('Endpoint does not exist');
        }
    }

    // This method is used to ensure that the $_SESSION variable exists by turning off any
    // output usually created by session_start(), calling session_start(), then closing
    // the session and returning the values session.use_cookies and session_cache_limiter
    // back to their original states.
    protected function initSessionGlobalVar()
    {
        // temporarily store values
        $use_cookies = ini_get('session.use_cookies');
        $limiter = session_cache_limiter();

        // initialize $_SESSION
        ini_set('session.use_cookies', false);
        session_cache_limiter(false);
        session_start();
        session_write_close();

        // restore values
        ini_set('session.use_cookies', $use_cookies);
        session_cache_limiter($limiter);
    }

    protected function getEndpointResponse()
    {
        $response = null;

        ob_start();
        if ($this->endpoint) {
            require $this->endpoint;
            $response = ob_get_contents();
            ob_end_clean();
        } else {
            throw new \Exception('Endpoint not set');
        }

        return json_decode($response);
    }
}
