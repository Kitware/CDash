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

namespace CDash;

/**
 * Class System
 * @package CDash
 */
class System
{
    /**
     * @param callable $process_name
     * @param array $args
     * @return mixed
     */
    final private static function call(callable $process_name, array $args)
    {
        $args = empty($args) ? $args : self::trimNullArguments($args);
        return call_user_func_array($process_name, $args);
    }

    /**
     * @param array $args
     * @return array
     */
    final private static function trimNullArguments(array $args)
    {
        $reversed = array_reverse($args);
        $length = count($args);

        foreach ($reversed as $arg) {
            if (is_null($arg)) {
                --$length;
                continue;
            }
            break;
        }

        return array_splice($args, 0, $length);
    }

    /**
     * @param $varname
     * @param $newvalue
     * @return void
     */
    public function ini_set($varname, $newvalue)
    {
        ini_set($varname, $newvalue);
    }

    /**
     * @param null $name
     * @return void
     */
    public function session_name($name = null)
    {
        self::call('session_name', [$name]);
    }

    /**
     * @param null $cache_limiter
     * @return void
     */
    public function session_cache_limiter($cache_limiter = null)
    {
        self::call('session_cache_limiter', [$cache_limiter]);
    }

    /**
     * @param $lifetime
     * @param $path
     * @param $domain
     * @param $secure
     * @param $httponly
     * @return void
     */
    public function session_set_cookie_params($lifetime)
    {
        session_set_cookie_params($lifetime);
    }

    /**
     * @param array $options
     * @return void
     */
    public function session_start($options = [])
    {
        self::call('session_start', $options);
    }

    /**
     * @return void
     */
    public function session_regenerate_id()
    {
        session_regenerate_id();
    }

    /**
     * @param null $id
     * @return string
     * @return void
     */
    public function session_id($id = null)
    {
        // This seems trivial, but it is definitely not
        if (is_null($id)) {
            return session_id();
        } else {
            return session_id($id);
        }
    }

    /**
     * @return void
     */
    public function session_destroy()
    {
        session_destroy();
    }

    /**
     * @param $name
     * @param $value
     * @param $expire
     * @param $path
     * @param $domain
     * @param $secure
     * @param $httponly
     * @return void
     */
    public function setcookie($name, $value, $expire, $path, $domain, $secure, $httponly)
    {
        setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }
}
