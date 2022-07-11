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
    public $buildid;
    public $type;
    public $md5;
    public $backupfilename;
    public $inboxdatafilename;
    public $token;
    public $build;
    public $project;

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

        $this->buildid = -1;
        $this->type = '';
        $this->md5 = '';
        $this->backupfilename = '';
        $this->inboxdatafilename = '';
        $this->token = '';

        $this->build = null;
        $this->project = null;
    }

    // Handle the initial (POST) request for an unparsed submission.
    public function postSubmit()
    {
        if (!$this->parseBuildMetadata()) {
            return false;
        }

        if (!$this->checkDatabaseConnection()) {
            // Write input parameters to disk (to be parsed later) if the database
            // is unavailable.
            $uuid = \Illuminate\Support\Str::uuid()->toString();
            $this->serializeBuildMetadata($uuid);

            // Respond with success even though the database is down so that CTest will
            // proceed to upload the data file.
            $response_array['status'] = 0;
            $response_array['buildid'] = $uuid;
            $response_array['datafilesmd5'][] = 0;
            echo json_encode(cast_data_for_JSON($response_array));
        } else {
            return $this->initializeBuild();
        }
    }

    // Parse build metadata from POST request.
    public function parseBuildMetadata()
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

    // Initialize a build from previously parsed metadata.
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
        if ($this->token) {
            $authtoken->Hash = $this->token;
            if (!$authtoken->hashValidForProject($projectid)) {
                return response('Forbidden', Response::HTTP_FORBIDDEN);
            }
        } else {
            if ($project_row->authenticatesubmissions && !$authtoken->validForProject($projectid)) {
                return response('Forbidden', Response::HTTP_FORBIDDEN);
            }
        }

        // Remove some old builds if the project has too many.
        $this->project = new Project();
        $this->project->Name = $this->projectname;
        $this->project->Id = $projectid;
        $this->project->CheckForTooManyBuilds();

        // Add the build.
        $this->build = new Build();

        $this->build->ProjectId = get_project_id($this->projectname);
        $this->build->StartTime = gmdate(FMT_DATETIME, $this->starttime);
        $this->build->EndTime = gmdate(FMT_DATETIME, $this->endtime);
        $this->build->SubmitTime = gmdate(FMT_DATETIME);
        $this->build->Name = $this->buildname;
        $this->build->InsertErrors = false; // we have no idea if we have errors at this point
        $this->build->SetStamp($this->buildstamp);

        // Get the site id
        $site = new Site();
        $site->Name = $this->sitename;
        $site->Insert();
        $this->build->SiteId = $site->Id;

        // Make this an "append" build, so that it doesn't result in a separate row
        // from the rest of the "normal" submission.
        $this->build->Append = true;

        // TODO: Check for labels and other optional metadata.
        if ($this->generator) {
            $this->build->Generator = $this->generator;
        }

        if ($this->subprojectname) {
            $this->build->SetSubProject($this->subprojectname);
        }

        // Check if this build already exists.
        $buildid = $this->build->GetIdFromName($this->subprojectname);

        // If not, add a new one.
        if ($buildid === 0) {
            $buildid = add_build($this->build);
        }

        // Returns the OK submission
        $response_array['status'] = 0;
        $response_array['buildid'] = $buildid;
        $response_array['datafilesmd5'][] = 0;

        echo json_encode(cast_data_for_JSON($response_array));
    }

    // Handle the subsequent (PUT) where the data file is actually uploaded.
    public function putSubmitFile()
    {
        $response_array = ['status' => 0];
        $this->parseDataFileParameters();
        $ext = pathinfo($this->backupfilename, PATHINFO_EXTENSION);

        $db_up = $this->checkDatabaseConnection();
        if ($db_up) {
            if (!is_numeric($this->buildid) || $this->buildid < 1) {
                return response('Build not found', Response::HTTP_NOT_FOUND);
            }
            // Get the relevant build and project.
            $this->build = new Build();
            $this->build->Id = $this->buildid;
            $this->build->FillFromId($this->build->Id);
            if (!$this->build->Exists()) {
                return response('Build not found', Response::HTTP_NOT_FOUND);
            }
            $this->project = $this->build->GetProject();
            $this->project->Fill();
            $this->projectname = $this->project->Name;
            $this->inboxdatafilename = "inbox/{$this->projectname}_{$this->type}_{$this->buildid}_{$this->md5}_.$ext";
        } else {
            // Get project name from build metadata file on disk.
            $projectname = null;
            foreach (Storage::files('inbox') as $inboxFile) {
                $filename = str_replace('inbox/', '', $inboxFile);
                $pos = strpos($filename, "_build_metadata_{$this->buildid}");
                if ($pos === false) {
                    continue;
                }
                $pos = strpos($filename, '_');
                if ($pos === false) {
                    \Log::info("Could not extract projectname from $filename for {$this->buildid}");
                    continue;
                }
                $projectname = substr($filename, 0, $pos);
                break;
            }
            if (is_null($projectname)) {
                \Log::info("Could not find build metadata file for {$this->buildid}");
                return response('Build not found', Response::HTTP_NOT_FOUND);
            }
            $this->projectname = $projectname;
            $this->inboxdatafilename = "inbox/{$this->projectname}_{$this->type}_{$this->buildid}_{$this->md5}_.$ext";
            $this->serializeDataFileParameters();
        }

        // Write this file to the inbox directory.
        $handle = request()->getContent(true);
        if (!Storage::put($this->inboxdatafilename, $handle)) {
            $response_array['status'] = 1;
            $response_array['description'] = "Cannot open file ($this->inboxdatafilename)";
            echo json_encode($response_array);
            return;
        }

        if (!$db_up) {
            // At this point we've stored the data file in the inbox directory.
            // We can't do much else if we don't have a database connection.
            echo json_encode($response_array);
            return;
        }

        if ($this->populateBuildFileRow()) {
            $filename = str_replace('inbox/', '', $this->inboxdatafilename);
            ProcessSubmission::dispatch($filename, $this->project->Id, $this->build->Id, $this->md5);
        }
    }

    public function populateBuildFileRow()
    {
        // Populate a BuildFile object.
        $buildfile = new BuildFile();
        $buildfile->BuildId = $this->build->Id;
        $buildfile->Type = $this->type;
        $buildfile->md5 = $this->md5;
        $buildfile->Filename = $this->backupfilename;

        $response_array = ['status' => 0];
        // TODO: think about whether these checks can go somewhere else.
        if (!$this->project->Exists()) {
            Storage::delete($this->inboxdatafilename);
            return response('Project not found', Response::HTTP_NOT_FOUND);
        }

        // Check if this submission requires a valid authentication token.
        $authtoken = new AuthToken();
        if ($this->project->AuthenticateSubmissions && !$authtoken->validForProject($this->project->Id)) {
            Storage::delete($this->inboxdatafilename);
            return response('Forbidden', Response::HTTP_FORBIDDEN);
        }

        // Check that the md5sum of the file matches what we were expecting.
        $md5sum = md5_file(Storage::path($this->inboxdatafilename));
        if ($md5sum != $this->md5) {
            $response_array['status'] = 1;
            $response_array['description'] =
                "md5 mismatch. expected: {$this->md5}, received: $md5sum";
            Storage::delete($this->inboxdatafilename);
            $buildfile->Delete();
            echo json_encode($response_array);
            return false;
        }
        // endthink

        // Insert the buildfile row.
        // TODO: make sure this uses PDO.
        $buildfile->Insert();

        // Increment the count of pending submission files for this build.
        $pendingSubmissions = new PendingSubmissions();
        $pendingSubmissions->Build = $this->build;
        if (!$pendingSubmissions->Exists()) {
            $pendingSubmissions->NumFiles = 0;
            $pendingSubmissions->Save();
        }
        $pendingSubmissions->Increment();

        // Returns the OK submission
        echo json_encode($response_array);
        return true;
    }

    public function parseDataFileParameters()
    {
        // We expect GET to contain the following values:
        $vars = ['buildid', 'type', 'md5', 'filename'];
        foreach ($vars as $var) {
            if (!isset($_GET[$var]) || empty($_GET[$var])) {
                $response_array['status'] = 1;
                $response_array['description'] = 'Variable \'' . $var . '\' not set but required.';
                echo json_encode($response_array);
                return;
            }
        }

        $this->buildid = $_GET['buildid'];
        $this->type = htmlspecialchars($_GET['type']);
        $this->md5 = htmlspecialchars($_GET['md5']);
        $this->backupfilename = htmlspecialchars($_GET['filename']);
    }

    // Check if CDash's database is down.
    private function checkDatabaseConnection()
    {
        try {
            $pdo = \DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // Write build metadata to disk in JSON format.
    private function serializeBuildMetadata($uuid)
    {
        $build_metadata = [
            'projectname' => $this->projectname,
            'buildname' => $this->buildname,
            'buildstamp' => $this->buildstamp,
            'sitename' => $this->sitename,
            'starttime' => $this->starttime,
            'endtime' => $this->endtime,
            'generator' => $this->generator,
            'subprojectname' => $this->subprojectname,
        ];

        $token = AuthToken::getBearerToken();
        if ($token) {
            $build_metadata['token'] = AuthToken::HashToken($token);
        } else {
            $build_metadata['token'] = '';
        }

        $build_metadata_filename = "{$this->projectname}_build_metadata_{$uuid}__.json";
        $inbox_build_metadata_filename = "inbox/{$build_metadata_filename}";
        Storage::put($inbox_build_metadata_filename, json_encode($build_metadata));
    }

    // Append data file parameters to the build metadata JSON file.
    private function serializeDataFileParameters()
    {
        $inbox_filename = "inbox/{$this->projectname}_build_metadata_{$this->buildid}__.json";
        if (!Storage::exists($inbox_filename)) {
            \Log::warn("Could not find build metadata file {$inbox_filename}");
            return false;
        }

        $contents = Storage::get($inbox_filename);
        $build_metadata = json_decode($contents, true);
        if (!$build_metadata) {
            \Log::warn("Failed to parse build metadata JSON {$inbox_filename}");
            return false;
        }

        $build_metadata['buildid'] = $this->buildid;
        $build_metadata['type'] = $this->type;
        $build_metadata['md5'] = $this->md5;
        $build_metadata['backupfilename'] = $this->backupfilename;
        $build_metadata['inboxdatafilename'] = $this->inboxdatafilename;

        Storage::put($inbox_filename, json_encode($build_metadata));
    }

    // Deserialize a build metadata JSON file.
    public function deserializeBuildMetadata($fp)
    {
        $build_metadata = json_decode(stream_get_contents($fp), true);
        $this->projectname = $build_metadata['projectname'];
        $this->buildname = $build_metadata['buildname'];
        $this->buildstamp = $build_metadata['buildstamp'];
        $this->sitename = $build_metadata['sitename'];
        $this->starttime = $build_metadata['starttime'];
        $this->endtime = $build_metadata['endtime'];
        $this->generator = $build_metadata['generator'];
        $this->subprojectname = $build_metadata['subprojectname'];
        $this->token = $build_metadata['token'];
        $this->buildid = $build_metadata['buildid'];
        $this->type = $build_metadata['type'];
        $this->md5 = $build_metadata['md5'];
        $this->backupfilename = $build_metadata['backupfilename'];
        $this->inboxdatafilename = $build_metadata['inboxdatafilename'];
    }
}
