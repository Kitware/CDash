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
include 'public/login.php';
include_once 'include/common.php';
include 'include/version.php';

use CDash\Model\Project;
use CDash\Model\User;

@$projectid = $_GET['projectid'];
if ($projectid != null) {
    $projectid = pdo_real_escape_numeric($projectid);
}

// Checks if the project id is set
if (!isset($projectid) || !is_numeric($projectid)) {
    checkUserPolicy(@$_SESSION['cdash']['loginid'], 0);
} else {
    checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid);
}

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);

$userid = $_SESSION['cdash']['loginid'];
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
