<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
$noforcelogin = 1;

include("cdash/config.php");
require_once("cdash/pdo.php");
include("cdash/common.php");
include('cdash/version.php');

$xml = begin_XML_for_XSLT();
$xml .= add_XML_value("title", "CDash");
$xml .= "<hostname>".$_SERVER['SERVER_NAME']."</hostname>";
$xml .= "<date>".date("r")."</date>";

$xml .= "<dashboard>
<title>CDash - Error</title>
<subtitle></subtitle>
<googletracker>".$CDASH_DEFAULT_GOOGLE_ANALYTICS."</googletracker>";
if (isset($CDASH_NO_REGISTRATION) && $CDASH_NO_REGISTRATION==1) {
    $xml .= add_XML_value("noregister", "1");
}
$xml .= "</dashboard> ";

// User
$userid = 0;
if (isset($_SESSION['cdash'])) {
    $xml .= "<user>";
    $userid = $_SESSION['cdash']['loginid'];
    $user = pdo_query("SELECT admin FROM ".qid("user")." WHERE id='$userid'");
    $user_array = pdo_fetch_array($user);
    $xml .= add_XML_value("id", $userid);
    $xml .= add_XML_value("admin", $user_array["admin"]);
    $xml .= "</user>";
}

$xml .= add_XML_value("error", $_COOKIE['cdash_error']);
$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml, "error");
