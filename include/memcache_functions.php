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

require_once 'config/config.php';

// Return a suitable key for use with memcache based on a page name
function cdash_memcache_key($page_name)
{
    global $CDASH_MEMCACHE_PREFIX;

    return implode(':', array($CDASH_MEMCACHE_PREFIX,
                              $page_name,
                              md5(serialize($_REQUEST))));
}

function cdash_memcache_connect($server, $port)
{
    if (class_exists('Memcached')) {
        $memcache = new Memcached();
        $memcache->setOption(Memcached::OPT_COMPRESSION, true);
        global $CDASH_USE_ELASTICACHE_AUTO_DISCOVERY;
        if ($CDASH_USE_ELASTICACHE_AUTO_DISCOVERY) {
            $memcache->setOption(Memcached::OPT_CLIENT_MODE, Memcached::DYNAMIC_CLIENT_MODE);
        }
        if ($memcache->addServer($server, $port) !== false) {
            return $memcache;
        }
    } elseif (class_exists('Memcache')) {
        $memcache = new Memcache();
        if ($memcache->connect($server, $port) !== false) {
            return $memcache;
        }
    }
    return false;
}

function cdash_memcache_get($memcache, $key)
{
    if ($memcache === false) {
        return false;
    }
    return $memcache->get($key);
}

function cdash_memcache_set($memcache, $key, $var, $expire)
{
    if ($memcache instanceof Memcached) {
        return $memcache->set($key, $var, $expire);
    }
    if ($memcache instanceof Memcache) {
        return $memcache->set($key, $var, MEMCACHE_COMPRESSED, $expire);
    }
    return false;
}
