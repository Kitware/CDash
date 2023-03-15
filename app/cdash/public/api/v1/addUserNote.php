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

namespace CDash\Api\v1\AddUserNote;

require_once 'include/pdo.php';
require_once 'include/api_common.php';

use CDash\Model\BuildUserNote;

init_api_request();
$response = array();

$userid = get_userid_from_session();
if (is_null($userid)) {
    return;
}

$build = get_request_build();

if (is_null($build)) {
    return;
}

if (!isset($_REQUEST['AddNote']) || !isset($_REQUEST['Status']) ||
        strlen($_REQUEST['AddNote']) < 1 ||  strlen($_REQUEST['Status']) < 1) {
    $response['error'] = 'No note specified';
    json_error_response($response, 400);
    return;
}

// Add the note.
$userNote = new BuildUserNote();
$userNote->BuildId = $build->Id;
$userNote->UserId = $userid;
$userNote->Note = $_REQUEST['AddNote'];
$userNote->Status = $_REQUEST['Status'];
$userNote->TimeStamp = gmdate(FMT_DATETIME);

if (!$userNote->Insert()) {
    $response['error'] = 'Error adding note';
    echo json_encode($response);
    http_response_code(400);
}

$response['note'] = $userNote->marshal();
echo json_encode(cast_data_for_JSON($response));
