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
require_once 'include/common.php';
require_once 'include/api_common.php';

use App\Models\User;
use CDash\Database;

$build = get_request_build();

if ($build === null || !can_administrate_project($build->ProjectId)) {
    echo 'Not a valid buildid!';
    return;
}
$buildid = intval($build->Id);

$db = Database::getInstance();

// Find the notes
$note = $db->executePrepared('SELECT * FROM buildnote WHERE buildid=? ORDER BY timestamp ASC', [$buildid]);
foreach ($note as $note_array) {
    /** @var User $user */
    $user = User::where('id', intval($note_array['userid']))->first();
    $timestamp = strtotime($note_array['timestamp'] . ' UTC');
    switch (intval($note_array['status'])) {
        case 0:
            echo '<b>[note] </b>';
            break;
        case 1:
            echo '<b>[fix in progress] </b>';
            break;
        case 2:
            echo '<b>[fixed] </b>';
            break;
    }
    echo 'by <b>' . htmlspecialchars($user->getFullNameAttribute()) . '</b>' . ' (' . date('H:i:s T', $timestamp) . ')';
    echo '<pre>' . substr($note_array['note'], 0, 100) . '</pre>'; // limit 100 chars
}
