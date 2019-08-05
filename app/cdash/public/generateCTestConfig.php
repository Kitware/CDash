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

use CDash\Model\Project;
use CDash\Model\SubProject;

@set_time_limit(0);

@$projectid = $_GET['projectid'];
if ($projectid != null) {
    $projectid = pdo_real_escape_numeric($projectid);
}

// Checks
if (!isset($projectid) || !is_numeric($projectid)) {
    echo 'Not a valid projectid!';
    return;
}

$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if (pdo_num_rows($project) == 0) {
    return;
}

$project_array = pdo_fetch_array($project);
$policy = checkUserPolicy(Auth::id(), $project_array['id']);

if ($policy !== true) {
    return $policy;
}

$ctestconfig = "## This file should be placed in the root directory of your project.\n";
$ctestconfig .= "## Then modify the CMakeLists.txt file in the root directory of your\n";
$ctestconfig .= "## project to incorporate the testing dashboard.\n";
$ctestconfig .= "##\n";
$ctestconfig .= "## # The following are required to submit to the CDash dashboard:\n";
$ctestconfig .= "##   ENABLE_TESTING()\n";
$ctestconfig .= "##   INCLUDE(CTest)\n";
$ctestconfig .= "\n";

$ctestconfig .= 'set(CTEST_PROJECT_NAME "' . $project_array['name'] . "\")\n";
$ctestconfig .= 'set(CTEST_NIGHTLY_START_TIME "' . $project_array['nightlytime'] . "\")\n\n";

$ctestconfig .= "set(CTEST_DROP_METHOD \"http\")\n";

$ctestconfig .= 'set(CTEST_DROP_SITE "' . $_SERVER['SERVER_NAME'] . "\")\n";

$currentURI = $_SERVER['REQUEST_URI'];
$currentURI = substr($currentURI, 0, strrpos($currentURI, '/'));

$ctestconfig .= 'set(CTEST_DROP_LOCATION "' . $currentURI . '/submit.php?project=' . urlencode($project_array['name']) . "\")\n";
$ctestconfig .= "set(CTEST_DROP_SITE_CDASH TRUE)\n";

// Add the subproject
$Project = new Project();
$Project->Id = $projectid;
$subprojectids = $Project->GetSubProjects();

function get_graph_depth($a, $value)
{
    $SubProject = new SubProject();
    $SubProject->SetId($a);
    $parents = $SubProject->GetDependencies();
    foreach ($parents as $parentid) {
        $subvalue = get_graph_depth($parentid, $value + 1);
        if ($subvalue > $value) {
            $value = $subvalue;
        }
    }
    return $value;
}

// Compare two subprojects to check the depth
function cmp($a, $b)
{
    $va = get_graph_depth($a, 0);
    $vb = get_graph_depth($b, 0);
    if ($va == $vb) {
        return 0;
    }
    return ($va < $vb) ? -1 : 1;
}

usort($subprojectids, 'cmp');

if (count($subprojectids) > 0) {
    $ctestconfig .= "\nset(CTEST_PROJECT_SUBPROJECTS\n";
}

foreach ($subprojectids as $subprojectid) {
    $SubProject = new SubProject();
    $SubProject->SetId($subprojectid);
    $ctestconfig .= $SubProject->GetName() . "\n";
}

if (count($subprojectids) > 0) {
    $ctestconfig .= ")\n";
}

return ['type' => 'text/plain', 'file' => $ctestconfig];
