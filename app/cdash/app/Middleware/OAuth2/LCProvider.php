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

use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use CDash\Middleware\OAuth2\LCResourceOwner;

class LCProvider extends GenericProvider{
    private $responseResourceOwnerId = 'id';

    protected function createResourceOwner(array $response, AccessToken $token){
        return new LCResourceOwner($response, $this->responseResourceOwnerId);
    }
}
