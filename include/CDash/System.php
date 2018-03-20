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


class System
{
    public function ini_set($varname, $newvalue)
    {
        ini_set($varname, $newvalue);
    }

    public function session_name($name = null)
    {
        session_name($name);
    }

    public function session_cache_limiter($cache_limiter = null)
    {
        session_cache_limiter($cache_limiter);
    }

    public function session_set_cookie_params($lifetime, $path = null, $secure = false, $httponly = false)
    {
        session_set_cookie_params($lifetime, $path, $secure, $httponly);
    }

    public function session_start($options = [])
    {
        session_start($options);
    }
}
