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

namespace CDash\Api\v1\HasFile;

require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Database;

$md5sums_get = isset($_GET['md5sums']) ? htmlspecialchars($_GET['md5sums']) : '';
if ($md5sums_get === '') {
    return response('md5sum not specified', 400);
}

$md5sums = preg_split('#[|.:,;]+#', $md5sums_get);

$db = Database::getInstance();
foreach ($md5sums as $md5sum) {
    if ($md5sum === '') {
        continue;
    }
    $result = $db->executePreparedSingleRow('SELECT id FROM filesum WHERE md5sum=?', [$md5sum]);
    //we don't have this file, add it to the list to send
    if (empty($result)) {
        return response($md5sum, 200);
    }
}
