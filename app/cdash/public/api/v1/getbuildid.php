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

namespace CDash\Api\v1\GetBuildID;

require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Database;

$project = htmlspecialchars($_GET['project'] ?? '');
$site = htmlspecialchars($_GET['site'] ?? '');
$siteid = intval($_GET['siteid'] ?? 0);
$stamp = htmlspecialchars($_GET['stamp'] ?? '');
$name = htmlspecialchars($_GET['name'] ?? '');

$projectid = get_project_id($project);

$xml = '<?xml version="1.0" encoding="UTF-8"?>';
$xml .= '<buildid>';

if (!is_numeric($projectid)) {
    $xml .= 'not found</buildid>';
    return response($xml, 404)->header('Content-Type', 'application/xml');
}

$db = Database::getInstance();

if (!array_key_exists('siteid', $_GET)) {
    $site_array = $db->executePreparedSingleRow('SELECT id FROM site WHERE name=?', [$site]);
    if (!empty($site_array)) {
        $siteid = intval($site_array['id']);
    }
}

$build_array = $db->executePreparedSingleRow('
                  SELECT id
                  FROM build
                  WHERE
                      siteid=?
                      AND projectid=?
                      AND name=?
                      AND stamp=?
              ', [$siteid, $projectid, $name, $stamp]);

if (!empty($build_array)) {
    $buildid = intval($build_array['id']);
    $xml .= $buildid . '</buildid>';
    return response($xml, 400)->header('Content-Type', 'application/xml');
}

$xml .= 'not found</buildid>';
return response($xml, 404)->header('Content-Type', 'application/xml');
