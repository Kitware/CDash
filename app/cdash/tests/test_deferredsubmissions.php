<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'tests/test_branchcoverage.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

use App\Models\AuthToken;
use CDash\Model\Project;

class DeferredSubmissionsTestCase extends BranchCoverageTestCase
{
    protected $ConfigFile;
    protected $Original;
    protected $project;
    protected $projectname;
    protected $token;
    protected $dataDir;
    protected $buildid;

    public function __construct()
    {
        parent::__construct();
        $this->ConfigFile = dirname(__FILE__) . '/../../../.env';
        $this->Original = file_get_contents($this->ConfigFile);

        // Make sure we start from scratch each time the test is run.
        $this->project = new Project();
        $this->projectname = 'DeferredSubmissions';
        if ($this->project->FindByName($this->projectname)) {
            remove_project_builds($this->project->Id);
            $this->project->Delete();
        }
        $this->project->Id = $this->createProject([
            'Name' => $this->projectname,
            'DisplayLabels' => '1',
        ]);
        $this->project->Fill();

        $this->token = '';
        $this->dataDir = dirname(__FILE__) . '/data/DeferredSubmission';
    }

    public function __destruct()
    {
        file_put_contents($this->ConfigFile, $this->Original);
    }

    public function testDeferredSubmissions()
    {
        $this->prepareForNormalSubmission();

        // Change CDash config to point to a nonexistent database.
        file_put_contents($this->ConfigFile, "DB_DATABASE=cdash4simpletestfake\n", FILE_APPEND | LOCK_EX);

        // Submit the build files.
        $this->submission($this->projectname, "$this->dataDir/Build.xml");
        $this->submission($this->projectname, "$this->dataDir/Configure.xml");
        $this->submission($this->projectname, "$this->dataDir/Test.xml");

        // Verify that files exist in the inbox directory.
        $this->assertEqual(3, count(Storage::files('inbox')));

        // Restore original database configuration.
        file_put_contents($this->ConfigFile, $this->Original);

        // Submit one more file.
        // This automatically parses the deferred files now that the database
        // is available again.
        $this->submission($this->projectname, "$this->dataDir/Notes.xml");

        // Verify the results.
        $this->verifyNormalSubmission();
    }

    private function prepareForNormalSubmission()
    {
        $this->deleteLog($this->logfilename);

        // Delete the existing build if it exists.
        $existing_build_row = \DB::table('build')
            ->where('projectid', '=', $this->project->Id)
            ->where('name', '=', 'deferred_submission')
            ->first();
        if ($existing_build_row) {
            remove_build($existing_build_row->id);
        }

        // Make sure inbox is empty.
        $files = Storage::allFiles('inbox');
        Storage::delete($files);
        $files = Storage::allFiles('parsed');
        Storage::delete($files);
        $files = Storage::allFiles('inprogress');
        Storage::delete($files);
        $files = Storage::allFiles('failed');
        Storage::delete($files);
    }

    private function verifyNormalSubmission()
    {
        $build_row = \DB::table('build')
            ->where('projectid', '=', $this->project->Id)
            ->where('name', '=', 'deferred_submission')
            ->first();
        if (!$build_row) {
            $this->fail('No build found after call to submission:queue');
        }

        $this->assertEqual(0, $build_row->builderrors);
        $this->assertEqual(1, $build_row->buildwarnings);
        $this->assertEqual(1, $build_row->testpassed);
        $this->assertEqual(1, $build_row->testfailed);
    }

    /**
     * When creating a submit-only, project-specific token (`$scope = AuthToken::SCOPE_SUBMIT_ONLY`),
     * the project will always be `$this->project`
     *
     * @param string $scope a string value matching one of the constants defined in AuthToken.php
     */
    private function getToken(string $scope)
    {
        if ($this->token !== '') {
            return;
        }

        // Log in as non-admin user.
        $this->login('user1@kw', 'user1');

        // Use API to generate token.
        $response = $this->post($this->url . '/api/authtokens/create', [
            'description' => 'mytoken',
            'scope' => $scope,
            'projectid' => $this->project->Id
        ]);
        $response = json_decode($response, true);
        $this->token = $response['raw_token'];
        $this->logout();
    }

    public function testNormalSubmitWithValidToken()
    {
        // Reconfigure project to require authenticated submissions.
        $this->project->AuthenticateSubmissions = true;
        $this->project->Public = 1;
        $this->project->Save();

        // Get bearer token.
        $this->getToken(AuthToken::SCOPE_FULL_ACCESS);

        // Start from a clean slate.
        $this->prepareForNormalSubmission();

        // Change CDash config to point to a nonexistent database.
        file_put_contents($this->ConfigFile, "DB_DATABASE=cdash4simpletestfake\n", FILE_APPEND | LOCK_EX);

        // Submit test data with bearer token.
        $header = ["Authorization: Bearer {$this->token}"];
        $this->submission($this->projectname, "$this->dataDir/Build.xml", $header);
        $this->submission($this->projectname, "$this->dataDir/Configure.xml", $header);
        $this->submission($this->projectname, "$this->dataDir/Test.xml", $header);

        // Verify that files exist in the inbox directory.
        $this->assertEqual(3, count(Storage::files('inbox')));

        // Restore original database configuration.
        file_put_contents($this->ConfigFile, $this->Original);

        // Submit one more file.
        // This automatically parses the deferred files now that the database
        // is available again.
        $this->submission($this->projectname, "$this->dataDir/Notes.xml", $header);

        // Verify the results.
        $this->verifyNormalSubmission();

        $this->project->AuthenticateSubmissions = false;
        $this->project->Save();
    }

    public function testNormalSubmitWithInvalidToken()
    {
        // Reconfigure project to require authenticated submissions.
        $this->project->AuthenticateSubmissions = true;
        $this->project->Save();

        // Start from a clean slate.
        $this->prepareForNormalSubmission();

        // Change CDash config to point to a nonexistent database.
        file_put_contents($this->ConfigFile, "DB_DATABASE=cdash4simpletestfake\n", FILE_APPEND | LOCK_EX);

        // Submit test data with invalid bearer token.
        $header = ["Authorization: Bearer asdf"];
        $this->submission($this->projectname, "$this->dataDir/Build.xml", $header);

        // Verify that files exist in the inbox directory.
        $this->assertEqual(1, count(Storage::files('inbox')));

        // Restore original database configuration.
        file_put_contents($this->ConfigFile, $this->Original);

        // Submit one more file.
        // This automatically parses the deferred files now that the database
        // is available again.
        $valid_header = ["Authorization: Bearer {$this->token}"];
        $this->submission($this->projectname, "$this->dataDir/Notes.xml", $valid_header);

        // Verify one failed submission.
        $this->assertEqual(1, count(Storage::files('failed')));

        $this->project->AuthenticateSubmissions = false;
        $this->project->Save();
    }

    public function testNormalSubmitWithMissingToken()
    {
        // Reconfigure project to require authenticated submissions.
        $this->project->AuthenticateSubmissions = true;
        $this->project->Save();

        // Start from a clean slate.
        $this->prepareForNormalSubmission();

        // Change CDash config to point to a nonexistent database.
        file_put_contents($this->ConfigFile, "DB_DATABASE=cdash4simpletestfake\n", FILE_APPEND | LOCK_EX);

        // Submit test data with invalid bearer token.
        $header = [];
        $this->submission($this->projectname, "$this->dataDir/Build.xml", $header);

        // Verify that files exist in the inbox directory.
        $this->assertEqual(1, count(Storage::files('inbox')));

        // Restore original database configuration.
        file_put_contents($this->ConfigFile, $this->Original);

        // Submit one more file.
        // This automatically parses the deferred files now that the database
        // is available again.
        $valid_header = ["Authorization: Bearer {$this->token}"];
        $this->submission($this->projectname, "$this->dataDir/Notes.xml", $valid_header);

        // Verify one failed submission.
        $this->assertEqual(1, count(Storage::files('failed')));

        $this->project->AuthenticateSubmissions = false;
        $this->project->Save();
    }

    public function testDeferredUnparsedSubmission()
    {
        // Delete existing results (if any).
        $this->clearPriorBranchCoverageResults();

        // Make sure inbox is empty.
        $files = Storage::allFiles('inbox');
        Storage::delete($files);

        // Change CDash config to point to a nonexistent database.
        file_put_contents($this->ConfigFile, "DB_DATABASE=cdash4simpletestfake\n", FILE_APPEND | LOCK_EX);

        // Submit data.
        $this->postSubmit();
        $this->putSubmit();

        // Verify that files exist in the inbox directory.
        $this->assertEqual(2, count(Storage::files('inbox')));

        // Restore original database configuration.
        file_put_contents($this->ConfigFile, $this->Original);

        // Submit one more file.
        // This automatically parses the deferred files now that the database
        // is available again.
        $this->submission($this->projectname, "$this->dataDir/Notes.xml");

        // Get the newly created buildid.
        $build_row = \DB::table('build')
            ->where('projectid', '=', $this->project->Id)
            ->where('name', '=', 'branch_coverage')
            ->first();
        if (!$build_row) {
            $this->fail('Could not locate branch coverage build id');
        }
        $this->buildid = $build_row->id;

        // Verify the results.
        $this->verifyResults();
    }

    public function testDeferredSubmitWithValidToken()
    {
        // Reconfigure project to require authenticated submissions.
        $this->project->AuthenticateSubmissions = true;
        $this->project->Public = 1;
        $this->project->Save();

        // Get bearer token.
        $this->getToken(AuthToken::SCOPE_FULL_ACCESS);

        // Delete existing results (if any).
        $this->clearPriorBranchCoverageResults();

        // Make sure inbox is empty.
        $files = Storage::allFiles('inbox');
        Storage::delete($files);

        // Change CDash config to point to a nonexistent database.
        file_put_contents($this->ConfigFile, "DB_DATABASE=cdash4simpletestfake\n", FILE_APPEND | LOCK_EX);

        // Submit data.
        $this->postSubmit($this->token);
        $this->putSubmit($this->token);

        // Verify that files exist in the inbox directory.
        $this->assertEqual(2, count(Storage::files('inbox')));

        // Restore original database configuration.
        file_put_contents($this->ConfigFile, $this->Original);

        // Submit one more file.
        // This automatically parses the deferred files now that the database
        // is available again.
        $valid_header = ["Authorization: Bearer {$this->token}"];
        $this->submission($this->projectname, "$this->dataDir/Notes.xml", $valid_header);

        // Get the newly created buildid.
        $build_row = \DB::table('build')
            ->where('projectid', '=', $this->project->Id)
            ->where('name', '=', 'branch_coverage')
            ->first();
        if (!$build_row) {
            $this->fail('Could not locate branch coverage build id');
        }
        $this->buildid = $build_row->id;

        // Verify the results.
        $this->verifyResults();

        $this->project->AuthenticateSubmissions = false;
        $this->project->Save();
    }

    public function testDeferredSubmitWithInvalidToken()
    {
        // Reconfigure project to require authenticated submissions.
        $this->project->AuthenticateSubmissions = true;
        $this->project->Public = 1;
        $this->project->Save();

        // Get bearer token.
        $this->getToken(AuthToken::SCOPE_FULL_ACCESS);

        // Delete existing results (if any).
        $this->clearPriorBranchCoverageResults();

        // Make sure inbox is empty.
        $files = Storage::allFiles('inbox');
        Storage::delete($files);

        // Change CDash config to point to a nonexistent database.
        file_put_contents($this->ConfigFile, "DB_DATABASE=cdash4simpletestfake\n", FILE_APPEND | LOCK_EX);

        // Submit data.
        $this->postSubmit('asdf');
        $this->putSubmit('asdf');

        // Verify that files exist in the inbox directory.
        $this->assertEqual(2, count(Storage::files('inbox')));

        // Restore original database configuration.
        file_put_contents($this->ConfigFile, $this->Original);

        // Submit one more file.
        // This automatically parses the deferred files now that the database
        // is available again.
        $valid_header = ["Authorization: Bearer {$this->token}"];
        $this->submission($this->projectname, "$this->dataDir/Notes.xml", $valid_header);

        // Verify two failed submission files.
        $this->assertEqual(2, count(Storage::files('failed')));

        $this->project->AuthenticateSubmissions = false;
        $this->project->Save();
    }

    public function testDeferredSubmitWithMissingToken()
    {
        // Reconfigure project to require authenticated submissions.
        $this->project->AuthenticateSubmissions = true;
        $this->project->Public = 1;
        $this->project->Save();

        // Get bearer token.
        $this->getToken(AuthToken::SCOPE_FULL_ACCESS);

        // Delete existing results (if any).
        $this->clearPriorBranchCoverageResults();

        // Make sure inbox is empty.
        $files = Storage::allFiles('inbox');
        Storage::delete($files);

        // Change CDash config to point to a nonexistent database.
        file_put_contents($this->ConfigFile, "DB_DATABASE=cdash4simpletestfake\n", FILE_APPEND | LOCK_EX);

        // Submit data.
        $this->postSubmit();
        $this->putSubmit();

        // Verify that files exist in the inbox directory.
        $this->assertEqual(2, count(Storage::files('inbox')));

        // Restore original database configuration.
        file_put_contents($this->ConfigFile, $this->Original);

        // Submit one more file.
        // This automatically parses the deferred files now that the database
        // is available again.
        $valid_header = ["Authorization: Bearer {$this->token}"];
        $this->submission($this->projectname, "$this->dataDir/Notes.xml", $valid_header);

        // Verify two failed submission files.
        $this->assertEqual(2, count(Storage::files('failed')));

        $this->project->AuthenticateSubmissions = false;
        $this->project->Save();
    }

    public function testDeferredSubmitWithProjectToken()
    {
        // Reconfigure project to require authenticated submissions.
        $this->project->AuthenticateSubmissions = true;
        $this->project->Public = 1;
        $this->project->Save();

        // Get bearer token.
        $this->getToken(AuthToken::SCOPE_SUBMIT_ONLY);

        // Delete existing results (if any).
        $this->clearPriorBranchCoverageResults();

        // Make sure inbox is empty.
        $files = Storage::allFiles('inbox');
        Storage::delete($files);

        // Change CDash config to point to a nonexistent database.
        file_put_contents($this->ConfigFile, "DB_DATABASE=cdash4simpletestfake\n", FILE_APPEND | LOCK_EX);

        // Submit data.
        $this->postSubmit($this->token);
        $this->putSubmit($this->token);

        // Verify that files exist in the inbox directory.
        $this->assertEqual(2, count(Storage::files('inbox')));

        // Restore original database configuration.
        file_put_contents($this->ConfigFile, $this->Original);

        // Submit one more file.
        // This automatically parses the deferred files now that the database
        // is available again.
        $valid_header = ["Authorization: Bearer {$this->token}"];
        $this->submission($this->projectname, "$this->dataDir/Notes.xml", $valid_header);

        // Get the newly created buildid.
        $build_row = \DB::table('build')
            ->where('projectid', '=', $this->project->Id)
            ->where('name', '=', 'branch_coverage')
            ->first();
        if (!$build_row) {
            $this->fail('Could not locate branch coverage build id');
        }
        $this->buildid = $build_row->id;

        // Verify the results.
        $this->verifyResults();

        $this->project->AuthenticateSubmissions = false;
        $this->project->Save();
    }

    public function testDeferredSubmitWithInvalidProjectToken()
    {
        // Reconfigure project to require authenticated submissions.
        $this->project->AuthenticateSubmissions = true;
        $this->project->Public = 1;
        $this->project->Save();

        // Make a new project, wiping away any previous test results
        $project2 = new Project();
        $projectname2 = 'DeferredSubmissions2';
        if ($project2->FindByName($projectname2)) {
            remove_project_builds($project2->Id);
            $project2->Delete();
        }
        $project2->Id = $this->createProject([
            'Name' => $projectname2,
            'DisplayLabels' => '1',
        ]);
        $project2->Fill();

        // Get bearer token.
        $this->getToken(AuthToken::SCOPE_SUBMIT_ONLY);

        // Delete existing results (if any).
        $this->clearPriorBranchCoverageResults();

        // Make sure inbox is empty.
        $files = Storage::allFiles('inbox');
        Storage::delete($files);

        // Change CDash config to point to a nonexistent database.
        file_put_contents($this->ConfigFile, "DB_DATABASE=cdash4simpletestfake\n", FILE_APPEND | LOCK_EX);

        // Submit data.
        $this->postSubmit($this->token);
        $this->putSubmit($this->token);

        // Verify that files exist in the inbox directory.
        $this->assertEqual(2, count(Storage::files('inbox')));

        // Restore original database configuration.
        file_put_contents($this->ConfigFile, $this->Original);

        // Submit one more file.
        // This automatically parses the deferred files now that the database
        // is available again.
        $valid_header = ["Authorization: Bearer {$this->token}"];
        $this->submission($projectname2, "$this->dataDir/Notes.xml", $valid_header);

        // Get the newly created buildid.
        $build_row = \DB::table('build')
            ->where('projectid', '=', $project2->Id)
            ->where('name', '=', 'branch_coverage')
            ->first();
        if ($build_row) {
            $this->fail('Submitted result for project with incorrect permissions');
        }

        $this->project->AuthenticateSubmissions = false;
        $this->project->Save();
    }
}
