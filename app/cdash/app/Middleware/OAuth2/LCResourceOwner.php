<?php
/*========================================================================i
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
namespace CDash\Middleware\OAuth2;

use League\OAuth2\Client\Provider\GenericResourceOwner;

class LCResourceOwner extends GenericResourceOwner{
    public function getName(){
        return $this->response['displayName'] ?: null;
    }
}
