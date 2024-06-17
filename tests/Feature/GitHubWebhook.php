<?php

namespace Tests\Feature;

use Tests\TestCase;

class GitHubWebhook extends TestCase
{
    protected string $endpoint = '/api/v1/GitHub/webhook.php';

    public function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_METHOD'] = 'POST';

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
        $_SERVER['HTTP_X_HUB_SIGNATURE'] = 'zzz=foo';
        $this->post($this->endpoint)
            ->assertServerError()
            ->assertJson(['error' => "Hash algorithm 'zzz' is not supported."], true);
    }

    public function testWebhookWithWrongSignature()
    {
        $_SERVER['HTTP_X_HUB_SIGNATURE'] = 'sha1=wrong secret';
        $this->post($this->endpoint)
            ->assertServerError()
            ->assertJson(['error' => 'Hook secret does not match.'], true);
    }

    public function testWebhookWithCorrectSignature()
    {
        $hash = hash_hmac('sha1', '', 'mock secret');
        $_SERVER['HTTP_X_HUB_SIGNATURE'] = "sha1=$hash";
        $this->post($this->endpoint)->assertNoContent();
    }
}
