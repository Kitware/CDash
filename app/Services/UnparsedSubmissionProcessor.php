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
use CDash\Model\Build;
use App\Models\BuildFile;
use CDash\Model\PendingSubmissions;
use CDash\Model\Project;
use App\Models\Site;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    /** Handle the initial (POST) request for an unparsed submission. */
    public function postSubmit(): JsonResponse
    {
        // Thus function will throw an exception if invalid data provided
        $this->parseBuildMetadata();

        if ($this->checkDatabaseConnection()) {
            return $this->initializeBuild();
        }

        // Write input parameters to disk (to be parsed later) if the database
        // is unavailable.
        $uuid = Str::uuid()->toString();
        $this->serializeBuildMetadata($uuid);

        // Write a marker file so we know to process these files when the DB comes back up.
        if (!Storage::exists("DB_WAS_DOWN")) {
            Storage::put("DB_WAS_DOWN", "");
        }

        // Respond with success even though the database is down so that CTest will
        // proceed to upload the data file.
        $response_array = [];
        $response_array['status'] = 0;
        $response_array['buildid'] = $uuid;
        $response_array['datafilesmd5'][] = 0;
        return response()->json(cast_data_for_JSON($response_array));
    }

    /** Parse build metadata from POST request. */
    public function parseBuildMetadata(): void
    {
        // We require POST to contain the following values.
        $vars = ['project', 'build', 'stamp', 'site', 'starttime', 'endtime', 'datafilesmd5'];
        foreach ($vars as $var) {
            if (empty($_POST[$var])) {
                abort(Response::HTTP_BAD_REQUEST, 'Variable \'' . $var . '\' not set but required.');
            }
        }

        if (!Project::validateProjectName(htmlspecialchars($_POST['project']))) {
            abort(Response::HTTP_BAD_REQUEST, 'Invalid project specified');
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

        $this->getAuthTokenHash();
    }

    /** Initialize a build from previously parsed metadata. */
    public function initializeBuild(): JsonResponse
    {
        // Retrieve this project from the database.
        $project_row = DB::table('project')
            ->where('name', $this->projectname)
            ->first();

        if (!$project_row) {
            abort(Response::HTTP_NOT_FOUND, 'Project does not exist');
        }
        $projectid = $project_row->id;

        // Check if this submission requires a valid authentication token.
        if (($this->token || $project_row->authenticatesubmissions) && !AuthTokenService::checkToken($this->token, $projectid)) {
            abort(Response::HTTP_FORBIDDEN, 'Forbidden');
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
        $this->build->SiteId = Site::firstOrCreate(['name' => $this->sitename])->id;

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
        $response_array = [];
        $response_array['status'] = 0;
        $response_array['buildid'] = $buildid;
        $response_array['datafilesmd5'][] = 0;

        return response()->json(cast_data_for_JSON($response_array));
    }

    /** Handle the subsequent (PUT) where the data file is actually uploaded. */
    public function putSubmitFile(): JsonResponse
    {
        $response_array = ['status' => 0];
        $this->parseDataFileParameters();
        $ext = pathinfo($this->backupfilename, PATHINFO_EXTENSION);

        $db_up = $this->checkDatabaseConnection();
        if ($db_up) {
            if (!is_numeric($this->buildid) || $this->buildid < 1) {
                abort(Response::HTTP_NOT_FOUND, 'Build not found');
            }
            // Get the relevant build and project.
            $this->build = new Build();
            $this->build->Id = $this->buildid;
            $this->build->FillFromId($this->build->Id);
            if (!$this->build->Exists()) {
                abort(Response::HTTP_NOT_FOUND, 'Build not found');
            }
            $this->project = $this->build->GetProject();
            $this->project->Fill();

            if (!Project::validateProjectName($this->project->Name)) {
                Log::info("Invalid project name: {$this->project->Name}");
                abort(Response::HTTP_BAD_REQUEST, 'Invalid project name.');
            }
            $this->projectname = $this->project->Name;

            $this->inboxdatafilename = "inbox/{$this->projectname}_-_{$this->token}_-_{$this->type}_-_{$this->buildid}_-_{$this->md5}_-_.$ext";
        } else {
            // Get project name from build metadata file on disk.
            $projectname = null;
            foreach (Storage::files('inbox') as $inboxFile) {
                $filename = str_replace('inbox/', '', $inboxFile);
                $pos = strpos($filename, "_-_build-metadata_-_{$this->buildid}");
                if ($pos === false) {
                    continue;
                }
                $pos = strpos($filename, '_-_');
                if ($pos === false) {
                    Log::info("Could not extract projectname from $filename for {$this->buildid}");
                    continue;
                }
                $projectname = substr($filename, 0, $pos);
                break;
            }
            if (is_null($projectname) || !Project::validateProjectName($projectname)) {
                Log::info("Could not find build metadata file for {$this->buildid}");
                abort(Response::HTTP_NOT_FOUND, 'Build not found');
            }
            $this->projectname = $projectname;
            $this->inboxdatafilename = "inbox/{$this->projectname}_-_{$this->token}_-_{$this->type}_-_{$this->buildid}_-_{$this->md5}_-_.$ext";
            $this->serializeDataFileParameters();

            if (!Storage::exists("DB_WAS_DOWN")) {
                Storage::put("DB_WAS_DOWN", "");
            }
        }

        // Write this file to the inbox directory.
        $handle = request()->getContent(true);
        if (!Storage::put($this->inboxdatafilename, $handle)) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, "Cannot open file ($this->inboxdatafilename)");
        }

        if (!$db_up) {
            // At this point we've stored the data file in the inbox directory.
            // We can't do much else if we don't have a database connection.
            return response()->json($response_array);
        }

        // THis function will throw an exception if invalid data provided
        $this->populateBuildFileRow();

        $filename = str_replace('inbox/', '', $this->inboxdatafilename);
        ProcessSubmission::dispatch($filename, $this->project->Id, $this->build->Id, $this->md5);

        // Check for marker file to see if we need to queue deferred submissions.
        if (Storage::exists("DB_WAS_DOWN")) {
            Storage::delete("DB_WAS_DOWN");
            Artisan::call('submission:queue');
        }

        return response()->json($response_array);
    }

    public function populateBuildFileRow(): void
    {
        // Populate a BuildFile object.
        $buildfile = BuildFile::firstOrNew([
            'buildid' => $this->build->Id,
            'type' => $this->type,
            'md5' => $this->md5,
            'filename' => $this->backupfilename,
        ]);

        if (!$this->project->Exists()) {
            Storage::delete($this->inboxdatafilename);
            abort(Response::HTTP_NOT_FOUND, 'Project not found');
        }

        // Check if this submission requires a valid authentication token.
        if ($this->project->AuthenticateSubmissions) {
            $token = AuthTokenService::getBearerToken();
            $authtoken_hash = AuthTokenService::hashToken($token);
            if (!AuthTokenService::checkToken($authtoken_hash, $this->project->Id)) {
                Storage::delete($this->inboxdatafilename);
                abort(Response::HTTP_FORBIDDEN, 'Forbidden');
            }
        }

        // Check that the md5sum of the file matches what we were expecting.
        $md5sum = md5_file(Storage::path($this->inboxdatafilename));
        if ($md5sum != $this->md5) {
            Storage::delete($this->inboxdatafilename);
            $buildfile->delete();
            abort(Response::HTTP_BAD_REQUEST, "md5 mismatch. expected: {$this->md5}, received: $md5sum");
        }

        // Insert the buildfile row.
        $buildfile->save();

        // Increment the count of pending submission files for this build.
        $pendingSubmissions = new PendingSubmissions();
        $pendingSubmissions->Build = $this->build;
        if (!$pendingSubmissions->Exists()) {
            $pendingSubmissions->NumFiles = 0;
            $pendingSubmissions->Save();
        }
        $pendingSubmissions->Increment();
    }

    public function parseDataFileParameters(): void
    {
        // We expect GET to contain the following values:
        $vars = ['buildid', 'type', 'md5', 'filename'];
        foreach ($vars as $var) {
            if (empty($_GET[$var])) {
                abort(Response::HTTP_BAD_REQUEST, "Variable '$var' not set but required.");
            }
        }

        $this->buildid = $_GET['buildid'];
        $this->type = htmlspecialchars($_GET['type']);
        $this->md5 = htmlspecialchars($_GET['md5']);
        $this->backupfilename = htmlspecialchars($_GET['filename']);

        $this->getAuthTokenHash();
    }

    /** Check if CDash's database is down. */
    private function checkDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /** Write build metadata to disk in JSON format. */
    private function serializeBuildMetadata(string $uuid): void
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
            'token' => $this->token,
        ];

        $build_metadata_filename = "{$this->projectname}_-_{$this->token}_-_build-metadata_-_{$uuid}_-__-_.json";
        $inbox_build_metadata_filename = "inbox/{$build_metadata_filename}";
        Storage::put($inbox_build_metadata_filename, json_encode($build_metadata));
    }

    /** Append data file parameters to the build metadata JSON file. */
    private function serializeDataFileParameters(): void
    {
        $inbox_filename = "inbox/{$this->projectname}_-_{$this->token}_-_build-metadata_-_{$this->buildid}_-__-_.json";
        if (!Storage::exists($inbox_filename)) {
            Log::warning("Could not find build metadata file {$inbox_filename}");
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Could not find build metadata file');
        }

        $contents = Storage::get($inbox_filename);
        $build_metadata = json_decode($contents, true);
        if (!$build_metadata) {
            Log::warning("Failed to parse build metadata JSON {$inbox_filename}");
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to parse build metadata');
        }

        $build_metadata['buildid'] = $this->buildid;
        $build_metadata['type'] = $this->type;
        $build_metadata['md5'] = $this->md5;
        $build_metadata['backupfilename'] = $this->backupfilename;
        $build_metadata['inboxdatafilename'] = $this->inboxdatafilename;

        Storage::put($inbox_filename, json_encode($build_metadata));
    }

    /** Deserialize a build metadata JSON file. */
    public function deserializeBuildMetadata($fp): void
    {
        $build_metadata = json_decode(stream_get_contents($fp), true);

        if (!Project::validateProjectName($build_metadata['projectname'])) {
            abort(Response::HTTP_BAD_REQUEST, "Invalid project name: {$build_metadata['projectname']}");
        }

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

    private function getAuthTokenHash(): void
    {
        if ($this->token) {
            return;
        }
        $token = AuthTokenService::getBearerToken();
        if ($token) {
            $this->token = AuthTokenService::hashToken($token);
        } else {
            $this->token = '';
        }
    }
}
