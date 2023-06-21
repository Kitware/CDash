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

// No project name set.
if (!isset($_GET['project'])) {
    $default_project = config('cdash.default_project');
    $url = $default_project ? "index.php?project={$default_project}" : 'projects';
    return \redirect()->away($url);
}

require_once dirname(__DIR__) . '/config/config.php';
include_once 'include/common.php';
load_view('index');
