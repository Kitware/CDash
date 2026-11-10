<?php

namespace Tests\Feature\Submission;

use App\Enums\AuthTokenScope;
use App\Models\Project;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

/**
 * Tests for submissions received while the database is unavailable, which are stored and then
 * processed once the next submission arrives after the database comes back.
 */
class DeferredSubmissionTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;
    use DatabaseTransactions;

    private const XML_FILES = [
        'tests/Feature/Submission/data/deferred_build.xml',
        'tests/Feature/Submission/data/deferred_configure.xml',
        'tests/Feature/Submission/data/deferred_test.xml',
    ];

    private const DATA_FILE = 'tests/Feature/Submission/data/gcov.tar';

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();

        $this->project = $this->makePublicProject('Deferred_' . Str::random(8));
    }

    /**
     * Submit every deferred file while the database is unavailable.
     */
    private function submitDuringOutage(?string $auth_token = null): void
    {
        $this->whileDatabaseIsUnavailable(function () use ($auth_token): void {
            foreach (self::XML_FILES as $file) {
                $this->makeSubmission($this->project->name, base_path($file), auth_token: $auth_token);
            }
            $this->makeDeferredUnparsedSubmission($auth_token);
        });

        $this->assertDatabaseMissing('build', ['projectid' => $this->project->id]);
    }

    /**
     * Submit an unrelated file once the database is back, which processes the deferred files.
     */
    private function submitAfterOutage(?string $auth_token = null): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/data/configure.xml'
        ), auth_token: $auth_token);
    }

    /**
     * Run a callback while the database is considered unavailable, failing if anything queries it anyway.
     *
     * Maintenance mode is what marks the database as unavailable, and submissions are exempt from it.
     * The array store keeps that state in this process, rather than in a file other requests can see.
     */
    private function whileDatabaseIsUnavailable(callable $callback): void
    {
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        Config::set('app.maintenance.driver', 'cache');
        Config::set('app.maintenance.store', 'array');
        $this->app->maintenanceMode()->activate([]);
        try {
            $callback();
        } finally {
            $this->app->maintenanceMode()->deactivate();
        }

        $this->assertSame([], $queries);
    }

    /**
     * The POST phase of an unparsed submission returns a UUID instead of a build id while the database
     * is unavailable, and the PUT phase uses it in place of the build id.
     */
    private function makeDeferredUnparsedSubmission(?string $auth_token): void
    {
        $server = $auth_token === null
            ? []
            : $this->transformHeadersToServerVars(['Authorization' => "Bearer $auth_token"]);

        $file_contents = file_get_contents(base_path(self::DATA_FILE));
        $this->assertIsString($file_contents);
        $md5 = md5($file_contents);

        $post_response = $this->call('POST', '/submit.php', [
            'project' => $this->project->name,
            'build' => 'deferred_unparsed_submission',
            'site' => 'localhost',
            'stamp' => '20150128-1436-Experimental',
            'starttime' => 1422455768,
            'endtime' => 1422455768,
            'datafilesmd5' => [$md5],
        ], [], [], $server);
        $post_response->assertOk();
        $uuid = $post_response->json('buildid');
        $this->assertTrue(Str::isUuid($uuid));

        // PUT request parameters are placed in the request body, so these must go in the URL instead.
        $query = http_build_query([
            'type' => 'GcovTar',
            'md5' => $md5,
            'filename' => 'gcov.tar',
            'buildid' => $uuid,
        ]);
        $this->call('PUT', "/submit.php?$query", [], [], [], $server, $file_contents)
            ->assertOk()
            ->assertExactJson(['status' => 0]);
    }

    private function assertDeferredSubmissionsProcessed(): void
    {
        $this->assertDatabaseHas('build', [
            'projectid' => $this->project->id,
            'name' => 'deferred_submission',
            'builderrors' => 0,
            'buildwarnings' => 1,
            'testpassed' => 1,
            'testfailed' => 1,
        ]);

        $buildid = $this->project->builds()->where('name', 'deferred_unparsed_submission')->firstOrFail()->id;
        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                coverage(filters: { eq: { filePath: "./MathFunctions/mysqrt.cxx" } }) {
                  edges {
                    node {
                      linesOfCodeTested
                      linesOfCodeUntested
                    }
                  }
                }
              }
            }
        ', [
            'id' => $buildid,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'coverage' => [
                        'edges' => [
                            [
                                'node' => [
                                    'linesOfCodeTested' => 17,
                                    'linesOfCodeUntested' => 3,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function assertDeferredSubmissionsRejected(): void
    {
        $this->assertDatabaseMissing('build', [
            'projectid' => $this->project->id,
            'name' => 'deferred_submission',
        ]);
        $this->assertDatabaseMissing('build', [
            'projectid' => $this->project->id,
            'name' => 'deferred_unparsed_submission',
        ]);
    }

    private function requireAuthenticatedSubmissions(): void
    {
        $this->project->authenticatesubmissions = true;
        $this->project->save();
    }

    private function createToken(AuthTokenScope $scope, Project $project): string
    {
        return AuthTokenService::generate(
            User::factory()->create()->id,
            $project->id,
            $scope,
            'Deferred submission test token',
        )['raw_token'];
    }

    public function testProcessesDeferredSubmissions(): void
    {
        $this->submitDuringOutage();
        $this->submitAfterOutage();

        $this->assertDeferredSubmissionsProcessed();
    }

    public function testProcessesDeferredSubmissionsWithFullAccessToken(): void
    {
        $this->requireAuthenticatedSubmissions();
        $token = $this->createToken(AuthTokenScope::FULL_ACCESS, $this->project);

        $this->submitDuringOutage($token);
        $this->submitAfterOutage($token);

        $this->assertDeferredSubmissionsProcessed();
    }

    public function testProcessesDeferredSubmissionsWithSubmitOnlyToken(): void
    {
        $this->requireAuthenticatedSubmissions();
        $token = $this->createToken(AuthTokenScope::SUBMIT_ONLY, $this->project);

        $this->submitDuringOutage($token);
        $this->submitAfterOutage($token);

        $this->assertDeferredSubmissionsProcessed();
    }

    public function testRejectsDeferredSubmissionsWithoutToken(): void
    {
        $this->requireAuthenticatedSubmissions();

        $this->submitDuringOutage();
        $this->expectOutputRegex('/\A(Invalid authentication token for \S+\n){5}\z/');
        $this->submitAfterOutage($this->createToken(AuthTokenScope::SUBMIT_ONLY, $this->project));

        $this->assertDeferredSubmissionsRejected();
    }

    public function testRejectsDeferredSubmissionsWithUnknownToken(): void
    {
        $this->requireAuthenticatedSubmissions();

        $this->submitDuringOutage(Str::uuid()->toString());
        $this->expectOutputRegex('/\A(Invalid authentication token for \S+\n){5}\z/');
        $this->submitAfterOutage($this->createToken(AuthTokenScope::SUBMIT_ONLY, $this->project));

        $this->assertDeferredSubmissionsRejected();
    }

    public function testRejectsDeferredSubmissionsWithTokenForAnotherProject(): void
    {
        $this->requireAuthenticatedSubmissions();

        $this->submitDuringOutage($this->createToken(AuthTokenScope::SUBMIT_ONLY, $this->makePublicProject()));
        $this->expectOutputRegex('/\A(Invalid authentication token for \S+\n){5}\z/');
        $this->submitAfterOutage($this->createToken(AuthTokenScope::SUBMIT_ONLY, $this->project));

        $this->assertDeferredSubmissionsRejected();
    }
}
