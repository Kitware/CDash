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

namespace App\Services;

use App\Jobs\ProcessSubmission;
use App\Services\ProjectPermissions;
use CDash\Config;
use CDash\Model\AuthToken;
use CDash\Model\Build;
use CDash\Model\BuildFile;
use CDash\Model\PendingSubmissions;
use CDash\Model\Project;
use CDash\Model\Site;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

require_once 'include/common.php';

/**
 * This class handles submissions that should be parsed on the server-side
 * by CDash. This is in contrast to the XML files that are typically generated
 * by CTest.
 **/
class UnparsedSubmissionProcessor
{
    public $projectname;
    public $buildname;
    public $buildstamp;
    public $sitename;
    public $starttime;
    public $endtime;
    public $generator;
    public $subprojectname;

    public function __construct()
    {
        $this->projectname = '';
        $this->buildname = '';
        $this->buildstamp = '';
        $this->sitename = '';
        $this->starttime = '';
        $this->endtime = '';
        $this->generator = '';
        $this->subprojectname = '';
    }

    // Handle the initial (POST) request for an unparsed submission.
    public function postSubmit()
    {
        if (!$this->parseInputParameters()) {
            return false;
        }

        return $this->initializeBuild();
    }

    // Parse build metadata from POST request.
    public function parseInputParameters()
    {
        // We require POST to contain the following values.
        $vars = ['project', 'build', 'stamp', 'site', 'starttime', 'endtime', 'datafilesmd5'];
        foreach ($vars as $var) {
            if (!isset($_POST[$var]) || empty($_POST[$var])) {
                $response_array['status'] = 1;
                $response_array['description'] = 'Variable \'' . $var . '\' not set but required.';
                echo json_encode($response_array);
                return false;
            }
        }

        $this->projectname = htmlspecialchars($_POST['project']);
        $this->buildname = htmlspecialchars($_POST['build']);
        $this->buildstamp = htmlspecialchars($_POST['stamp']);
        $this->sitename = htmlspecialchars($_POST['site']);
        $this->starttime = htmlspecialchars($_POST['starttime']);
        $this->endtime = htmlspecialchars($_POST['endtime']);
        $this->generator = '';
        if (isset($_POST['generator'])) {
            $this->generator = htmlspecialchars($_POST['generator']);
        }

        $this->subprojectname = '';
        if (isset($_POST['subproject'])) {
            $this->subprojectname = htmlspecialchars($_POST['subproject']);
        }

        return true;
    }

    // Initialize a build from POST metadata.
    public function initializeBuild()
    {
        // Retrieve this project from the database.
        $project_row = \DB::table('project')
            ->where('name', $this->projectname)
            ->first();

        if (!$project_row) {
            $response_array['status'] = 1;
            $response_array['description'] = 'Project does not exist';
            http_response_code(400);
            echo json_encode($response_array);
            return;
        }
        $projectid = $project_row->id;

        // Check if this submission requires a valid authentication token.
        $authtoken = new AuthToken();
        if ($project_row->authenticatesubmissions && !$authtoken->validForProject($projectid)) {
            return response('Forbidden', Response::HTTP_FORBIDDEN);
        }

        // Make sure this is a valid project.
        $projectid = get_project_id($this->projectname);
        if ($projectid == -1) {
            $response_array['status'] = 1;
            $response_array['description'] = 'Not a valid project.';
            echo json_encode($response_array);
            return;
        }

        // Remove some old builds if the project has too many.
        $project = new Project();
        $project->Name = $this->projectname;
        $project->Id = $projectid;
        $project->CheckForTooManyBuilds();

        // Add the build.
        $build = new Build();

        $build->ProjectId = get_project_id($this->projectname);
        $build->StartTime = gmdate(FMT_DATETIME, $this->starttime);
        $build->EndTime = gmdate(FMT_DATETIME, $this->endtime);
        $build->SubmitTime = gmdate(FMT_DATETIME);
        $build->Name = $this->buildname;
        $build->InsertErrors = false; // we have no idea if we have errors at this point
        $build->SetStamp($this->buildstamp);

        // Get the site id
        $site = new Site();
        $site->Name = $this->sitename;
        $site->Insert();
        $build->SiteId = $site->Id;

        // Make this an "append" build, so that it doesn't result in a separate row
        // from the rest of the "normal" submission.
        $build->Append = true;

        // TODO: Check for labels and other optional metadata.
        if ($this->generator) {
            $build->Generator = $this->generator;
        }

        if ($this->subprojectname) {
            $build->SetSubProject($this->subprojectname);
        }

        // Check if this build already exists.
        $buildid = $build->GetIdFromName($this->subprojectname);

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

    // Handle the subsequent (PUT) where the data file is actually uploaded.
    public function putSubmitFile()
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
        $authtoken = new AuthToken();
        if ($project->AuthenticateSubmissions && !$authtoken->validForProject($project->Id)) {
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
}
