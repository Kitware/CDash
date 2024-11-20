<?php

namespace Tests\Traits;

use Exception;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

trait CreatesSubmissions
{
    /**
     * Submit files to a given project.  Requests are batched for better performance.
     *
     * @param array<string> $files_to_submit
     * @param int<1,max> $batch_size
     */
    private function submitFiles(string $project_name, array $files_to_submit, int $batch_size = 20, ?string $auth_token = null): void
    {
        $num_failed_submissions = 0;
        foreach (array_chunk($files_to_submit, $batch_size) as $filenames_chunk) {
            $responses = Http::pool(function (Pool $pool) use ($project_name, $filenames_chunk, $auth_token) {
                foreach ($filenames_chunk as $fixture) {
                    $file_contents = file_get_contents($fixture);
                    if ($file_contents === false) {
                        throw new Exception('Unable to open submission file.');
                    }
                    $command = $pool->as($fixture)->withBody($file_contents);
                    if ($auth_token !== null) {
                        $command = $command->withToken($auth_token);
                    }
                    $command->get(url('/submit.php'), [
                        'project' => $project_name,
                    ]);
                }
            });

            foreach ($responses as $file => $response) {
                if (!($response instanceof \Illuminate\Http\Client\Response) || !$response->ok()) {
                    $num_failed_submissions++;
                }
            }
        }
        if ($num_failed_submissions > 0) {
            throw new Exception("$num_failed_submissions submissions failed!");
        }
    }
}
