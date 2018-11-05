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
namespace CDash\Controller;

use CDash\Database;

/**
 * Parent class for all API controllers.
 **/
class Api
{
    const BEGIN_EPOCH = '1980-01-01 00:00:00';

    protected $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }
}
