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
