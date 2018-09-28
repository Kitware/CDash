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
use Bernard\Message\PlainMessage;
use Bernard\Producer;
use Bernard\QueueFactory\PersistentFactory;
use Bernard\Serializer;
use CDash\Config;
use CDash\Middleware\Queue;
use CDash\Middleware\Queue\DriverFactory as QueueDriverFactory;
use CDash\Middleware\Queue\SubmissionService;
use CDash\Model\AuthToken;
use CDash\Model\Build;
use CDash\Model\BuildFile;
use CDash\Model\Project;
use CDash\Model\Site;
use Symfony\Component\EventDispatcher\EventDispatcher;

include 'include/ctestparser.php';
include_once 'include/common.php';
include_once 'include/createRSS.php';
include 'include/sendemail.php';

/**
 * Given a filename, query the CDash API for its contents and return
 * a read-only file handle.
 * This is useful for workers running on other machines that need access to build xml.
 **/
function fileHandleFromSubmissionId($filename)
{
    $config = Config::getInstance();

    $tmpFilename = tempnam($config->get('CDASH_BACKUP_DIRECTORY'), 'cdash-submission-');
    $client = new GuzzleHttp\Client();
    $response = $client->request('GET',
                                 $config->get('CDASH_BASE_URL') . '/api/v1/getSubmissionFile.php',
                                 array('query' => array('filename' => $filename),
                                       'save_to' => $tmpFilename));

    if ($response->getStatusCode() === 200) {
        // @todo I'm sure Guzzle can be used to return a file handle from the stream, but for now
        // I'm just creating a temporary file with the output
        return fopen($tmpFilename, 'r');
    } else {
        // Log the status code and build submission UUID (404 means it's already been processed)
        add_log('Failed to retrieve a file handle from build UUID ' .
                $submissionId . '(' . (string) $response->getStatusCode() . ')',
                'fileHandleFromSubmissionId', LOG_WARNING);
        return false;
    }
}

function getSubmissionFileHandle($fileHandleOrSubmissionId)
{
    if (is_resource($fileHandleOrSubmissionId)) {
        return $fileHandleOrSubmissionId;
    } elseif (is_string($fileHandleOrSubmissionId)) {
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
                   $expected_md5 = '', $do_checksum = true, $submission_id = 0)
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

    if ($config->get('CDASH_DAILY_UPDATES') && curl_request($request) === false) {
        return false;
    }

    if ($config->get('CDASH_USE_LOCAL_DIRECTORY') && file_exists('local/submit.php')) {
        include 'local/submit.php';
    }

    $scheduleid = 0;
    if ($submission_id !== 0) {
        $row = pdo_single_row_query(
            "SELECT scheduleid from client_jobschedule2submission WHERE submissionid=$submission_id");
        if (!empty($row)) {
            $scheduleid = $row[0];
        }
    } elseif (isset($_GET['clientscheduleid'])) {
        $scheduleid = pdo_real_escape_numeric($_GET['clientscheduleid']);
    }

    // Parse the XML file
    $handler = ctest_parse($filehandle, $projectid, $buildid, $expected_md5, $do_checksum, $scheduleid);
    //this is the md5 checksum fail case
    if ($handler == false) {
        //no need to log an error since ctest_parse already did
        return false;
    }

    // Send the emails if necessary
    if ($handler instanceof UpdateHandler) {
        send_update_email($handler, $projectid);
        sendemail($handler, $projectid);
    }
    if ($handler instanceof TestingHandler ||
        $handler instanceof BuildHandler ||
        $handler instanceof ConfigureHandler ||
        $handler instanceof DynamicAnalysisHandler
    ) {
        sendemail($handler, $projectid);
    }

    if ($config->get('CDASH_ENABLE_FEED') && !$config->get('CDASH_BERNARD_SUBMISSION')) {
        // Create the RSS feed
        CreateRSSFeed($projectid);
    }
}

/** Asynchronous submission */
function do_submit_asynchronous($filehandle, $projectid, $buildid = null,
                                $expected_md5 = '')
{
    include 'include/version.php';
    $config = Config::getInstance();

    do {
        $filename = $config->get('CDASH_BACKUP_DIRECTORY') . '/' . mt_rand() . '.xml';
        $fp = @fopen($filename, 'x');
    } while (!$fp);
    fclose($fp);
    unset($fp);

    $outfile = fopen($filename, 'w');

    // Save the file in the backup directory
    while (!feof($filehandle)) {
        $content = fread($filehandle, 8192);
        if (fwrite($outfile, $content) === false) {
            echo "ERROR: Cannot write to file ($filename)";
            add_log("Cannot write to file ($filename)", 'do_submit_asynchronous',
                LOG_ERR, $projectid);
            fclose($outfile);
            unset($outfile);
            return;
        }
    }
    fclose($outfile);
    unset($outfile);

    // Sends the file size to the local parser
    if ($config->get('CDASH_USE_LOCAL_DIRECTORY') && file_exists('local/ctestparser.php')) {
        require_once 'local/ctestparser.php';
        $localParser = new LocalParser();
        $filesize = filesize($filename);
        $localParser->SetFileSize($projectid, $filesize);
    }

    $md5sum = md5_file($filename);
    $md5error = false;

    echo "<cdash version=\"{$config->get('CDASH_VERSION')}\">\n";
    if ($expected_md5 == '' || $expected_md5 == $md5sum) {
        echo "  <status>OK</status>\n";
        echo "  <message></message>\n";
    } else {
        echo "  <status>ERROR</status>\n";
        echo "  <message>Checksum failed for file.  Expected $expected_md5 but got $md5sum.</message>\n";
        $md5error = true;
    }
    if (!is_null($buildid)) {
        echo " <buildId>$buildid</buildId>\n";
    }
    echo "  <md5>$md5sum</md5>\n";
    echo "</cdash>\n";

    if ($md5error) {
        add_log("Checksum failure on file: $filename", 'do_submit_asynchronous',
            LOG_ERR, $projectid);
        return;
    }

    $bytes = filesize($filename);

    // Insert the filename in the database
    $now_utc = gmdate(FMT_DATETIMESTD);
    pdo_query('INSERT INTO submission (filename,projectid,status,attempts,filesize,filemd5sum,created) ' .
        "VALUES ('" . $filename . "','" . $projectid . "','0','0','$bytes','$md5sum','$now_utc')");

    // Get the ID associated with this submission.  We may need to reference it
    // later if this is a CDash@home (client) submission.
    $submissionid = pdo_insert_id('submission');

    // We find the daily updates
    $currentURI = $config->getBaseUrl();
    $request = $currentURI . '/ajax/dailyupdatescurl.php?projectid=' . $projectid;

    if ($config->get('CDASH_DAILY_UPDATES') && curl_request($request) === false) {
        return;
    }

    $clientscheduleid = isset($_GET['clientscheduleid']) ? pdo_real_escape_numeric($_GET['clientscheduleid']) : 0;
    if ($clientscheduleid !== 0) {
        pdo_query('INSERT INTO client_jobschedule2submission (scheduleid,submissionid) ' .
            "VALUES ('$clientscheduleid','$submissionid')");
    }

    // Save submitter IP in the database in the async case, so we have a valid
    // IP at Site::Insert time when processing rather than 'localhost's IP:
    pdo_insert_query('INSERT INTO submission2ip (submissionid, ip) ' .
        "VALUES ('$submissionid', '" . $_SERVER['REMOTE_ADDR'] . "')");

    // Call process submissions via cURL.
    trigger_process_submissions($projectid);
}

/** Function to deal with the external tool mechanism */
function post_submit()
{
    // We expect POST to contain the following values.
    $vars = array('project', 'build', 'stamp', 'site', 'track', 'type', 'starttime', 'endtime', 'datafilesmd5');
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
        return;
    }

    $buildname = htmlspecialchars(pdo_real_escape_string($_POST['build']));
    $buildstamp = htmlspecialchars(pdo_real_escape_string($_POST['stamp']));
    $sitename = htmlspecialchars(pdo_real_escape_string($_POST['site']));
    $track = htmlspecialchars(pdo_real_escape_string($_POST['track']));
    $type = htmlspecialchars(pdo_real_escape_string($_POST['type']));
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

    // Do not process this submission if the project has too many builds.

    $project = new Project();
    $project->Name = $projectname;
    $project->Id = $projectid;
    $message = '';
    if ($project->HasTooManyBuilds($message)) {
        $response_array['status'] = 1;
        $response_array['description'] = $message;
        echo json_encode($response_array);
        return;
    }

    // Check if we have the CDash@Home scheduleid
    $scheduleid = 0;
    if (isset($_POST['clientscheduleid'])) {
        $scheduleid = pdo_real_escape_numeric($_POST['clientscheduleid']);
    }

    // Add the build
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
        $buildid = add_build($build, $scheduleid);
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

    // Verify buildid.
    $buildid = pdo_real_escape_numeric($_GET['buildid']);
    if (!is_numeric($_GET['buildid']) || $buildid < 1) {
        $response_array['status'] = 1;
        $response_array['description'] = 'Variable \'buildid\' is not numeric.';
        echo json_encode($response_array);
        return;
    }

    $buildfile = new BuildFile();
    $buildfile->BuildId = $buildid;
    $buildfile->Type = htmlspecialchars(pdo_real_escape_string($_GET['type']));
    $buildfile->md5 = htmlspecialchars(pdo_real_escape_string($_GET['md5']));
    $buildfile->Filename = htmlspecialchars(pdo_real_escape_string($_GET['filename']));
    $buildfile->Insert();

    // Get the ID of the project associated with this build.
    $row = pdo_single_row_query(
        "SELECT p.id, p.authenticatesubmissions
        FROM project p
        JOIN build b on b.projectid = p.id
        WHERE b.id = $buildid");
    if (empty($row)) {
        $response_array['status'] = 1;
        $response_array['description'] = "Cannot find projectid for build #$buildid";
        echo json_encode($response_array);
        return;
    }
    $projectid = $row['id'];

    // Check if this submission requires a valid authentication token.
    if ($row['authenticatesubmissions'] && !valid_token_for_submission($projectid)) {
        return;
    }

    // Begin writing this file to the backup directory.
    $uploadDir = $config->get('CDASH_BACKUP_DIRECTORY');
    $ext = pathinfo($buildfile->Filename, PATHINFO_EXTENSION);
    $filename = $uploadDir . '/' . $buildid . '_' . $buildfile->md5
        . ".$ext";

    if (!$handle = fopen($filename, 'w')) {
        $response_array['status'] = 1;
        $response_array['description'] = "Cannot open file ($filename)";
        echo json_encode($response_array);
        return;
    }

    // Read the data 1 KB at a time and write to the file.
    $putdata = fopen('php://input', 'r');
    while ($data = fread($putdata, 1024)) {
        fwrite($handle, $data);
    }
    // Close the streams.
    fclose($handle);
    fclose($putdata);

    // Check that the md5sum of the file matches what we were expecting.
    $md5sum = md5_file($filename);
    if ($md5sum != $buildfile->md5) {
        $response_array['status'] = 1;
        $response_array['description'] =
            "md5 mismatch. expected: $buildfile->md5, received: $md5sum";
        unlink($filename);
        $buildfile->Delete();
        echo json_encode($response_array);
        return;
    }

    if ($config->get('CDASH_BERNARD_COVERAGE_SUBMISSION')) {
        $driver = QueueDriverFactory::create();
        $queue = new Queue($driver);
        $message = SubmissionService::createMessage([
            'file' => $filename,
            'project' => $projectid,
            'md5' => $md5sum,
            'checksum' => true,
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
        $queue->produce($message);
    } elseif ($config->get('CDASH_ASYNCHRONOUS_SUBMISSION')) {
        // Create a new entry in the submission table for this file.
        $bytes = filesize($filename);
        $now_utc = gmdate(FMT_DATETIMESTD);
        pdo_query('INSERT INTO submission (filename,projectid,status,attempts,filesize,filemd5sum,created) ' .
            "VALUES ('$filename','$projectid','0','0','$bytes','$buildfile->md5','$now_utc')");

        // Trigger the processing loop in case it's not already running.
        trigger_process_submissions($projectid);
    } else {
        // synchronous processing.
        $handle = fopen($filename, 'r');
        do_submit($handle, $projectid, null, $buildfile->md5, false);

        // The file is given a more appropriate name during do_submit, so we can
        // delete the old file now.
        if (is_file($filename)) {
            unlink($filename);
        }
    }

    // Returns the OK submission
    $response_array['status'] = 0;

    echo json_encode($response_array);
}

// Used for parallel requests to processubmissions.php
// Adapted from a comment found here:
// stackoverflow.com/questions/962915/how-do-i-make-an-asynchronous-get-request-in-php
function curl_request_async($url, $params, $type = 'POST')
{
    foreach ($params as $key => &$val) {
        if (is_array($val)) {
            $val = implode(',', $val);
        }
        $post_params[] = $key . '=' . urlencode($val);
    }
    $post_string = implode('&', $post_params);

    $parts = parse_url($url);

    switch ($parts['scheme']) {
        case 'https':
            $scheme = 'ssl://';
            $port = 443;
            break;
        case 'http':
        default:
            $scheme = '';
            $port = 80;
    }

    $fp = fsockopen($scheme . $parts['host'], $port, $errno, $errstr, 30);

    // Data goes in the path for a GET request
    if ('GET' == $type) {
        $parts['path'] .= '?' . $post_string;
    }

    $out = "$type " . $parts['path'] . " HTTP/1.1\r\n";
    $out .= 'Host: ' . $parts['host'] . "\r\n";
    if ('POST' == $type && isset($post_string)) {
        // Data goes in the request body for a POST request.
        $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $out .= 'Content-Length: ' . strlen($post_string) . "\r\n";
        $out .= $post_string;
    }
    $out .= "Connection: Close\r\n\r\n";

    fwrite($fp, $out);
    fclose($fp);
}

// Trigger processsubmissions.php using cURL.
function trigger_process_submissions($projectid)
{
    $config = Config::getInstance();
    $currentURI = $config->getBaseUrl();
    $async_workers = $config->get('CDASH_ASYNC_WORKERS');

    if ($async_workers > 1) {
        // Parallel processing.
        // Obtain the processing lock before firing off parallel workers.
        $mypid = getmypid();
        include 'include/submission_functions.php';
        if (AcquireProcessingLock($projectid, false, $mypid)) {
            $url = $currentURI . '/ajax/processsubmissions.php';
            $params = array('projectid' => $projectid, 'pid' => $mypid);
            for ($i = 0; $i < $async_workers; $i++) {
                curl_request_async($url, $params, 'GET');
            }
        }
    } else {
        // Serial processing.
        $request = $currentURI . '/ajax/processsubmissions.php?projectid=' . $projectid;
        curl_request($request);
    }
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
    if (!checkUserPolicy($userid, $projectid, 1)) {
        http_response_code(403);
        return false;
    }

    return true;
}
