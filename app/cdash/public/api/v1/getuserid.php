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

namespace CDash\Api\v1\GetUserID;

require_once 'include/common.php';
require_once 'include/api_common.php';
require_once 'include/pdo.php';

use App\Models\User;
use CDash\Model\Project;
use CDash\Database;
use Illuminate\Support\Facades\Auth;

$userid = Auth::id();
// Check for authenticated user.
if (!$userid) {
    return;
}

$xml = '<?xml version="1.0" encoding="UTF-8"?>';
$xml .= '<userid>';

if (!isset($_GET['author'])) {
    $xml .= 'error<no-author-param/></userid>';
    return response($xml, 400)->header('Content-Type', 'application/xml');
}

if (strlen($_GET['author']) == 0) {
    $xml .= 'error<empty-author-param/></userid>';
    return response($xml, 400)->header('Content-Type', 'application/xml');
}

$author = htmlspecialchars(pdo_real_escape_string($_GET['author']));

// First, try the simplest query, where the author string is simply exactly
// equal to the user's email:
//
$user = User::where('email', $author)->first();
if ($user) {
    $xml .= $user->id . '</userid>';
    return response($xml, 200)->header('Content-Type', 'application/xml');
}

// If no exact email match, fall back to the more complicated project-based
// repository credentials lookup:
//
if (!isset($_GET['project'])) {
    $xml .= 'error<no-project-param/></userid>';
    return response($xml, 400)->header('Content-Type', 'application/xml');
}

if (strlen($_GET['project']) == 0) {
    $xml .= 'error<empty-project-param/></userid>';
    return response($xml, 400)->header('Content-Type', 'application/xml');
}

$project = new Project();
$projectname = htmlspecialchars(pdo_real_escape_string($_GET['project']));
if (!$project->FindByName($projectname) || !can_access_project(get_project_id($projectname))) {
    $xml .= 'error<no-such-project/></userid>';
    return response($xml, 404)->header('Content-Type', 'application/xml');
}

$db = Database::getInstance();
$userarray = $db->executePreparedSingleRow('
                 SELECT up.userid
                 FROM user2project AS up, user2repository AS ur
                 WHERE
                     ur.userid=up.userid
                     AND up.projectid=?
                     AND ur.credential=?
                     AND (
                         ur.projectid=?
                         OR ur.projectid=0
                     )
             ', [intval($project->Id), $author, intval($project->Id)]);

if (!empty($userarray)) {
    $userid = $userarray['userid'];
    $xml .= $userid . '</userid>';
    return response($xml, 200)->header('Content-Type', 'application/xml');
}

$xml .= 'not found<no-such-user/></userid>';
return response($xml, 404)->header('Content-Type', 'application/xml');
