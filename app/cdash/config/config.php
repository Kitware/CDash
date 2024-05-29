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

/** WARNING: It's recommended to edit the existing .env file and leave
 * this file as is.*/

// This file is 'config.php', in the directory 'config', in the root.
// Therefore, the root of the CDash source tree on the web server is:

include_once dirname(__FILE__) . '/../bootstrap/cdash_autoload.php';

$CDASH_ROOT_DIR = str_replace('\\', '/', dirname(dirname(__FILE__)));
set_include_path(get_include_path() . PATH_SEPARATOR . $CDASH_ROOT_DIR);
