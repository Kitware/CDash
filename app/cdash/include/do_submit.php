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

use App\Jobs\ProcessSubmission;
use App\Services\ProjectPermissions;
use CDash\Config;
use CDash\Model\AuthToken;
use CDash\Model\Build;
use CDash\Model\BuildFile;
use CDash\Model\PendingSubmissions;
use CDash\Model\Project;
use CDash\Model\Site;

use Symfony\Component\HttpFoundation\Response;

require_once 'include/common.php';

/** Function to deal with the external tool mechanism */
function post_submit()
{
    // We expect POST to contain the following values.
    $vars = ['project', 'build', 'stamp', 'site', 'starttime', 'endtime', 'datafilesmd5'];
    foreach ($vars as $var) {
        if (!isset($_POST[$var]) || empty($_POST[$var])) {
            $response_array['status'] = 1;
            $response_array['description'] = 'Variable \'' . $var . '\' not set but required.';
            echo json_encode($response_array);
            return;
        }
    }

    $projectname = htmlspecialchars(pdo_real_escape_string($_POST['project']));

    // Get the projectid.
    $row = pdo_single_row_query(
        "SELECT id, authenticatesubmissions FROM project WHERE name = '$projectname'");
    if (empty($row)) {
        $response_array['status'] = 1;
        $response_array['description'] = 'Project does not exist';
        http_response_code(400);
        echo json_encode($response_array);
        return;
    }
    $projectid = $row['id'];

    // Check if this submission requires a valid authentication token.
    if ($row['authenticatesubmissions'] && !valid_token_for_submission($projectid)) {
        return response('Forbidden', Response::HTTP_FORBIDDEN);
    }

    $buildname = htmlspecialchars(pdo_real_escape_string($_POST['build']));
    $buildstamp = htmlspecialchars(pdo_real_escape_string($_POST['stamp']));
    $sitename = htmlspecialchars(pdo_real_escape_string($_POST['site']));
    $starttime = htmlspecialchars(pdo_real_escape_string($_POST['starttime']));
    $endtime = htmlspecialchars(pdo_real_escape_string($_POST['endtime']));

    // Make sure this is a valid project.
    $projectid = get_project_id($projectname);
    if ($projectid == -1) {
        $response_array['status'] = 1;
        $response_array['description'] = 'Not a valid project.';
        echo json_encode($response_array);
        return;
    }

    // Remove some old builds if the project has too many.
    $project = new Project();
    $project->Name = $projectname;
    $project->Id = $projectid;
    $project->CheckForTooManyBuilds();

    // Add the build.
    $build = new Build();

    $build->ProjectId = get_project_id($projectname);
    $build->StartTime = gmdate(FMT_DATETIME, $starttime);
    $build->EndTime = gmdate(FMT_DATETIME, $endtime);
    $build->SubmitTime = gmdate(FMT_DATETIME);
    $build->Name = $buildname;
    $build->InsertErrors = false; // we have no idea if we have errors at this point
    $build->SetStamp($buildstamp);

    // Get the site id
    $site = new Site();
    $site->Name = $sitename;
    $site->Insert();
    $build->SiteId = $site->Id;

    // Make this an "append" build, so that it doesn't result in a separate row
    // from the rest of the "normal" submission.
    $build->Append = true;

    // TODO: Check the labels and generator and other optional
    if (isset($_POST['generator'])) {
        $build->Generator = htmlspecialchars(pdo_real_escape_string($_POST['generator']));
    }

    $subprojectname = '';
    if (isset($_POST['subproject'])) {
        $subprojectname = htmlspecialchars(pdo_real_escape_string($_POST['subproject']));
        $build->SetSubProject($subprojectname);
    }

    // Check if this build already exists.
    $buildid = $build->GetIdFromName($subprojectname);

    // If not, add a new one.
    if ($buildid === 0) {
        $buildid = add_build($build);
    }

    // Returns the OK submission
    $response_array['status'] = 0;
    $response_array['buildid'] = $buildid;

    // Tell CTest to continue with the upload of this file.
    foreach ($_POST['datafilesmd5'] as $md5) {
        $response_array['datafilesmd5'][] = 0;
    }
    echo json_encode(cast_data_for_JSON($response_array));
}

/** Function to deal with the external tool mechanism */
function put_submit_file()
{
    $config = Config::getInstance();
    // We expect GET to contain the following values:
    $vars = array('buildid', 'type');
    foreach ($vars as $var) {
        if (!isset($_GET[$var]) || empty($_GET[$var])) {
            $response_array['status'] = 1;
            $response_array['description'] = 'Variable \'' . $var . '\' not set but required.';
            echo json_encode($response_array);
            return;
        }
    }

    // Check for numeric buildid.
    $buildid = pdo_real_escape_numeric($_GET['buildid']);
    if (!is_numeric($_GET['buildid']) || $buildid < 1) {
        $response_array['status'] = 1;
        $response_array['description'] = 'Variable \'buildid\' is not numeric.';
        echo json_encode($response_array);
        return;
    }

    // Get the relevant build and project.
    $build = new Build();
    $build->Id = $buildid;
    $build->FillFromId($build->Id);
    if (!$build->Exists()) {
        return response('Build not found', Response::HTTP_NOT_FOUND);
    }
    $project = $build->GetProject();
    $project->Fill();
    if (!$project->Exists()) {
        return response('Project not found', Response::HTTP_NOT_FOUND);
    }

    // Check if this submission requires a valid authentication token.
    if ($project->AuthenticateSubmissions && !valid_token_for_submission($project->Id)) {
        return response('Forbidden', Response::HTTP_FORBIDDEN);
    }

    // Populate a BuildFile object.
    $buildfile = new BuildFile();
    $buildfile->BuildId = $build->Id;
    $buildfile->Type = htmlspecialchars(pdo_real_escape_string($_GET['type']));
    $buildfile->md5 = htmlspecialchars(pdo_real_escape_string($_GET['md5']));
    $buildfile->Filename = htmlspecialchars(pdo_real_escape_string($_GET['filename']));

    // Write this file to the inbox directory.
    $ext = pathinfo($buildfile->Filename, PATHINFO_EXTENSION);
    $filename = "{$project->Name}_{$build->Id}_{$buildfile->md5}.$ext";
    $inbox_filename = "inbox/{$filename}";
    $handle = request()->getContent(true);
    if (!Storage::put($inbox_filename, $handle)) {
        $response_array['status'] = 1;
        $response_array['description'] = "Cannot open file ($inbox_filename)";
        echo json_encode($response_array);
        return;
    }

    // Check that the md5sum of the file matches what we were expecting.
    $md5sum = md5_file(Storage::path($inbox_filename));
    if ($md5sum != $buildfile->md5) {
        $response_array['status'] = 1;
        $response_array['description'] =
            "md5 mismatch. expected: $buildfile->md5, received: $md5sum";
        Storage::delete($inbox_filename);
        $buildfile->Delete();
        echo json_encode($response_array);
        return;
    }

    // Insert the buildfile row.
    $buildfile->Insert();

    // Increment the count of pending submission files for this build.
    $pendingSubmissions = new PendingSubmissions();
    $pendingSubmissions->Build = $build;
    if (!$pendingSubmissions->Exists()) {
        $pendingSubmissions->NumFiles = 0;
        $pendingSubmissions->Save();
    }
    $pendingSubmissions->Increment();
    ProcessSubmission::dispatch($filename, $project->Id, $build->Id, $md5sum);

    // Returns the OK submission
    $response_array['status'] = 0;

    echo json_encode($response_array);
}

/**
 * Return true if the header contains a valid authentication token
 * for this project.  Otherwise return false and set the appropriate
 * response code.
 **/
function valid_token_for_submission($projectid)
{
    $authtoken = new AuthToken();
    $userid = $authtoken->getUserIdFromRequest();
    if (is_null($userid)) {
        http_response_code(401);
        return false;
    }

    // Make sure that the user associated with this token is allowed to access
    // the project in question.
    Auth::loginUsingId($userid);
    $project = new Project();
    $project->Id = $projectid;
    $project->Fill();
    if (ProjectPermissions::userCanViewProject($project)) {
        return true;
    }
    http_response_code(403);
    return false;
}
