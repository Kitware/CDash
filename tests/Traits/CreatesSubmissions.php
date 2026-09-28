<?php

namespace Tests\Traits;

use Exception;

trait CreatesSubmissions
{
    /**
     * Submit a file to a given project using an in-process test request, so that the
     * submission runs within the calling test's database transaction.
     *
     * @return int|null the build id included in the submission response, or null if the
     *                  response didn't include one
     */
    private function makeSubmission(string $project_name, string $file_to_submit, int $expected_status = 200, ?string $auth_token = null): ?int
    {
        $server = $auth_token === null
            ? []
            : $this->transformHeadersToServerVars(['Authorization' => "Bearer $auth_token"]);

        $file_contents = file_get_contents($file_to_submit);
        if ($file_contents === false) {
            throw new Exception('Unable to open submission file.');
        }

        $response = $this->call('GET', '/submit.php', ['project' => $project_name], [], [], $server, $file_contents);
        $response->assertStatus($expected_status);

        $content = $response->getContent();
        if ($content === false) {
            return null;
        }

        $previous_libxml_setting = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_clear_errors();
        libxml_use_internal_errors($previous_libxml_setting);

        if ($xml === false || !isset($xml->buildId)) {
            return null;
        }

        return (int) $xml->buildId;
    }
}
