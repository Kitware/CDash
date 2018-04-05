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

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once 'include/pdo.php';
require_once 'include/common.php';
include 'public/login.php';

use CDash\Model\Project;
use CDash\Model\SubProject;

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

@$projectname = $_GET['project'];
if ($projectname != null) {
    $projectname = htmlspecialchars(pdo_real_escape_string($projectname));
}

@$date = $_GET['date'];
if ($date != null) {
    $date = htmlspecialchars(pdo_real_escape_string($date));
}

$projectid = get_project_id($projectname);

if ($projectid == 0) {
    echo 'Invalid project';
    return;
}

$project = pdo_query("SELECT * FROM project WHERE id='$projectid'");
if (pdo_num_rows($project) > 0) {
    $project_array = pdo_fetch_array($project);
    $svnurl = make_cdash_url(htmlentities($project_array['cvsurl']));
    $homeurl = make_cdash_url(htmlentities($project_array['homeurl']));
    $bugurl = make_cdash_url(htmlentities($project_array['bugtrackerurl']));
    $googletracker = htmlentities($project_array['googletracker']);
    $docurl = make_cdash_url(htmlentities($project_array['documentationurl']));
    $projectpublic = $project_array['public'];
    $projectname = $project_array['name'];
} else {
    $projectname = 'NA';
}

checkUserPolicy(@$_SESSION['cdash']['loginid'], $project_array['id']);

$Project = new Project();
$Project->Id = $projectid;
$subprojectids = $Project->GetSubProjects();
sort($subprojectids);

$subproject_groups = array();
$groups = $Project->GetSubProjectGroups();
foreach ($groups as $group) {
    $subproject_groups[$group->GetId()] = $group;
}

$result = array(); # array to store the all the result
$subprojs = array();
foreach ($subprojectids as $subprojectid) {
    $SubProject = new SubProject();
    $SubProject->SetId($subprojectid);
    $subprojs[$subprojectid] = $SubProject;
}

foreach ($subprojectids as $subprojectid) {
    $SubProject = $subprojs[$subprojectid];
    $subarray = array('name' => $SubProject->GetName(), 'id' => $subprojectid);
    $groupid = $SubProject->GetGroupId();
    if ($groupid > 0) {
        $subarray['group'] = $subproject_groups[$groupid]->GetName();
    }
    $dependencies = $SubProject->GetDependencies($date);
    $deparray = array();
    foreach ($dependencies as $depprojid) {
        if (array_key_exists($depprojid, $subprojs)) {
            $deparray[] = $subprojs[$depprojid]->GetName();
        }
    }
    if (!empty($deparray)) {
        $subarray['depends'] = $deparray;
    }
    $result[] = $subarray;
}
echo json_encode(cast_data_for_JSON($result));
