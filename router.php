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
  include("cdash/config.php");
  require_once("cdash/pdo.php");
  include_once("cdash/common.php");
  include_once('cdash/version.php');

if (!empty($_GET['page'])) {
    $localphpfile = $_GET['page'].".php";
  
    if (file_exists($_GET['page'].".php")) {
        include($localphpfile);
    } else {
        include('local/'.$localphpfile);
    }
} else {
    header('location: index.php');
}
