<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSubmission;
use App\Models\Site;
use App\Services\AuthTokenService;
use App\Services\UnparsedSubmissionProcessor;
use CDash\Model\Build;
use CDash\Model\PendingSubmissions;
use CDash\Model\Project;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SubmissionController extends AbstractProjectController
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

    private function submitProcess(): Response
    {
        @set_time_limit(0);

        $statusarray = [];

        $projectname = $_GET['project'] ?? '';

        if (strlen($projectname) === 0) {
            abort(Response::HTTP_BAD_REQUEST, 'No project name provided.');
        }

        if (!Project::validateProjectName($projectname)) {
            Log::error("Invalid project name: $projectname");
            abort(Response::HTTP_BAD_REQUEST, "Invalid project name: $projectname");
        }

        $expected_md5 = isset($_GET['MD5']) ? htmlspecialchars($_GET['MD5']) : '';

        if ($expected_md5 !== '' && !preg_match('/^[a-f0-9]{32}$/i', $expected_md5)) {
            abort(Response::HTTP_BAD_REQUEST, "Provided md5 hash '{$expected_md5}' is improperly formatted.");
        }

        // Get auth token (if any).
        $authtoken = AuthTokenService::getBearerToken();
        $authtoken_hash = $authtoken === null || $authtoken === '' ? '' : AuthTokenService::hashToken($authtoken);

        // Save the incoming file in the inbox directory.
        $filename = "{$projectname}_-_{$authtoken_hash}_-_" . Str::uuid()->toString() . "_-_{$expected_md5}.xml";
        $fp = request()->getContent(true);
        if (!Storage::put("inbox/{$filename}", $fp)) {
            Log::error("Failed to save submission to inbox for $projectname (md5=$expected_md5)");
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to save submission file.');
        }

        // Check that the md5sum of the file matches what we were told to expect.
        if (strlen($expected_md5) > 0) {
            $md5sum = md5_file(Storage::path("inbox/{$filename}"));
            if ($md5sum != $expected_md5) {
                Storage::delete("inbox/{$filename}");
                abort(Response::HTTP_BAD_REQUEST, "md5 mismatch. expected: {$expected_md5}, received: {$md5sum}");
            }
        }

        // Check if we can connect to the database before proceeding any further.
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            // Write a marker file so we know to process these files when the DB comes back up.
            if (!Storage::exists("DB_WAS_DOWN")) {
                Storage::put("DB_WAS_DOWN", "");
            }
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'Cannot connect to the database.');
        }

        // We can't use the usual $this->setProjectByName() function here because the auth token we have might not allow
        // full access to the project.  E.g., it might be a submit-only token.
        $this->project = new Project();
        $this->project->FindByName($projectname);

        // Remove some old builds if the project has too many.
        $this->project->CheckForTooManyBuilds();

        // Check for valid authentication token if this project requires one.
        if ($this->project->AuthenticateSubmissions && !AuthTokenService::checkToken($authtoken_hash, $this->project->Id)) {
            Storage::delete("inbox/{$filename}");
            abort(Response::HTTP_FORBIDDEN, 'Invalid Token');
        } elseif (intval($this->project->Id) < 1) {
            abort(Response::HTTP_NOT_FOUND, 'The requested project does not exist.');
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
        if (Storage::exists("DB_WAS_DOWN")) {
            Storage::delete("DB_WAS_DOWN");
            Artisan::call('submission:queue');
        }

        $statusarray['status'] = 'OK';
        $statusarray['message'] = '';
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
}
