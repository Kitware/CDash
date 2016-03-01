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

$NoXSLGenerate = 1;

include("index.php");

@$projectname = $_GET["project"];

if (!isset($projectname)) {
    $xml = generate_index_table();

    generate_XSLT($xml, 'indextable');
} else {
    $projectname = htmlspecialchars(pdo_real_escape_string($projectname));
    $projectid = get_project_id($projectname);
    @$date = $_GET["date"];
    if ($date != null) {
        $date = htmlspecialchars(pdo_real_escape_string($date));
    }

    // Check if the project has any subproject
    $Project = new Project();
    $Project->Id = $projectid;
    $Project->Fill();

    $xml = generate_main_dashboard_XML($Project, $date);

    generate_XSLT($xml, 'viewIssues');
}
