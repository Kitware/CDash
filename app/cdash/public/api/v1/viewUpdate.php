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

namespace CDash\Api\v1\ViewUpdate;

require_once 'include/pdo.php';
require_once 'include/common.php';
require_once 'include/api_common.php';
require_once 'include/repository.php';

use App\Services\PageTimer;
use App\Services\TestingDay;

use CDash\Model\BuildUpdate;
use CDash\Database;
use CDash\Model\Project;
use CDash\Model\Site;
use Illuminate\Support\Facades\Auth;

$pageTimer = new PageTimer();
$build = get_request_build();
if (is_null($build)) {
    return;
}

$db = Database::getInstance();

$project = new Project();
$project->Id = $build->ProjectId;
$project->Fill();

$date = TestingDay::get($project, $build->StartTime);
$response = begin_JSON_response();
get_dashboard_JSON($project->Name, $date, $response);
$response['title'] = "$project->Name : Update";

// Menu
$menu_response = [];
$menu_response['back'] = "index.php?project=$project->Name&date=$date";

$previous_buildid = $build->GetPreviousBuildId();
$current_buildid = $build->GetCurrentBuildId();
$next_buildid = $build->GetNextBuildId();

if ($previous_buildid > 0) {
    $menu_response['previous'] = "viewUpdate.php?buildid=$previous_buildid";
} else {
    $menu_response['previous'] = false;
}

$menu_response['current'] = "viewUpdate.php?buildid=$current_buildid";

if ($next_buildid > 0) {
    $menu_response['next'] = "viewUpdate.php?buildid=$next_buildid";
} else {
    $menu_response['next'] = false;
}
$response['menu'] = $menu_response;

// Build
$site = new Site();
$site->Id = $build->SiteId;
$site_name = $site->GetName();

$build_response = [];
$build_response['site'] = $site_name;
$build_response['siteid'] = $site->Id;
$build_response['buildname'] = $build->Name;
$build_response['buildid'] = $build->Id;
$build_response['buildtime'] = date('D, d M Y H:i:s T', strtotime($build->StartTime . ' UTC'));
$response['build'] = $build_response;

// Update
$update = new BuildUpdate();
$update->BuildId = $build->Id;
$update->FillFromBuildId();

$update_response = [];
if (strlen($update->Status) > 0 && $update->Status != '0') {
    $update_response['status'] = $update->Status;
} else {
    $update_response['status'] = ''; // empty status
}
$update_response['revision'] = $update->Revision;
$update_response['priorrevision'] = $update->PriorRevision;
$update_response['path'] = $update->Path;
$update_response['revisionurl'] =
    get_revision_url($project->Id, $update->Revision, $update->PriorRevision);
$update_response['revisiondiff'] =
    get_revision_url($project->Id, $update->PriorRevision, ''); // no prior prior revision...
$response['update'] = $update_response;
if (!function_exists('sort_array_by_directory')) {
    function sort_array_by_directory($a, $b)
    {
        return $a > $b ? 1 : 0;
    }
}

if (!function_exists('sort_array_by_filename')) {
    function sort_array_by_filename($a, $b)
    {
        // Extract directory
        $filenamea = $a['filename'];
        $filenameb = $b['filename'];
        return $filenamea > $filenameb ? 1 : 0;
    }
}
$directoryarray = [];
$updatearray1 = [];
// Create an array so we can sort it
foreach ($update->GetFiles() as $update_file) {
    $file = [];
    $file['filename'] = $update_file->Filename;
    $file['author'] = $update_file->Author;
    $file['status'] = $update_file->Status;

    // Only display email if the user is logged in.
    if (Auth::check()) {
        if ($update_file->Email == '') {
            // Try to find author email from repository credentials.
            $stmt = $db->prepare("
                SELECT email FROM user WHERE id IN (
                  SELECT up.userid FROM user2project AS up, user2repository AS ur
                   WHERE ur.userid=up.userid
                   AND up.projectid=:projectid
                   AND ur.credential=:author
                   AND (ur.projectid=0 OR ur.projectid=:projectid) )
                   LIMIT 1");
            $stmt->bindParam(':projectid', $project->Id);
            $stmt->bindParam(':author', $file['author']);
            $db->execute($stmt);
            $file['email'] = $stmt ? $stmt->fetchColumn() : '';
        } else {
            $file['email'] = $update_file->Email;
        }
    } else {
        $file['email'] = '';
    }

    $file['log'] = $update_file->Log;
    $file['revision'] = $update_file->Revision;
    $updatearray1[] = $file;
    $directoryarray[] = substr($update_file->Filename, 0, strrpos($update_file->Filename, '/'));
}

$directoryarray = array_unique($directoryarray);
usort($directoryarray, 'sort_array_by_directory');
usort($updatearray1, 'sort_array_by_filename');

$updatearray = [];

foreach ($directoryarray as $directory) {
    foreach ($updatearray1 as $update) {
        $filename = $update['filename'];
        if (substr($filename, 0, strrpos($filename, '/')) == $directory) {
            $updatearray[] = $update;
        }
    }
}

// These variables represent a list of directories that contain a list of files.
$updated_files = [];
$modified_files = [];
$conflicting_files = [];

$num_updated_files = 0;
$num_modified_files = 0;
$num_conflicting_files = 0;

// Local function to reduce copy/pasted code in the loop below.
// It adds a file to one of the above data structures, creating the
// directory if it does not exist yet.
if (!function_exists('add_file')) {
    function add_file($file, $directory, &$list_of_files)
    {
        $idx = array_search($directory, array_column($list_of_files, 'name'));
        if ($idx === false) {
            $d = [];
            $d['name'] = $directory;
            $d['files'] = [$file];
            $list_of_files[] = $d;
        } else {
            $list_of_files[$idx]['files'][] = $file;
        }
    }
}

foreach ($updatearray as $file) {
    $filename = $file['filename'];
    $filename = str_replace('\\', '/', $filename);
    $directory = substr($filename, 0, strrpos($filename, '/'));

    $pos = strrpos($filename, '/');
    if ($pos !== false) {
        $filename = substr($filename, $pos + 1);
    }

    $baseurl = $project->BugTrackerFileUrl;
    if (empty($baseurl)) {
        $baseurl = $project->BugTrackerUrl;
    }

    $log = $file['log'];
    $status = $file['status'];
    $revision = $file['revision'];
    $log = str_replace("\r", ' ', $log);
    $log = str_replace("\n", ' ', $log);
    // Do this twice so that <something> ends up as
    // &amp;lt;something&amp;gt; because it gets sent to a
    // java script function not just displayed as html
    $log = XMLStrFormat($log); // Apparently no need to do this twice anymore
    $log = XMLStrFormat($log);

    $log = trim($log);

    $file['log'] = $log;
    $file['filename'] = $filename;
    $file['bugid'] = '0';
    $file['bugpos'] = '0';
    // This field is redundant because of the way our data is organized.
    unset($file['status']);

    if ($status == 'UPDATED') {
        $diff_url = get_diff_url($project->Id, $project->CvsUrl, $directory, $filename, $revision);
        $diff_url = XMLStrFormat($diff_url);
        $file['diffurl'] = $diff_url;
        add_file($file, $directory, $updated_files);
        $num_updated_files++;
    } elseif ($status == 'MODIFIED') {
        $diff_url = get_diff_url($project->Id, $project->CvsUrl, $directory, $filename);
        $diff_url = XMLStrFormat($diff_url);
        $file['diffurl'] = $diff_url;
        add_file($file, $directory, $modified_files);
        $num_modified_files++;
    } else {
        //CONFLICTED
        $diff_url = get_diff_url($project->Id, $project->CvsUrl, $directory, $filename);
        $diff_url = XMLStrFormat($diff_url);
        $file['diffurl'] = $diff_url;
        add_file($file, $directory, $conflicting_files);
        $num_conflicting_files++;
    }
}

$update_groups = [
    [
        'description' => "$project->Name Updated Files ($num_updated_files)",
        'directories' => $updated_files
    ],
    [
        'description' => "Modified Files ($num_modified_files)",
        'directories' => $modified_files
    ],
    [
        'description' => "Conflicting Files ($num_conflicting_files)",
        'directories' => $conflicting_files
    ]
];
$response['updategroups'] = $update_groups;

$pageTimer->end($response);
echo json_encode(cast_data_for_JSON($response));
