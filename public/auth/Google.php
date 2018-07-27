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

require_once dirname(dirname(__DIR__)) . '/config/config.php';

use CDash\Config;
use CDash\Controller\Auth\Session;
use CDash\Middleware\OAuth2\Google;
use CDash\Model\User;
use CDash\ServiceContainer;
use CDash\System;

$config = Config::getInstance();
$service = ServiceContainer::getInstance();
$session = $service->get(Session::class);
$system = $service->get(System::class);

$user = $service->create(User::class);
$provider = new Google($system, $session, $config);
$provider->auth($user);
