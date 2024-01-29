<?php

use App\Models\AuthToken;
use App\Models\Project;
use App\Models\User;
use App\Utils\AuthTokenUtil;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;

class DatabaseSeeder extends Seeder
{
    private string $admin_auth_token = '';

    /**
     * The main database seeder method.
     */
    public function run(): void
    {
        $this->admin_auth_token = $this->createAdminUser();

        $this->createPublicProject();
        $this->createTrilinosProject();
    }

    /**
     * Submit files to a given project.  Requests are batched for better performance.
     *
     * @param array<string> $fixtures
     * @param int<1,max> $batch_size
     */
    private function submit(string $project_name, array $fixtures, int $batch_size = 20): void
    {
        echo 'Submitting ' . count($fixtures) . " files to project $project_name..." . PHP_EOL;

        $num_failed_submissions = 0;
        foreach (array_chunk($fixtures, $batch_size) as $fixtures_chunk) {
            $responses = Http::pool(function (Pool $pool) use ($project_name, $fixtures_chunk) {
                foreach ($fixtures_chunk as $fixture) {
                    $file_contents = file_get_contents($fixture);
                    if ($file_contents === false) {
                        throw new Exception('Unable to open submission file.');
                    }
                    $pool->as($fixture)->withBody($file_contents)->get(url('/submit.php'), [
                        'project' => $project_name,
                    ]);
                }
            });

            foreach ($responses as $fixture => $response) {
                if ($response->ok()) {
                    echo "Submitted file $fixture to project $project_name successfully." . PHP_EOL;
                } else {
                    echo "Failed to submit file $fixture to project $project_name." . PHP_EOL;
                    $num_failed_submissions++;
                }
            }
        }
        if ($num_failed_submissions > 0) {
            throw new Exception("$num_failed_submissions submissions failed!");
        }
    }

    /**
     * Returns an auth token associated with an admin user.  This token can be used to perform admin actions
     * when seeding the database.
     */
    private function createAdminUser(): string
    {
        Artisan::call('user:save --email=admin@cdash --firstname=Admin --lastname=User --password=12345 --institution=Kitware --admin=true');

        $admin = User::where('email', 'admin@cdash')->firstOrFail();
        $auth_token = AuthTokenUtil::generateToken($admin->id, -1, AuthToken::SCOPE_FULL_ACCESS, 'Basic full access token');
        return $auth_token['raw_token'];
    }

    /**
     * @param array<string,mixed> $details
     */
    private function createProject(array $details): Project
    {
        $response = HTTP::withToken($this->admin_auth_token)->post(url('/api/v1/project.php'), [
            'project' => $details,
            'Submit' => true,
        ]);

        return Project::findOrFail((int) $response['project']['Id']);
    }

    private function createPublicProject(): void
    {
        $this->createProject([
            'Name' => 'PublicProject',
            'Description' => 'A testing playground for basic submissions.',
            'Public' => 1,
        ]);
    }

    private function createTrilinosProject(): void
    {
        $project = $this->createProject([
            'Name' => 'Trilinos',
            'Description' => 'Submission files donated by the Trilinos project.',
            'Public' => 1,
            'ViewSubProjectsLink' => 1,
        ]);

        $files_to_submit = file_get_contents(app_path('/cdash/tests/data/ActualTrilinosSubmission/orderedFileList.txt'));
        if ($files_to_submit === false) {
            throw new Exception('Failed to open Trilinos project submission files list.');
        }
        $files_to_submit = preg_split('/\s+/', trim($files_to_submit));
        if (!is_array($files_to_submit) || count($files_to_submit) === 0) {
            throw new Exception('No submission files found for Trilinos project');
        }

        $files_to_submit = array_map(function ($filename) {
            return app_path("/cdash/tests/data/ActualTrilinosSubmission/$filename");
        }, $files_to_submit);

        $this->submit($project->name, $files_to_submit, 1);
    }
}
