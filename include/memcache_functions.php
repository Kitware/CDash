<?php

require_once 'config/config.php';

// Return a suitable key for use with memcache based on a page name
function cdash_memcache_key($page_name)
{
    global $CDASH_MEMCACHE_PREFIX;

    return implode(':', array($CDASH_MEMCACHE_PREFIX,
                              $page_name,
                              md5(serialize($_REQUEST))));
}
