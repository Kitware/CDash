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

use App\Http\Controllers\Auth\LoginController;
use CDash\Model\Project;
use CDash\Model\User;

@$date = $_GET['projectid'];
@$projectid = $_GET['projectid'];
if ($projectid != null) {
    $projectid = pdo_real_escape_numeric($projectid);
}

$policy_project_id = is_numeric($projectid) ? $projectid : 0;
$policy = checkUserPolicy(Auth::id(), $policy_project_id);

if ($policy !== true) {
    return $policy;
}

$userid = Auth::id();
$User = new User;
$User->Id = $userid;

$Project = new Project;
$role = 0;

if ($projectid) {
    $project = pdo_query("SELECT name FROM project WHERE id='$projectid'");
    if (pdo_num_rows($project) > 0) {
        $project_array = pdo_fetch_array($project);
        $projectname = $project_array['name'];
    }
    $Project->Id = $projectid;
    $role = $Project->GetUserRole($userid);
} else {
    $projectname = 'Global';
}

$xml = begin_XML_for_XSLT();
$xml .= '<title>Feed - ' . $projectname . '</title>';

$xml .= get_cdash_dashboard_xml(get_project_name($projectid), $date);

$sql = '';
if ($date) {
    $sql = "AND date>'" . $date . "'";
}

// Get the errors
$query = pdo_query('SELECT * FROM feed WHERE projectid=' . qnum($projectid) . ' ORDER BY id DESC');

while ($query_array = pdo_fetch_array($query)) {
    $xml .= '<feeditem>';
    $xml .= add_XML_value('date', $query_array['date']);
    $xml .= add_XML_value('buildid', $query_array['buildid']);
    $xml .= add_XML_value('type', $query_array['type']);
    $xml .= add_XML_value('description', $query_array['description']);
    $xml .= '</feeditem>';
}

$xml .= add_XML_value('admin', $User->IsAdmin());
$xml .= add_XML_value('role', $role);

$xml .= '</cdash>';

// Now doing the xslt transition
generate_XSLT($xml, 'viewFeed');
