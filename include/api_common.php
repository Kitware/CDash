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

// Make sure this user has access to this project.
// Return true if the current user has access to view this project, or if
// the project is public (allows anonymous read access).
// Return false and respond with the correct HTTP status (401 or 403) if not.
function can_access_project($projectid)
{
    $response = array();
    $logged_in = false;
    $userid = '';

    $noforcelogin = 1;
    include 'public/login.php';
    if (isset($_SESSION['cdash']) && isset($_SESSION['cdash']['loginid'])) {
        $userid = $_SESSION['cdash']['loginid'];
        $logged_in = true;
    }
    if (!checkUserPolicy(@$_SESSION['cdash']['loginid'], $projectid, 1)) {
        if ($logged_in) {
            $response['error'] = 'You do not have permission to access this page.';
            echo json_encode($response);
            http_response_code(403);
        } else {
            $response['requirelogin'] = 1;
            echo json_encode($response);
            http_response_code(401);
        }
        return false;
    }
    return true;
}
