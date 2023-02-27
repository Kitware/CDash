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
use App\Models\User;
use App\Services\ProjectPermissions;

use CDash\Model\Banner;
use CDash\Model\Project;
use CDash\Database;
use Illuminate\Support\Facades\Auth;

if (Auth::check()) {
    $userid = Auth::id();
    // Checks
    if (!isset($userid) || !is_numeric($userid)) {
        echo 'Not a valid userid!';
        return;
    }

    /** @var User $user */
    $user = Auth::user();

    $xml = begin_XML_for_XSLT();
    $xml .= '<backurl>user.php</backurl>';
    $xml .= '<title>CDash - Manage Banner</title>';
    $xml .= '<menutitle>CDash</menutitle>';
    $xml .= '<menusubtitle>Banner</menusubtitle>';

    @$projectid = $_GET['projectid'];
    if ($projectid != null) {
        $projectid = intval($projectid);
    }

    if (empty($projectid)) {
        $projectid = 0;
    }

    $project = new Project;

    // If the projectid is not set and there is only one project we go directly to the page
    if (isset($edit) && !isset($projectid)) {
        $projectids = $project->GetIds();
        if (count($projectids) == 1) {
            $projectid = $projectids[0];
        }
    }

    $project->Id = $projectid;

    if (!ProjectPermissions::userCanEditProject($user, $project)) {
        echo "You don't have the permissions to access this page";
        return;
    }

    // If user is admin then we can add a banner for all projects
    if ($user->IsAdmin() == true) {
        $xml .= '<availableproject>';
        $xml .= add_XML_value('id', '0');
        $xml .= add_XML_value('name', 'All');
        if ($projectid == 0) {
            $xml .= add_XML_value('selected', '1');
        }
        $xml .= '</availableproject>';
    }

    $sql = 'SELECT id, name FROM project';
    $params = [];
    if (!$user->IsAdmin()) {
        $sql .= " WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid=? AND role>0)";
        $params[] = intval($userid);
    }

    $db = Database::getInstance();
    $projects = $db->executePrepared($sql, $params);
    foreach ($projects as $project_array) {
        $xml .= '<availableproject>';
        $xml .= add_XML_value('id', $project_array['id']);
        $xml .= add_XML_value('name', $project_array['name']);
        if ($project_array['id'] == $projectid) {
            $xml .= add_XML_value('selected', '1');
        }
        $xml .= '</availableproject>';
    }

    $Banner = new Banner();
    $Banner->SetProjectId($projectid);

    // If submit has been pressed
    @$updateMessage = $_POST['updateMessage'];
    if (isset($updateMessage)) {
        $Banner->SetText(htmlspecialchars($_POST['message']));
    }

    /* We start generating the XML here */
    // List the available project
    if ($projectid >= 0) {
        $xml .= '<project>';
        $xml .= add_XML_value('id', $project->Id);
        $xml .= add_XML_value('text', $Banner->GetText());

        if ($projectid > 0) {
            $xml .= add_XML_value('name', $project->GetName());
            $xml .= add_XML_value('name_encoded', urlencode($project->GetName()));
        }
        $xml .= add_XML_value('id', $project->Id);
        $xml .= '</project>';
    }

    $xml .= '</cdash>';

    // Now doing the xslt transition
    generate_XSLT($xml, 'manageBanner');
} else {
    return LoginController::staticShowLoginForm();
}
