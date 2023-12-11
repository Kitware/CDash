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
     * @param $exit_message
     * @return void
     * @deprecated 06/15/2023 Use abort() to exit cleanly instead.
     */
    public function system_exit($exit_message = '')
    {
        exit($exit_message);
    }
}
