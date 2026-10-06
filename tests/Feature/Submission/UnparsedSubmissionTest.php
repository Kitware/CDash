<?php

namespace Tests\Feature\Submission;

use App\Enums\AuthTokenScope;
use App\Jobs\ProcessSubmission;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

/**
 * Tests for the two-phase "unparsed" submission process: build metadata is POSTed first to obtain a
 * build id, then a data file is PUT to that build.
 */
class UnparsedSubmissionTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    // Submissions are never processed since the queue is faked, so the contents are arbitrary.
    private const FILE_CONTENTS = 'Unparsed submission data file';

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Log::spy();
        Queue::fake();

        $this->project = $this->makePublicProject();
    }

    /**
     * @return array<string,mixed>
     */
    private function buildMetadata(): array
    {
        return [
            'project' => $this->project->name,
            'build' => 'unparsed_submission',
            'site' => 'unparsed.site',
            'stamp' => '20240501-0100-Experimental',
            'starttime' => 1714525200,
            'endtime' => 1714525260,
            'datafilesmd5' => [md5(self::FILE_CONTENTS)],
        ];
    }

    /**
     * @return array<string,int|string>
     */
    private function dataFileParameters(int $buildid): array
    {
        return [
            'buildid' => $buildid,
            'type' => 'BazelJSON',
            'md5' => md5(self::FILE_CONTENTS),
            'filename' => 'bazel.json',
        ];
    }

    /**
     * @param array<string,mixed> $build_metadata
     *
     * @return TestResponse<Response>
     */
    private function postBuildMetadata(array $build_metadata, ?string $auth_token = null): TestResponse
    {
        return $this->call('POST', '/submit.php', $build_metadata, [], [], $this->authorizationHeader($auth_token));
    }

    /**
     * @param array<string,int|string> $query
     *
     * @return TestResponse<Response>
     */
    private function putDataFile(array $query, ?string $auth_token = null): TestResponse
    {
        // PUT request parameters are placed in the request body, so these must go in the URL instead.
        return $this->call('PUT', '/submit.php?' . http_build_query($query), [], [], [], $this->authorizationHeader($auth_token), self::FILE_CONTENTS);
    }

    /**
     * @return array<string,string>
     */
    private function authorizationHeader(?string $auth_token): array
    {
        return $auth_token === null
            ? []
            : $this->transformHeadersToServerVars(['Authorization' => "Bearer $auth_token"]);
    }

    private function createBuild(): int
    {
        return $this->project->builds()->create([
            'name' => 'unparsed_submission',
            'uuid' => Str::uuid()->toString(),
        ])->id;
    }

    private function createSubmitOnlyToken(): string
    {
        return AuthTokenService::generate(
            User::factory()->create()->id,
            $this->project->id,
            AuthTokenScope::SUBMIT_ONLY,
            'Unparsed submission test token',
        )['raw_token'];
    }

    private function requireAuthenticatedSubmissions(): void
    {
        $this->project->authenticatesubmissions = true;
        $this->project->save();
    }

    public function testRejectsBuildMetadataWithEmptyProjectName(): void
    {
        // Requests without a project parameter are treated as XML submissions, so an empty project
        // name is needed to reach the unparsed submission processor.
        $response = $this->postBuildMetadata([
            ...$this->buildMetadata(),
            'project' => '',
        ]);

        $response->assertBadRequest();
        $response->assertExactJson([
            'status' => 1,
            'description' => 'The project field is required.',
        ]);
        $this->assertDatabaseMissing('build', ['projectid' => $this->project->id]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function invalidProjectNameCases(): array
    {
        return [
            'invalid characters' => [
                'invalid/name',
                'Project name may only contain letters, numbers, dashes, and underscores.',
            ],
            'filename separator' => [
                'invalid_-_name',
                'Project name must not contain string "_-_"',
            ],
        ];
    }

    #[DataProvider('invalidProjectNameCases')]
    public function testRejectsBuildMetadataWithInvalidProjectName(string $project_name, string $message): void
    {
        $response = $this->postBuildMetadata([
            ...$this->buildMetadata(),
            'project' => $project_name,
        ]);

        $response->assertBadRequest();
        $response->assertExactJson([
            'status' => 1,
            'description' => $message,
        ]);
        $this->assertDatabaseMissing('build', ['projectid' => $this->project->id]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    public function testRejectsBuildMetadataForNonexistentProject(): void
    {
        $response = $this->postBuildMetadata([
            ...$this->buildMetadata(),
            'project' => 'NonexistentProject_' . Str::uuid()->toString(),
        ]);

        $response->assertNotFound();
        $response->assertExactJson([
            'status' => 1,
            'description' => 'Project does not exist',
        ]);
        $this->assertDatabaseMissing('build', ['name' => 'unparsed_submission']);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    /**
     * @return array<string,array{string}>
     */
    public static function requiredBuildMetadataCases(): array
    {
        return [
            'build' => ['build'],
            'site' => ['site'],
            'stamp' => ['stamp'],
            'starttime' => ['starttime'],
            'endtime' => ['endtime'],
            'datafilesmd5' => ['datafilesmd5'],
        ];
    }

    #[DataProvider('requiredBuildMetadataCases')]
    public function testRejectsBuildMetadataWithMissingField(string $field): void
    {
        $build_metadata = $this->buildMetadata();
        unset($build_metadata[$field]);

        $response = $this->postBuildMetadata($build_metadata);

        $response->assertBadRequest();
        $response->assertExactJson([
            'status' => 1,
            'description' => "The $field field is required.",
        ]);
        $this->assertDatabaseMissing('build', ['projectid' => $this->project->id]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function nonIntegerTimestampCases(): array
    {
        return [
            'non-numeric starttime' => ['starttime', 'abc'],
            'formatted starttime' => ['starttime', '2024-05-01 01:00:00'],
            'fractional endtime' => ['endtime', '1714525260.5'],
        ];
    }

    #[DataProvider('nonIntegerTimestampCases')]
    public function testRejectsBuildMetadataWithNonIntegerTimestamp(string $field, string $value): void
    {
        $response = $this->postBuildMetadata([
            ...$this->buildMetadata(),
            $field => $value,
        ]);

        $response->assertBadRequest();
        $response->assertExactJson([
            'status' => 1,
            'description' => "The $field must be an integer.",
        ]);
        $this->assertDatabaseMissing('build', ['projectid' => $this->project->id]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    public function testAcceptsBuildMetadata(): void
    {
        $response = $this->postBuildMetadata([
            ...$this->buildMetadata(),
            'generator' => 'ctest-3.30.0',
        ]);

        $response->assertOk();
        $this->assertSame(1, $this->project->builds()->count());
        $build = $this->project->builds()->firstOrFail();
        $response->assertExactJson([
            'status' => 0,
            'buildid' => $build->id,
            'datafilesmd5' => [0],
        ]);
        $this->assertSame('unparsed_submission', $build->name);
        $this->assertSame('20240501-0100-Experimental', $build->stamp);
        $this->assertSame('ctest-3.30.0', $build->generator);
        $this->assertSame('2024-05-01 01:00:00', $build->starttime->format('Y-m-d H:i:s'));
        $this->assertSame('2024-05-01 01:01:00', $build->endtime->format('Y-m-d H:i:s'));
        $this->assertSame(Site::where('name', 'unparsed.site')->firstOrFail()->id, $build->siteid);
        // The data file hasn't been uploaded yet, so there is nothing to process.
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    public function testReusesBuildForRepeatedBuildMetadata(): void
    {
        $first_response = $this->postBuildMetadata($this->buildMetadata());
        $second_response = $this->postBuildMetadata($this->buildMetadata());

        $first_response->assertOk();
        $second_response->assertOk();
        $this->assertSame(1, $this->project->builds()->count());
        $buildid = $this->project->builds()->firstOrFail()->id;
        $this->assertSame($buildid, $first_response->json('buildid'));
        $this->assertSame($buildid, $second_response->json('buildid'));
    }

    public function testRejectsBuildMetadataWithoutTokenWhenProjectRequiresAuthentication(): void
    {
        $this->requireAuthenticatedSubmissions();

        $response = $this->postBuildMetadata($this->buildMetadata());

        $response->assertForbidden();
        $response->assertExactJson([
            'status' => 1,
            'description' => 'Forbidden',
        ]);
        $this->assertDatabaseMissing('build', ['projectid' => $this->project->id]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    public function testRejectsBuildMetadataWithUnknownTokenWhenProjectRequiresAuthentication(): void
    {
        $this->requireAuthenticatedSubmissions();

        $response = $this->postBuildMetadata($this->buildMetadata(), Str::uuid()->toString());

        $response->assertForbidden();
        $response->assertExactJson([
            'status' => 1,
            'description' => 'Forbidden',
        ]);
        $this->assertDatabaseMissing('build', ['projectid' => $this->project->id]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    public function testAcceptsBuildMetadataWithValidTokenWhenProjectRequiresAuthentication(): void
    {
        $this->requireAuthenticatedSubmissions();

        $response = $this->postBuildMetadata($this->buildMetadata(), $this->createSubmitOnlyToken());

        $response->assertOk();
        $this->assertSame(1, $this->project->builds()->count());
        $response->assertExactJson([
            'status' => 0,
            'buildid' => $this->project->builds()->firstOrFail()->id,
            'datafilesmd5' => [0],
        ]);
    }

    /**
     * Unlike XML submissions and data file uploads, build metadata is rejected whenever an invalid
     * token is presented, even if the project doesn't require one.
     */
    public function testRejectsBuildMetadataWithUnknownTokenWhenProjectDoesNotRequireAuthentication(): void
    {
        $response = $this->postBuildMetadata($this->buildMetadata(), Str::uuid()->toString());

        $response->assertForbidden();
        $response->assertExactJson([
            'status' => 1,
            'description' => 'Forbidden',
        ]);
        $this->assertDatabaseMissing('build', ['projectid' => $this->project->id]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    public function testRejectsDataFileWithEmptyBuildId(): void
    {
        // Requests without a buildid parameter are treated as XML submissions, so an empty build id
        // is needed to reach the unparsed submission processor.
        $response = $this->putDataFile([
            ...$this->dataFileParameters($this->createBuild()),
            'buildid' => '',
        ]);

        $response->assertBadRequest();
        $response->assertExactJson([
            'status' => 1,
            'description' => 'The buildid field is required.',
        ]);
        $this->assertDatabaseMissing('buildfile', ['md5' => md5(self::FILE_CONTENTS)]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    /**
     * @return array<string,array{string}>
     */
    public static function requiredDataFileParameterCases(): array
    {
        return [
            'type' => ['type'],
            'md5' => ['md5'],
            'filename' => ['filename'],
        ];
    }

    #[DataProvider('requiredDataFileParameterCases')]
    public function testRejectsDataFileWithMissingParameter(string $parameter): void
    {
        $buildid = $this->createBuild();
        $query = $this->dataFileParameters($buildid);
        unset($query[$parameter]);

        $response = $this->putDataFile($query);

        $response->assertBadRequest();
        $response->assertExactJson([
            'status' => 1,
            'description' => "The $parameter field is required.",
        ]);
        $this->assertDatabaseMissing('buildfile', ['buildid' => $buildid]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    /**
     * @return array<string,array{string}>
     */
    public static function invalidBuildIdCases(): array
    {
        return [
            'non-numeric' => ['abc'],
            'zero' => ['0'],
            'negative' => ['-1'],
            'fractional' => ['1.5'],
            'exponent' => ['1e3'],
            'too large' => ['99999999999999999999'],
            'nonexistent' => [(string) PHP_INT_MAX],
        ];
    }

    #[DataProvider('invalidBuildIdCases')]
    public function testRejectsDataFileWithInvalidBuildId(string $buildid): void
    {
        $response = $this->putDataFile([
            ...$this->dataFileParameters($this->createBuild()),
            'buildid' => $buildid,
        ]);

        $response->assertNotFound();
        $response->assertExactJson([
            'status' => 1,
            'description' => 'Build not found',
        ]);
        $this->assertDatabaseMissing('buildfile', ['md5' => md5(self::FILE_CONTENTS)]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    /**
     * @return array<string,array{string}>
     */
    public static function invalidTypeCases(): array
    {
        return [
            'unknown type' => ['NotAType'],
            'wrong case' => ['bazeljson'],
            'path' => ['../../../BazelJSON'],
        ];
    }

    #[DataProvider('invalidTypeCases')]
    public function testRejectsDataFileWithInvalidType(string $type): void
    {
        $buildid = $this->createBuild();

        $response = $this->putDataFile([
            ...$this->dataFileParameters($buildid),
            'type' => $type,
        ]);

        $response->assertBadRequest();
        $response->assertExactJson([
            'status' => 1,
            'description' => 'The selected type is invalid.',
        ]);
        $this->assertDatabaseMissing('buildfile', ['buildid' => $buildid]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    /**
     * @return array<string,array{string}>
     */
    public static function improperlyFormattedMd5Cases(): array
    {
        return [
            'not hexadecimal' => ['bad_md5sum'],
            'too short' => ['ffffffffffffffffffffffffffffff'],
            'too long' => ['ffffffffffffffffffffffffffffffffff'],
            'path' => ['../../../ffffffffffffffffffffffff'],
        ];
    }

    #[DataProvider('improperlyFormattedMd5Cases')]
    public function testRejectsDataFileWithImproperlyFormattedMd5(string $md5): void
    {
        $buildid = $this->createBuild();

        $response = $this->putDataFile([
            ...$this->dataFileParameters($buildid),
            'md5' => $md5,
        ]);

        $response->assertBadRequest();
        $response->assertExactJson([
            'status' => 1,
            'description' => 'The md5 format is invalid.',
        ]);
        $this->assertDatabaseMissing('buildfile', ['buildid' => $buildid]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    public function testRejectsDataFileWithMismatchedMd5(): void
    {
        $buildid = $this->createBuild();

        $response = $this->putDataFile([
            ...$this->dataFileParameters($buildid),
            'md5' => 'ffffffffffffffffffffffffffffffff',
        ]);

        $response->assertBadRequest();
        $response->assertExactJson([
            'status' => 1,
            'description' => 'md5 mismatch. expected: ffffffffffffffffffffffffffffffff, received: ' . md5(self::FILE_CONTENTS),
        ]);
        $this->assertDatabaseMissing('buildfile', ['buildid' => $buildid]);
        $this->assertDatabaseMissing('pending_submissions', ['buildid' => $buildid]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    public function testAcceptsDataFile(): void
    {
        $buildid = $this->createBuild();
        $md5 = md5(self::FILE_CONTENTS);

        $response = $this->putDataFile($this->dataFileParameters($buildid));

        $response->assertOk();
        $response->assertExactJson(['status' => 0]);
        $filename = "{$this->project->name}_-__-_BazelJSON_-_{$buildid}_-_{$md5}_-_.json";
        $this->assertSame(["inbox/$filename"], Storage::allFiles());
        $this->assertSame(self::FILE_CONTENTS, Storage::get("inbox/$filename"));
        $this->assertDatabaseHas('buildfile', [
            'buildid' => $buildid,
            'type' => 'BazelJSON',
            'md5' => $md5,
            'filename' => 'bazel.json',
        ]);
        $this->assertDatabaseHas('pending_submissions', [
            'buildid' => $buildid,
            'numfiles' => 1,
        ]);
        Queue::assertCount(1);
        Queue::assertPushed(ProcessSubmission::class, fn (ProcessSubmission $job) => $job->filename === $filename
            && $job->projectid === $this->project->id
            && $job->buildid === $buildid
            && $job->expected_md5 === $md5);
    }

    public function testRejectsDataFileWithoutTokenWhenProjectRequiresAuthentication(): void
    {
        $this->requireAuthenticatedSubmissions();
        $buildid = $this->createBuild();

        $response = $this->putDataFile($this->dataFileParameters($buildid));

        $response->assertForbidden();
        $response->assertExactJson([
            'status' => 1,
            'description' => 'Forbidden',
        ]);
        $this->assertDatabaseMissing('buildfile', ['buildid' => $buildid]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    public function testRejectsDataFileWithUnknownTokenWhenProjectRequiresAuthentication(): void
    {
        $this->requireAuthenticatedSubmissions();
        $buildid = $this->createBuild();

        $response = $this->putDataFile($this->dataFileParameters($buildid), Str::uuid()->toString());

        $response->assertForbidden();
        $response->assertExactJson([
            'status' => 1,
            'description' => 'Forbidden',
        ]);
        $this->assertDatabaseMissing('buildfile', ['buildid' => $buildid]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    public function testAcceptsDataFileWithValidTokenWhenProjectRequiresAuthentication(): void
    {
        $this->requireAuthenticatedSubmissions();
        $buildid = $this->createBuild();
        $token = $this->createSubmitOnlyToken();

        $response = $this->putDataFile($this->dataFileParameters($buildid), $token);

        $response->assertOk();
        $response->assertExactJson(['status' => 0]);
        $this->assertDatabaseHas('buildfile', ['buildid' => $buildid]);
        Queue::assertCount(1);
        Queue::assertPushed(ProcessSubmission::class, fn (ProcessSubmission $job) => $job->projectid === $this->project->id
            && $job->buildid === $buildid
            && str_contains($job->filename, '_-_' . AuthTokenService::hash($token) . '_-_'));
    }

    public function testAcceptsDataFileWithUnknownTokenWhenProjectDoesNotRequireAuthentication(): void
    {
        $buildid = $this->createBuild();

        $response = $this->putDataFile($this->dataFileParameters($buildid), Str::uuid()->toString());

        $response->assertOk();
        $response->assertExactJson(['status' => 0]);
        $this->assertDatabaseHas('buildfile', ['buildid' => $buildid]);
        Queue::assertCount(1);
        Queue::assertPushed(ProcessSubmission::class, fn (ProcessSubmission $job) => $job->projectid === $this->project->id
            && $job->buildid === $buildid);
    }
}
