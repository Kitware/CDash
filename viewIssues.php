<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id: index.php 1895 2009-05-15 04:21:03Z jjomier $
  Language:  PHP
  Date:      $Date: 2009-05-15 00:21:03 -0400 (Fri, 15 May 2009) $
  Version:   $Revision: 1895 $

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even 
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR 
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/

$NoXSLGenerate = 1;

include("index.php");

@$projectname = $_GET["project"];

if(!isset($projectname ))
  {
  $xml = generate_index_table();

  generate_XSLT($xml, 'indextable');
  }
else
  {
  $projectid = get_project_id($projectname);
  @$date = $_GET["date"];

  // Check if the project has any subproject 
  $Project = new Project();
  $Project->Id = $projectid;

  $xml = generate_main_dashboard_XML($projectid, $date);

  generate_XSLT($xml, 'viewIssues');
  }

?>
