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
namespace CDash\Controller\Auth;

use CDash\Config;
use CDash\System;

/**
 * Class Session
 * @package CDash\Controller\Auth
 */
class Session
{
    const EXTEND_GC_LIFETIME = 600;
    const CACHE_NOCACHE = 'nocache';
    const CACHE_PRIVATE_NO_EXPIRE = 'private_no_expire';

    private $config;
    private $system;

    /**
     * Session constructor.
     * @param System $system
     * @param Config $config
     */
    public function __construct(System $system, Config $config)
    {
        $this->system = $system;
        $this->config = $config;
    }

    /**
     * @param $cache_policy
     */
    public function start($cache_policy)
    {
        $lifetime = $this->config->get('CDASH_COOKIE_EXPIRATION_TIME');
        $maxlife = $lifetime + self::EXTEND_GC_LIFETIME;
        $baseUrl = Config::getInstance()->getBaseUrl();
        $url = parse_url($baseUrl);
        $secure = false; // send only over a https connection
        $httponly = true; // make cookie only accessible via http, e.g. not javascript

        $this->system->session_name('CDash');
        $this->system->session_cache_limiter($cache_policy);
        $this->system->session_set_cookie_params(
            $lifetime,
            $url['path'],
            $url['host'],
            $secure,
            $httponly
        );
        $this->system->ini_set('session.gc_maxlifetime', $maxlife);
        $this->system->session_start();
    }

    /**
     * @return void
     */
    public function regenerateId()
    {
        $this->system->session_regenerate_id();
    }

    public function getSessionId()
    {
        return $this->system->session_id();
    }

    public function exists()
    {
        return !empty($this->getSessionId());
    }

    public function destroy()
    {
        $this->system->session_destroy();

        // TODO: explicit where the class is implicit, pick one
        if (isset($_SESSION['cdash'])) {
            unset($_SESSION['cdash']);
        }
    }

    /**
     * @param $path
     * @return string|null
     */
    public function getSessionVar($path)
    {
        $legs = explode('.', $path);
        $session = count($legs) ? $_SESSION : null;

        foreach ($legs as $leg) {
            if (isset($session[$leg])) {
                $session = $session[$leg];
            } else {
                return null;
            }
        }
        return $session;
    }

    public function setSessionVar($name, $value)
    {
        $_SESSION[$name] = $value;
    }
}
