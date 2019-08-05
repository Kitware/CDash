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

use CDash\Config;

// Return a suitable key for use with memcache based on a page name
function cdash_memcache_key($page_name)
{
    return implode(':', [Config::getInstance()->get('CDASH_MEMCACHE_PREFIX'),
                              $page_name,
                              md5(serialize($_REQUEST))]);
}

function cdash_memcache_connect($server, $port)
{
    if (class_exists('Memcached')) {
        $memcached = new Memcached();
        $memcached->setOption(Memcached::OPT_COMPRESSION, true);

        if (Config::getInstance()->get('CDASH_USE_ELASTICACHE_AUTO_DISCOVERY')) {
            $memcached->setOption(Memcached::OPT_CLIENT_MODE, Memcached::DYNAMIC_CLIENT_MODE);
        }
        if ($memcached->addServer($server, $port) !== false) {
            return $memcached;
        }
    }
    return false;
}

function cdash_memcache_get($memcached, $key)
{
    if ($memcached === false) {
        return false;
    }
    return $memcached->get($key);
}

function cdash_memcache_set($memcached, $key, $var, $expire)
{
    if ($memcached === false) {
        return false;
    }
    return $memcached->set($key, $var, $expire);
}
