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

    /**
     * Submit a file to a given project using the two-phase "unparsed" submission process: build
     * metadata is POSTed first to obtain a build id, then the data file is PUT to that build.
     *
     * @param string $type the type of data file being submitted, e.g. "JavaJSONTar"
     *
     * @return int|null the build id assigned during the POST phase, or null if the POST phase was
     *                  rejected before a build id was assigned
     */
    private function makeUnparsedSubmission(string $project_name, string $file_to_submit, string $type, int $expected_status = 200, ?string $auth_token = null): ?int
    {
        $server = $auth_token === null
            ? []
            : $this->transformHeadersToServerVars(['Authorization' => "Bearer $auth_token"]);

        $file_contents = file_get_contents($file_to_submit);
        if ($file_contents === false) {
            throw new Exception('Unable to open submission file.');
        }

        $filename = basename($file_to_submit);
        $md5 = md5($file_contents);
        $time = time();

        $post_response = $this->call('POST', '/submit.php', [
            'project' => $project_name,
            'build' => pathinfo($filename, PATHINFO_FILENAME),
            'site' => 'localhost',
            'stamp' => gmdate('Ymd-Hi', $time) . '-Experimental',
            'starttime' => $time,
            'endtime' => $time,
            'datafilesmd5' => [$md5],
        ], [], [], $server);

        if ($post_response->status() !== 200) {
            $post_response->assertStatus($expected_status);
            return null;
        }

        $buildid = $post_response->json('buildid');
        if (!is_numeric($buildid)) {
            throw new Exception('Unparsed submission POST response did not include a build id.');
        }
        $buildid = (int) $buildid;

        // PUT request parameters are placed in the request body, so these must go in the URL instead.
        $query = http_build_query([
            'type' => $type,
            'md5' => $md5,
            'filename' => $filename,
            'buildid' => $buildid,
        ]);
        $this->call('PUT', "/submit.php?$query", [], [], [], $server, $file_contents)
            ->assertStatus($expected_status);

        return $buildid;
    }
}
