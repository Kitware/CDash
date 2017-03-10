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

$noforcelogin = 1;

include dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';
include 'include/common.php';
include 'include/version.php';
include 'models/user.php';

$xml = begin_XML_for_XSLT();
$xml .= add_XML_value('title', 'CDash');
$xml .= '<hostname>' . $_SERVER['SERVER_NAME'] . '</hostname>';
$xml .= '<date>' . date('r') . '</date>';

$xml .= '<dashboard>
<title>CDash - Error</title>
<subtitle></subtitle>
<googletracker>' . $CDASH_DEFAULT_GOOGLE_ANALYTICS . '</googletracker>';
if (isset($CDASH_NO_REGISTRATION) && $CDASH_NO_REGISTRATION == 1) {
    $xml .= add_XML_value('noregister', '1');
}
$xml .= '</dashboard> ';

// User
$userid = 0;
if (isset($_SESSION['cdash'])) {
    $userid = $_SESSION['cdash']['loginid'];
    $user = new User();
    $user->Id = $userid;
    $user->Fill();

    $xml .= '<user>';
    $xml .= add_XML_value('id', $userid);
    $xml .= add_XML_value('admin', $user->Admin);
    $xml .= '</user>';
}

$xml .= add_XML_value('error', $_COOKIE['cdash_error']);
$xml .= '</cdash>';

// Now doing the xslt transition
generate_XSLT($xml, 'error');
