<?php

namespace Tests\Feature\Submission;

use App\Enums\AuthTokenScope;
use App\Jobs\ProcessSubmission;
use App\Models\Project;
use App\Models\User;
use App\Services\AuthTokenService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class XmlSubmissionTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

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
     * @param array<string,string> $query
     *
     * @return TestResponse<Response>
     */
    private function submit(string $file_to_submit, array $query, ?string $auth_token = null): TestResponse
    {
        $server = $auth_token === null
            ? []
            : $this->transformHeadersToServerVars(['Authorization' => "Bearer $auth_token"]);

        $file_contents = file_get_contents($file_to_submit);
        if ($file_contents === false) {
            throw new Exception('Unable to open submission file.');
        }

        return $this->call('PUT', '/submit.php?' . http_build_query($query), [], [], [], $server, $file_contents);
    }

    /**
     * @param TestResponse<Response> $response
     */
    private function assertXmlResponse(TestResponse $response, string $expected_elements): void
    {
        $content = $response->getContent();
        $this->assertIsString($content);

        $this->assertXmlStringEqualsXmlString(
            '<cdash version="' . Config::string('cdash.version') . '">' . $expected_elements . '</cdash>',
            $content,
        );
    }

    public function testRejectsSubmissionWithoutProjectName(): void
    {
        $response = $this->submit(base_path('tests/Feature/Submission/data/configure.xml'), []);

        $response->assertBadRequest();
        $this->assertXmlResponse($response, '
            <status>ERROR</status>
            <message>No project name provided.</message>
        ');
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    public function testRejectsSubmissionWithInvalidProjectName(): void
    {
        $response = $this->submit(base_path('tests/Feature/Submission/data/configure.xml'), [
            'project' => 'invalid/name',
        ]);

        $response->assertBadRequest();
        $this->assertXmlResponse($response, '
            <status>ERROR</status>
            <message>Invalid project name: invalid/name</message>
        ');
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    public function testRejectsSubmissionToNonexistentProject(): void
    {
        $response = $this->submit(base_path('tests/Feature/Submission/data/configure.xml'), [
            'project' => 'NonexistentProject_' . Str::uuid()->toString(),
        ]);

        $response->assertNotFound();
        $this->assertXmlResponse($response, '
            <status>ERROR</status>
            <message>The requested project does not exist.</message>
        ');
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    public function testRejectsSubmissionWithImproperlyFormattedMd5(): void
    {
        $response = $this->submit(base_path('tests/Feature/Submission/data/configure.xml'), [
            'project' => $this->project->name,
            'MD5' => 'bad_md5sum',
        ]);

        $response->assertBadRequest();
        $this->assertXmlResponse($response, '
            <status>ERROR</status>
            <message>Provided md5 hash \'bad_md5sum\' is improperly formatted.</message>
        ');
        $this->assertDatabaseMissing('build', ['projectid' => $this->project->id]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    public function testRejectsSubmissionWithMismatchedMd5(): void
    {
        $file_to_submit = base_path('tests/Feature/Submission/data/configure.xml');
        $md5 = md5_file($file_to_submit);
        $this->assertIsString($md5);

        $response = $this->submit($file_to_submit, [
            'project' => $this->project->name,
            'MD5' => 'ffffffffffffffffffffffffffffffff',
        ]);

        $response->assertBadRequest();
        $this->assertXmlResponse($response, "
            <status>ERROR</status>
            <message>md5 mismatch. expected: ffffffffffffffffffffffffffffffff, received: $md5</message>
        ");
        $this->assertDatabaseMissing('build', ['projectid' => $this->project->id]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    public function testAcceptsSubmissionWithoutMd5(): void
    {
        $response = $this->submit(base_path('tests/Feature/Submission/data/configure.xml'), [
            'project' => $this->project->name,
        ]);

        $response->assertOk();
        $this->assertXmlResponse($response, '
            <message></message>
            <status>OK</status>
        ');
        Queue::assertCount(1);
        Queue::assertPushed(ProcessSubmission::class, fn (ProcessSubmission $job) => $job->projectid === $this->project->id
            && $job->buildid === null
            && $job->expected_md5 === '');
    }

    public function testAcceptsSubmissionWithMd5(): void
    {
        $file_to_submit = base_path('tests/Feature/Submission/data/configure.xml');
        $md5 = md5_file($file_to_submit);
        $this->assertIsString($md5);

        $response = $this->submit($file_to_submit, [
            'project' => $this->project->name,
            'MD5' => $md5,
        ]);

        $response->assertOk();
        $this->assertXmlResponse($response, '
            <message></message>
            <status>OK</status>
        ');
        Queue::assertCount(1);
        Queue::assertPushed(ProcessSubmission::class, fn (ProcessSubmission $job) => $job->projectid === $this->project->id
            && $job->buildid === null
            && $job->expected_md5 === $md5);
    }

    public function testAcceptsSubmissionWithUppercaseMd5(): void
    {
        $file_to_submit = base_path('tests/Feature/Submission/data/configure.xml');
        $md5 = md5_file($file_to_submit);
        $this->assertIsString($md5);
        $md5 = strtoupper($md5);

        $response = $this->submit($file_to_submit, [
            'project' => $this->project->name,
            'MD5' => $md5,
        ]);

        $response->assertOk();
        $this->assertXmlResponse($response, '
            <message></message>
            <status>OK</status>
        ');
        Queue::assertCount(1);
        Queue::assertPushed(ProcessSubmission::class, fn (ProcessSubmission $job) => $job->projectid === $this->project->id
            && $job->buildid === null
            && $job->expected_md5 === $md5);
    }

    public function testAcceptsSubmissionWithBuildMetadata(): void
    {
        $response = $this->submit(base_path('tests/Feature/Submission/data/configure.xml'), [
            'project' => $this->project->name,
            'build' => 'regular_submission',
            'site' => 'submission.site',
            'stamp' => '20240501-0100-Nightly',
        ]);

        $response->assertOk();
        $this->assertSame(1, $this->project->builds()->count());
        $buildid = $this->project->builds()->firstOrFail()->id;
        $this->assertXmlResponse($response, "
            <message></message>
            <status>OK</status>
            <buildId>$buildid</buildId>
        ");
        Queue::assertCount(1);
        Queue::assertPushed(ProcessSubmission::class, fn (ProcessSubmission $job) => $job->projectid === $this->project->id
            && $job->buildid === $buildid
            && $job->expected_md5 === '');
    }

    /**
     * @return array<string,array{array<string,string>}>
     */
    public static function partialBuildMetadataCases(): array
    {
        return [
            'without build' => [[
                'site' => 'submission.site',
                'stamp' => '20240501-0100-Nightly',
            ]],
            'without site' => [[
                'build' => 'regular_submission',
                'stamp' => '20240501-0100-Nightly',
            ]],
            'without stamp' => [[
                'build' => 'regular_submission',
                'site' => 'submission.site',
            ]],
            'with blank build' => [[
                'build' => '',
                'site' => 'submission.site',
                'stamp' => '20240501-0100-Nightly',
            ]],
            'with blank site' => [[
                'build' => 'regular_submission',
                'site' => '',
                'stamp' => '20240501-0100-Nightly',
            ]],
            'with blank stamp' => [[
                'build' => 'regular_submission',
                'site' => 'submission.site',
                'stamp' => '',
            ]],
        ];
    }

    /**
     * @param array<string,string> $build_metadata
     */
    #[DataProvider('partialBuildMetadataCases')]
    public function testAcceptsSubmissionWithPartialBuildMetadata(array $build_metadata): void
    {
        $response = $this->submit(base_path('tests/Feature/Submission/data/configure.xml'), [
            'project' => $this->project->name,
            ...$build_metadata,
        ]);

        $response->assertOk();
        $this->assertXmlResponse($response, '
            <message></message>
            <status>OK</status>
        ');
        $this->assertDatabaseMissing('build', ['projectid' => $this->project->id]);
        Queue::assertCount(1);
        Queue::assertPushed(ProcessSubmission::class, fn (ProcessSubmission $job) => $job->projectid === $this->project->id
            && $job->buildid === null
            && $job->expected_md5 === '');
    }

    public function testRejectsSubmissionWithoutTokenWhenProjectRequiresAuthentication(): void
    {
        $this->project->authenticatesubmissions = true;
        $this->project->save();

        $response = $this->submit(base_path('tests/Feature/Submission/data/configure.xml'), [
            'project' => $this->project->name,
        ]);

        $response->assertForbidden();
        $this->assertXmlResponse($response, '
            <status>ERROR</status>
            <message>Invalid Token</message>
        ');
        $this->assertDatabaseMissing('build', ['projectid' => $this->project->id]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    public function testRejectsSubmissionWithUnknownTokenWhenProjectRequiresAuthentication(): void
    {
        $this->project->authenticatesubmissions = true;
        $this->project->save();

        $response = $this->submit(base_path('tests/Feature/Submission/data/configure.xml'), [
            'project' => $this->project->name,
        ], Str::uuid()->toString());

        $response->assertForbidden();
        $this->assertXmlResponse($response, '
            <status>ERROR</status>
            <message>Invalid Token</message>
        ');
        $this->assertDatabaseMissing('build', ['projectid' => $this->project->id]);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::allFiles());
    }

    public function testAcceptsSubmissionWithValidTokenWhenProjectRequiresAuthentication(): void
    {
        $this->project->authenticatesubmissions = true;
        $this->project->save();
        $token = AuthTokenService::generate(
            User::factory()->create()->id,
            $this->project->id,
            AuthTokenScope::SUBMIT_ONLY,
            'Submission test token',
        )['raw_token'];

        $response = $this->submit(base_path('tests/Feature/Submission/data/configure.xml'), [
            'project' => $this->project->name,
        ], $token);

        $response->assertOk();
        $this->assertXmlResponse($response, '
            <message></message>
            <status>OK</status>
        ');
        Queue::assertCount(1);
        Queue::assertPushed(ProcessSubmission::class, fn (ProcessSubmission $job) => $job->projectid === $this->project->id
            && $job->buildid === null
            && $job->expected_md5 === ''
            && str_contains($job->filename, '_-_' . AuthTokenService::hash($token) . '_-_'));
    }

    public function testAcceptsSubmissionWithUnknownTokenWhenProjectDoesNotRequireAuthentication(): void
    {
        $response = $this->submit(base_path('tests/Feature/Submission/data/configure.xml'), [
            'project' => $this->project->name,
        ], Str::uuid()->toString());

        $response->assertOk();
        $this->assertXmlResponse($response, '
            <message></message>
            <status>OK</status>
        ');
        Queue::assertCount(1);
        Queue::assertPushed(ProcessSubmission::class, fn (ProcessSubmission $job) => $job->projectid === $this->project->id
            && $job->buildid === null
            && $job->expected_md5 === '');
    }
}
