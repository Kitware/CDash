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

require_once dirname(__DIR__) . '/config/config.php';
include_once 'include/common.php';

if (isset($_GET['logout'])) {
    // User requested logout.
    require_once 'include/login_functions.php';
    logout();
    return \redirect('viewProjects.php');
}

angular_login();

use CDash\Config;

$config = Config::getInstance();
$loginerror = $config->get('loginerror');
if ($loginerror != '') {
    // Display error on login page.
    require_once('include/login_functions.php');
    LoginForm($loginerror);
    exit;
}

load_view('user', false);
