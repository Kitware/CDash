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

require_once(dirname(dirname(__DIR__)) . "/config/config.php");
require_once("include/pdo.php");

@$buildid = $_GET["buildid"];
// Checks
if (!isset($buildid) || !is_numeric($buildid)) {
    echo "Not a valid buildid!";
    return;
}

$NoXSLGenerate = 1;
$FormatTextForIphone = 1;

@$buildid = $_GET["project"];
if ($buildid != null) {
    $buildid = pdo_real_escape_numeric($buildid);
}

@$date = $_GET["date"];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

include("public/buildSummary.php");

// Now doing the xslt transition
generate_XSLT($xml, "buildsummary");
