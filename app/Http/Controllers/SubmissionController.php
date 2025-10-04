<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionValidationType;
use App\Exceptions\BadSubmissionException;
use App\Jobs\ProcessSubmission;
use App\Models\Site;
use App\Utils\AuthTokenUtil;
use App\Utils\SubmissionUtils;
use App\Utils\UnparsedSubmissionProcessor;
use CDash\Model\Build;
use CDash\Model\PendingSubmissions;
use CDash\Model\Project;
use Exception;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class SubmissionController extends AbstractProjectController
{
    public function submit(): Response|JsonResponse
    {
        // If we have a POST or PUT we defer to the unparsed submission processor.
        try {
            if (isset($_POST['project'])) {
                return (new UnparsedSubmissionProcessor())->postSubmit();
            } elseif (isset($_GET['buildid'])) {
                return (new UnparsedSubmissionProcessor())->putSubmitFile();
            }
        } catch (Exception $e) {
            $http_code = $e instanceof HttpException ? $e->getStatusCode() : 500;

            return self::displayXMLReturnStatus([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ], $http_code);
        }

        try {
            return $this->submitProcess();
        } catch (Exception $e) {
            $http_code = $e instanceof HttpException ? $e->getStatusCode() : 500;

            return response()->json([
                'status' => 1,
                'description' => $e->getMessage(),
            ], $http_code);
        }
    }

    private function failProcessing(?string $filename, int $outResponseCode, string $outMessage): void
    {
        if ($filename !== null) {
            try {
                Storage::move('inbox/' . $filename, 'failed/' . $filename);
            } catch (UnableToMoveFile) {
                Log::error("Failed to move {$filename} from inbox/ to failed/");
            }
        }
        abort($outResponseCode, $outMessage);
    }

    /**
     * @throws BadSubmissionException
     */
    private function submitProcess(): Response
    {
        @set_time_limit(0);

        $statusarray = [];
        $responseMessage = '';
        $projectname = request()->string('project', '');

        if (strlen($projectname) === 0) {
            Log::info('Rejected submission with no project name');
            $this->failProcessing(null, Response::HTTP_BAD_REQUEST, 'No project name provided.');
        }

        if (!Project::validateProjectName($projectname)) {
            Log::info("Rejected submission with invalid project name: $projectname");
            $this->failProcessing(null, Response::HTTP_BAD_REQUEST, "Invalid project name: $projectname");
        }

        $expected_md5 = isset($_GET['MD5']) ? htmlspecialchars($_GET['MD5']) : '';

        if ($expected_md5 !== '' && !preg_match('/^[a-f0-9]{32}$/i', $expected_md5)) {
            Log::info("Rejected submission with invalid hash '$expected_md5' for project $projectname");
            $this->failProcessing(null, Response::HTTP_BAD_REQUEST, "Provided md5 hash '{$expected_md5}' is improperly formatted.");
        }

        // Get auth token (if any).
        $authtoken = AuthTokenUtil::getBearerToken();
        $authtoken_hash = $authtoken === null || $authtoken === '' ? '' : AuthTokenUtil::hashToken($authtoken);

        // Check that the md5sum of the file matches what we were told to expect.
        $fp = request()->getContent(true);
        if (strlen($expected_md5) > 0) {
            $md5sum = SubmissionUtils::hashFileHandle($fp, 'md5');
            if ($md5sum !== $expected_md5) {
                Log::info("Rejected submission because hash $expected_md5 does not match the expected hash $md5sum");
                $this->failProcessing(null, Response::HTTP_BAD_REQUEST, "md5 mismatch. expected: {$expected_md5}, received: {$md5sum}");
            }
        }

        // Save the incoming file in the inbox directory.
        $filename = "{$projectname}_-_{$authtoken_hash}_-_" . Str::uuid()->toString() . "_-_{$expected_md5}.xml";
        try {
            Storage::put("inbox/{$filename}", $fp);
        } catch (UnableToWriteFile $e) {
            report($e);
            $this->failProcessing($filename, Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to save submission file.');
        }

        // Check if we can connect to the database before proceeding any further.
        try {
            DB::connection()->getPdo();
            if (app()->isDownForMaintenance()) {
                throw new Exception();
            }
        } catch (Exception) {
            // Write a marker file so we know to process these files when the DB comes back up.
            if (!Storage::exists('DB_WAS_DOWN')) {
                Storage::put('DB_WAS_DOWN', '');
            }
            $statusarray['status'] = 'OK';
            $statusarray['message'] = 'Database is unavailable.';
            return self::displayXMLReturnStatus($statusarray);
        }

        // We can't use the usual $this->setProjectByName() function here because the auth token we have might not allow
        // full access to the project.  E.g., it might be a submit-only token.
        $this->project = new Project();
        $this->project->FindByName($projectname);

        // Remove some old builds if the project has too many.
        $this->project->CheckForTooManyBuilds();

        // Check for valid authentication token if this project requires one.
        if ($this->project->AuthenticateSubmissions && !AuthTokenUtil::checkToken($authtoken_hash, $this->project->Id)) {
            Storage::delete("inbox/{$filename}");
            Log::info('Rejected submission with invalid authentication token');
            $this->failProcessing(null, Response::HTTP_FORBIDDEN, 'Invalid Token');
        } elseif (intval($this->project->Id) < 1) {
            Log::info("Rejected submission with invalid project name: $projectname");
            $this->failProcessing($filename, Response::HTTP_NOT_FOUND, 'The requested project does not exist.');
        }

        // Figure out what type of XML file this is.
        $stored_filename = 'inbox/' . $filename;
        $xml_info = SubmissionUtils::get_xml_type(Storage::readStream($stored_filename), $stored_filename);

        if ($xml_info['xml_handler'] !== '') {
            // If validation is enabled and if this file has a corresponding schema, validate it
            $validation_errors = [];
            try {
                $validation_errors = $xml_info['xml_handler']::validate($stored_filename);
            } catch (FileNotFoundException|UnableToReadFile $e) {
                report($e);
                $this->failProcessing($filename, 500, "Unable to read file for validation: $filename");
            }
            if (count($validation_errors) > 0) {
                $error_string = implode(PHP_EOL, $validation_errors);
                $message = "XML validation failed: Found issues with file $filename:" . PHP_EOL . $error_string;
                // We always log validation failures, but we only send messages back to the client if configured to do so
                switch (config('cdash.validate_submissions')) {
                    case SubmissionValidationType::REJECT:
                        Log::info("XML validation failed for file $filename.  Rejected submission.");
                        $this->failProcessing($filename, 400, $message);
                        break;
                    case SubmissionValidationType::WARN:
                        Log::info("XML validation failed for file $filename.  Accepted submission and returned warning.");
                        $responseMessage .= $message;
                        break;
                    default:
                        Log::info("XML validation failed for file $filename.  Accepted submission anyway.");
                }
            }
        }

        // Check if CTest provided us enough info to assign a buildid.
        $pendingSubmissions = new PendingSubmissions();
        $buildid = null;
        if (isset($_GET['build']) && isset($_GET['site']) && isset($_GET['stamp'])) {
            $build = new Build();
            $build->Name = pdo_real_escape_string($_GET['build']);
            $build->ProjectId = $this->project->Id;
            $build->SetStamp(pdo_real_escape_string($_GET['stamp']));
            $build->StartTime = gmdate(FMT_DATETIME);
            $build->SubmitTime = $build->StartTime;

            if (isset($_GET['subproject'])) {
                $build->SetSubProject(pdo_real_escape_string($_GET['subproject']));
            }

            $build->SiteId = Site::firstOrCreate(['name' => $_GET['site']], ['name' => $_GET['site']])->id;
            $pendingSubmissions->Build = $build;

            if ($build->AddBuild()) {
                // Insert row to keep track of how many submissions are waiting to be
                // processed for this build. This value will be incremented
                // (and decremented) later on.
                $pendingSubmissions->NumFiles = 0;
                $pendingSubmissions->Save();
            }
            $buildid = $build->Id;
        }

        if ($buildid !== null) {
            $pendingSubmissions->Increment();
        }
        ProcessSubmission::dispatch($filename, $this->project->Id, $buildid, $expected_md5);
        fclose($fp);
        unset($fp);

        // Check for marker file to see if we need to queue deferred submissions.
        if (Storage::exists('DB_WAS_DOWN')) {
            Storage::delete('DB_WAS_DOWN');
            Artisan::call('submission:queue');
        }

        $statusarray['message'] = '';
        if ($responseMessage !== '') {
            $statusarray['message'] = $responseMessage;
        }

        $statusarray['status'] = 'OK';
        if ($buildid !== null) {
            $statusarray['buildId'] = $buildid;
        }
        return self::displayXMLReturnStatus($statusarray);
    }

    /**
     * Accepts a status array and returns an XML response with the proper headers set.
     *
     * @param array<string,mixed> $statusarray
     */
    private static function displayXMLReturnStatus(array $statusarray, int $http_code = 200): Response
    {
        return response()
            ->view('submission.xml-response', ['statusarray' => $statusarray], $http_code)
            ->header('Content-Type', 'text/xml');
    }

    public function storeUploadedFile(Request $request): Response
    {
        if (!(bool) config('cdash.remote_workers')) {
            return response('This feature is disabled', Response::HTTP_CONFLICT);
        }

        if (!$request->has('sha1sum')) {
            return response('Bad request', Response::HTTP_BAD_REQUEST);
        }

        try {
            $expected_sha1sum = decrypt($request->input('sha1sum'));
        } catch (DecryptException) {
            return response('This feature is disabled', Response::HTTP_CONFLICT);
        }

        $uploaded_file = array_values(request()->allFiles())[0];
        $stored_path = $uploaded_file->storeAs('upload', $expected_sha1sum);
        if ($stored_path === false) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to store uploaded file');
        }

        $fp = Storage::readStream($stored_path);
        if ($fp === null) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to store uploaded file');
        }

        $found_sha1sum = SubmissionUtils::hashFileHandle($fp, 'sha1');
        if ($found_sha1sum !== $expected_sha1sum) {
            Storage::delete($stored_path);
            return response('Uploaded file does not match expected sha1sum', Response::HTTP_BAD_REQUEST);
        }

        return response('OK', Response::HTTP_OK);
    }
}
