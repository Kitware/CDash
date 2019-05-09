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

use CDash\Config;

// No project name set.
if (!isset($_GET['project'])) {
    $default_project = Config::getInstance()->get('CDASH_DEFAULT_PROJECT');
    if ($default_project) {
        // Go to the default project if one is set.
        header("Location: index.php?project=$default_project");
    } else {
        // Otherwise display the table of projects.
        header('Location: viewProjects.php');
    }
    exit;
}

include_once 'include/common.php';
load_view('index');
