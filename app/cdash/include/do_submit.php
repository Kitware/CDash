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

//error_reporting(0); // disable error reporting
use App\Jobs\ProcessSubmission;
use App\Services\ProjectPermissions;
use CDash\Config;
use CDash\Model\AuthToken;
use CDash\Model\Build;
use CDash\Model\BuildFile;
use CDash\Model\PendingSubmissions;
use CDash\Model\Project;
use CDash\Model\Repository;
use CDash\Model\Site;
use CDash\ServiceContainer;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;

require_once 'include/ctestparser.php';
include_once 'include/common.php';
include 'include/sendemail.php';

/**
 * Given a filename, query the CDash API for its contents and return
 * a read-only file handle.
 * This is useful for workers running on other machines that need access to build xml.
 **/
function fileHandleFromSubmissionId($filename)
{
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $_t = tempnam(Storage::path('inbox'), 'cdash-submission-');
    $tmpFilename = "{$_t}.{$ext}";
    rename($_t, $tmpFilename);

    $client = new GuzzleHttp\Client();
    $response = $client->request('GET',
                                 config('app.url') . '/api/v1/getSubmissionFile.php',
                                 array('query' => array('filename' => $filename),
                                       'save_to' => $tmpFilename));

    if ($response->getStatusCode() === 200) {
        // @todo I'm sure Guzzle can be used to return a file handle from the stream, but for now
        // I'm just creating a temporary file with the output
        return fopen($tmpFilename, 'r');
    } else {
        // Log the status code and build submission UUID (404 means it's already been processed)
        add_log('Failed to retrieve a file handle from filename ' .
                $filename . '(' . (string) $response->getStatusCode() . ')',
                'fileHandleFromSubmissionId', LOG_WARNING);
        return false;
    }
}

function getSubmissionFileHandle($fileHandleOrSubmissionId)
{
    if (is_resource($fileHandleOrSubmissionId)) {
        return $fileHandleOrSubmissionId;
    } elseif (Storage::exists($fileHandleOrSubmissionId)) {
        return fopen(Storage::path($fileHandleOrSubmissionId), 'r');
    } elseif (is_string($fileHandleOrSubmissionId) && config('cdash.remote_workers')) {
        return fileHandleFromSubmissionId($fileHandleOrSubmissionId);
    } else {
        add_log('Failed to get a file handle for submission (was type ' . gettype($fileHandleOrSubmissionId) . ')',
                'getSubmissionFileHandle', LOG_ERR);
        return false;
    }
}

function curl_request($request)
{
    $use_https = Config::getInstance()->get('CDASH_USE_HTTPS');
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        if ($use_https) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        curl_exec($ch);
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $options = array('http' => array('timeout' => 1.0));
        if ($use_https) {
            $options['ssl'] = array('verify_peer' => false);
        }
        $context = stream_context_create($options);
        $data = file_get_contents($request, false, $context);
        if ($data === false) {
            add_log('Error for request ' . $request, LOG_ERR);
            return false;
        }
    } else {
        add_log('Your PHP installation does not support cURL. Please install the cURL extension.', LOG_ERR);
        return false;
    }
    return true;
}

/**
 * This method could be running on a worker that is either remote or local, so it accepts
 * a file handle or a filename that it can query the CDash API for.
 **/
function do_submit($fileHandleOrSubmissionId, $projectid, $buildid = null,
                   $expected_md5 = '')
{
    $config = Config::getInstance();
    $filehandle = getSubmissionFileHandle($fileHandleOrSubmissionId);

    if ($filehandle === false) {
        // Logs will have already captured this issue at this point
        return false;
    }

    // We find the daily updates
    // If we have php curl we do it asynchronously
    $baseUrl = get_server_URI(false);
    $request = $baseUrl . '/ajax/dailyupdatescurl.php?projectid=' . $projectid;

    if (config('cdash.daily_updates') && curl_request($request) === false) {
        return false;
    }

    // Parse the XML file
    $handler = ctest_parse($filehandle, $projectid, $buildid, $expected_md5);
    fclose($filehandle);
    unset($filehandle);

    //this is the md5 checksum fail case
    if ($handler == false) {
        //no need to log an error since ctest_parse already did
        return false;
    }

    $build = get_build_from_handler($handler);
    if (!is_null($build)) {
        $pendingSubmissions = new PendingSubmissions();
        $pendingSubmissions->Build = $build;
        if ($pendingSubmissions->Exists()) {
            $pendingSubmissions->Decrement();
        }
    }

    // Set status on repository.
    if ($handler instanceof UpdateHandler ||
        $handler instanceof BuildPropertiesJSONHandler
    ) {
        Repository::setStatus($build, false);
    }

    // Send emails about update problems.
    if ($handler instanceof UpdateHandler) {
        send_update_email($handler, $projectid);
    }

    // Send more general build emails.
    if ($handler instanceof TestingHandler ||
        $handler instanceof BuildHandler ||
        $handler instanceof ConfigureHandler ||
        $handler instanceof DynamicAnalysisHandler ||
        $handler instanceof UpdateHandler
    ) {
        sendemail($handler, $projectid);
    }

    return $handler;
}

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

function get_build_from_handler($handler)
{
    $build = null;
    $builds = $handler->getBuilds();
    if (count($builds) > 1) {
        // More than one build referenced by the handler.
        // Return the parent build.
        $build = new Build();
        $build->Id = $builds[0]->GetParentId();
    } elseif (count($builds) === 1 && $builds[0] instanceof Build) {
        $build = $builds[0];
    }
    return $build;
}
