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

    private $system;

    /**
     * Session constructor.
     * @param System $system
     */
    public function __construct(System $system)
    {
        $this->system = $system;
    }

    /**
     * @param $cache_policy
     */
    public function start($cache_policy)
    {
        $lifetime = config('session.lifetime');
        $maxlife = $lifetime + self::EXTEND_GC_LIFETIME;

        $this->system->session_name('CDash');
        $this->system->session_cache_limiter($cache_policy);
        $this->system->session_set_cookie_params($lifetime);
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
        return session($path);
    }

    /**
     * @param $path
     * @param $value
     * @return void
     */
    public function setSessionVar($path, $value)
    {
        session([$path => $value]);
    }

    public function getStatus()
    {
        return $this->system->session_status();
    }

    public function isActive()
    {
        return $this->getStatus() === PHP_SESSION_ACTIVE;
    }

    public function writeClose()
    {
        $this->system->session_write_close();
    }
}
