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
require_once 'include/autoremove.php';

if ($argc != 2) {
    echo "Usage: php $argv[0] <project_name>\n";
    echo "Or:    php $argv[0] all\n";
    return -1;
}

$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME", $db);
set_time_limit(0);

$projectname = $argv[1];
echo "removing builds for $projectname \n";
$sql = " WHERE name='" . $projectname . "'";
if ($projectname == 'all') {
    $sql = '';
}

$project = pdo_query('SELECT id,autoremovetimeframe,autoremovemaxbuilds FROM project' . $sql);
if (!$project) {
    add_last_sql_error('autoRemoveBuilds');
    return false;
}
while ($project_array = pdo_fetch_array($project)) {
    removeFirstBuilds($project_array['id'], $project_array['autoremovetimeframe'], $project_array['autoremovemaxbuilds'], true); // force the autoremove
    removeBuildsGroupwise($project_array['id'], $project_array['autoremovemaxbuilds'], true); // force the autoremove
}
return 0;
