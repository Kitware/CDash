<?php

namespace Tests\Feature;

use App\Models\Build;
use App\Models\BuildUpdate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class GitHubWebhook extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    protected string $endpoint = '/api/v1/GitHub/webhook.php';

    public function setUp(): void
    {
        parent::setUp();

        config(['cdash.github_webhook_secret' => 'mock secret']);
    }

    public function testWebhookWithoutRequiredSignature(): void
    {
        $this->post($this->endpoint)
            ->assertServerError()
            ->assertJson(['error' => "HTTP header 'X-Hub-Signature' is missing."], true);
    }

    public function testWebhookWithUnsupportedAlgorithm(): void
    {
        $this->withHeader('X-Hub-Signature', 'zzz=foo')
            ->post($this->endpoint)
            ->assertServerError()
            ->assertJson(['error' => "Hash algorithm 'zzz' is not supported."], true);
    }

    public function testWebhookWithWrongSignature(): void
    {
        $this->withHeader('X-Hub-Signature', 'sha1=wrong secret')
            ->post($this->endpoint)
            ->assertServerError()
            ->assertJson(['error' => 'Hook secret does not match.'], true);
    }

    public function testWebhookWithCorrectSignature(): void
    {
        $hash = hash_hmac('sha1', '', 'mock secret');
        $this->withHeader('X-Hub-Signature', "sha1=$hash")
            ->post($this->endpoint)
            ->assertNoContent();
    }

    public function testWebhookWithoutSecretDoesNotRequireSignature(): void
    {
        config(['cdash.github_webhook_secret' => null]);

        $this->post($this->endpoint)->assertNoContent();
    }

    public function testSignatureCoversRequestBody(): void
    {
        $body = (string) json_encode(['sha' => 'abc']);
        $hash = hash_hmac('sha1', $body . ' ', 'mock secret');

        $this->call('POST', $this->endpoint, server: [
            'HTTP_X_GITHUB_EVENT' => 'status',
            'HTTP_X_HUB_SIGNATURE' => "sha1=$hash",
            'CONTENT_TYPE' => 'application/json',
        ], content: $body)
            ->assertServerError()
            ->assertJson(['error' => 'Hook secret does not match.'], true);
    }

    public function testStatusEventCreatesCheck(): void
    {
        $this->sendEvent('status', ['sha' => $this->createCommit()])
            ->assertServerError()
            ->assertJson(['error' => 'No repository interface defined for gitlab'], true);
    }

    public function testStatusEventForUnknownCommit(): void
    {
        $this->createCommit();

        $this->sendEvent('status', ['sha' => Str::uuid()->toString()])->assertNoContent();
    }

    public function testCheckRunEventCreatesCheck(): void
    {
        $this->sendEvent('check_run', [
            'action' => 'completed',
            'check_run' => [
                'name' => 'Some other check',
                'head_sha' => $this->createCommit(),
            ],
        ])
            ->assertServerError()
            ->assertJson(['error' => 'No repository interface defined for gitlab'], true);
    }

    public function testCheckRunEventIgnoresOwnCheck(): void
    {
        $this->sendEvent('check_run', [
            'action' => 'completed',
            'check_run' => [
                'name' => 'CDash',
                'head_sha' => $this->createCommit(),
            ],
        ])->assertNoContent();
    }

    public function testCheckRunEventRecreatesRerequestedOwnCheck(): void
    {
        $this->sendEvent('check_run', [
            'action' => 'rerequested',
            'check_run' => [
                'name' => 'CDash',
                'head_sha' => $this->createCommit(),
            ],
        ])
            ->assertServerError()
            ->assertJson(['error' => 'No repository interface defined for gitlab'], true);
    }

    public function testUnknownEventIsIgnored(): void
    {
        $this->sendEvent('push', ['sha' => $this->createCommit()])->assertNoContent();
    }

    /**
     * Creates a build for a commit in a project which uses an unsupported repository viewer, so that
     * creating a check for that commit fails with an error that shows the check was attempted.
     */
    private function createCommit(): string
    {
        $project = $this->makePublicProject();
        $project->cvsviewertype = 'gitlab';
        $project->save();

        /** @var Build $build */
        $build = $project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);
        $update = BuildUpdate::factory()->create();
        $build->updateStep()->associate($update)->save();

        return $update->revision;
    }

    /**
     * @param array<string,mixed> $payload
     *
     * @return TestResponse<Response>
     */
    private function sendEvent(string $event, array $payload): TestResponse
    {
        $body = (string) json_encode($payload);
        $hash = hash_hmac('sha1', $body, 'mock secret');

        return $this->call('POST', $this->endpoint, server: [
            'HTTP_X_GITHUB_EVENT' => $event,
            'HTTP_X_HUB_SIGNATURE' => "sha1=$hash",
            'CONTENT_TYPE' => 'application/json',
        ], content: $body);
    }
}
