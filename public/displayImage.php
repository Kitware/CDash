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

include dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';

use CDash\Model\Image;

if (array_key_exists('imgid', $_GET)) {
    $imgid = $_GET['imgid'];
}
// Checks
if (empty($imgid) || !is_numeric($imgid)) {
    echo 'Not a valid imgid!';
    return;
}

$image = new Image();
$image->Id = $imgid;
$image->Load();

switch ($image->Extension) {
    case 'image/jpg':
        header('Content-type: image/jpeg');
        break;
    case 'image/jpeg':
        header('Content-type: image/jpeg');
        break;
    case 'image/gif':
        header('Content-type: image/gif');
        break;
    case 'image/png':
        header('Content-type: image/png');
        break;
    default:
        echo "Unknown image type: $image->Extension";
        return;
}
echo $image->Data;
