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

require_once(dirname(dirname(__DIR__))."/config/config.php");
$NoXSLGenerate = 1;
include("public/user.php");

if (empty($xml)) {
    $xml = begin_XML_for_XSLT();
    $xml .= add_XML_value("showlogin", "1");
    $xml .= "</cdash>";
}

// Now doing the xslt transition
generate_XSLT($xml, "user");
