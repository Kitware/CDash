<?php

namespace App\Http\Controllers;

use CDash\Model\Repository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

final class GitHubWebhookController extends AbstractController
{
    public function __invoke(Request $request): Response
    {
        $secret = config('cdash.github_webhook_secret');
        if ($secret !== null) {
            self::verifySignature($request, (string) $secret);
        }

        // Note: json() decodes the body regardless of the request's Content-Type.
        switch ($request->header('X-GitHub-Event', '')) {
            case 'check_run':
                // Avoid an infinite loop of reacting to our own activity.
                if ($request->json('check_run.name') !== 'CDash' || $request->json('action') === 'rerequested') {
                    Repository::createOrUpdateCheck($request->json('check_run.head_sha'));
                }
                break;
            case 'status':
                Repository::createOrUpdateCheck($request->json('sha'));
                break;
        }

        return response()->noContent();
    }

    /**
     * Adapted from https://gist.github.com/milo/daed6e958ea534e4eba3
     */
    private static function verifySignature(Request $request, string $secret): void
    {
        $signature = $request->headers->get('X-Hub-Signature');
        if ($signature === null) {
            self::fail("HTTP header 'X-Hub-Signature' is missing.");
        }

        [$algo, $hash] = explode('=', $signature, 2) + ['', ''];
        if (!in_array($algo, hash_hmac_algos(), true)) {
            self::fail("Hash algorithm '$algo' is not supported.");
        }

        if (!hash_equals(hash_hmac($algo, $request->getContent(), $secret), $hash)) {
            self::fail('Hook secret does not match.');
        }
    }

    private static function fail(string $message): never
    {
        Log::warning($message, [
            'function' => 'GitHub webhook',
        ]);
        abort(500, $message);
    }
}
