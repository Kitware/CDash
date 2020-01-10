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
require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\Project;
use CDash\Model\User;

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
$user = new User();
$userid = $user->GetIdFromEmail($author);
if ($userid) {
    $xml .= $userid . '</userid>';
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
if (!$project->FindByName($projectname)) {
    $xml .= 'error<no-such-project/></userid>';
    return response($xml, 404)->header('Content-Type', 'application/xml');
}

$userquery = pdo_query("SELECT up.userid FROM user2project AS up,user2repository AS ur
                        WHERE ur.userid=up.userid
                          AND up.projectid='$project->Id'
                          AND ur.credential='$author'
                          AND (ur.projectid='$project->Id' OR ur.projectid=0)");

if (pdo_num_rows($userquery) > 0) {
    $userarray = pdo_fetch_array($userquery);
    $userid = $userarray['userid'];
    $xml .= $userid . '</userid>';
    return response($xml, 200)->header('Content-Type', 'application/xml');
}

$xml .= 'not found<no-such-user/></userid>';
return response($xml, 404)->header('Content-Type', 'application/xml');
