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

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

// --------------------------------------------------------------------------------------
// main
// --------------------------------------------------------------------------------------
// $mysession = ['login' => false, 'passwd' => false, 'ID' => false, 'valid' => false, 'langage' => false];

$session_OK = (int)Auth::check();

if (!$session_OK && !@$noforcelogin) {
    $errors = Collection::make([]);
    $js_version = \App\Http\Controllers\AbstractController::getJsVersion();
    echo view(
        'auth.login',
        [
            'title' => 'Login',
            'errors' => $errors,
            'js_version' => $js_version,
        ]
    )->render();
    return;
}
