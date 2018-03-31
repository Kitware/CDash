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
use CDash\Model\User;

/**
 * Class Session
 * @package CDash\Controller\Auth
 */
class Session
{
    const EXTEND_GC_LIFETIME = 600;
    const CACHE_NOCACHE = 'nocache';
    const CACHE_PRIVATE_NO_EXPIRE = 'private_no_expire';

    const REMEMBER_ME_PREFIX = 'CDash-';
    const REMEMBER_ME_EXPIRATION = 2592000; // 60 * 60 * 24 * 30, 1 MONTH
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
        $baseUrl = $this->config->getBaseUrl();
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

    /**
     * @return string
     */
    public function getSessionId()
    {
        return $this->system->session_id();
    }

    /**
     * @return bool
     */
    public function exists()
    {
        $id = $this->getSessionId();
        return !empty($id);
    }

    /**
     * @return void
     */
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

    /**
     * @param $name
     * @param $value
     * @return void
     */
    public function setSessionVar($name, $value)
    {
        $_SESSION[$name] = $value;
    }

    /**
     * @param User $user
     * @param $key
     * @return void
     */
    public function setRememberMeCookie(User $user, $key)
    {
        $time = time() + self::REMEMBER_ME_EXPIRATION; // 30 days;
        $cookie_value = $user->Id . $key;
        $baseUrl = $this->config->getBaseUrl();
        $url = parse_url($baseUrl);
        $cookie_name = self::REMEMBER_ME_PREFIX . $url['host'];
        $https = $this->config->get('CDASH_USE_HTTPS');

        // This hack will prevent the xsrf possible with this cookie
        // @reference https://stackoverflow.com/a/46971326/1373710
        $path = "{$url['path']}; samesite=strict";
        // $name, $value, $expire, $path, $domain, $secure, $httponly

        if ($user->SetCookieKey($key)) {
            $this->system->setcookie($cookie_name, $cookie_value, $time, $path, $url['host'], $https, true);
        }
    }
}
