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
require_once 'include/pdo.php';
include_once 'include/common.php';
$policy = checkUserPolicy(Auth::id(), 0);
if ($policy !== true) {
    return $policy;
}

include_once 'include/ctestparser.php';

@set_time_limit(0);

; // only admin
$xml = begin_XML_for_XSLT();
$xml .= '<title>CDash - Backup</title>';
$xml .= '<menutitle>CDash</menutitle>';
$xml .= '<menusubtitle>Backup</menusubtitle>';
$xml .= '<backurl>user.php</backurl>';
$xml .= '</cdash>';

// Now doing the xslt transition
generate_XSLT($xml, 'manageBackup');
