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
namespace CDash\Middleware\OAuth2;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use League\OAuth2\Client\Provider\AbstractProvider;

interface OAuth2Interface
{
    /**
     * @return AbstractProvider
     */
    public function getProvider();

    /**
     * @param AbstractProvider $provider
     * @return void
     */
    public function setProvider(AbstractProvider $provider);

    /**
     * @return Collection
     */
    public function getEmail();

    /**
     * @param Request $request
     * @return OAuth2Interface
     */
    public function setRequest(Request $request);

    /**
     * @return bool
     */
    public function checkState();

    /**
     * @return string
     */
    public function getAuthorizationUrl();

    /**
     * @return string
     */
    public function getState();

    /**
     * @return string
     */
    public function getOwnerName();
}
