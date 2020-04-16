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

use App\Models\User;

$buildid = pdo_real_escape_numeric($_GET['buildid']);
if (!isset($buildid) || !is_numeric($buildid)) {
    echo 'Not a valid buildid!';
    return;
}

// Find the notes
$note = pdo_query("SELECT * FROM buildnote WHERE buildid='$buildid' ORDER BY timestamp ASC");
while ($note_array = pdo_fetch_array($note)) {
    $user = User::where('id', $note_array['userid']);
    $timestamp = strtotime($note_array['timestamp'] . ' UTC');
    switch ($note_array['status']) {
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
    echo 'by <b>' . $user->firstname . ' ' . $user->lastname . '</b>' . ' (' . date('H:i:s T', $timestamp) . ')';
    echo '<pre>' . substr($note_array['note'], 0, 100) . '</pre>'; // limit 100 chars
}
