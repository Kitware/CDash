<?php

namespace Database\Seeders;

use App\Models\AuthToken;
use App\Models\Project;
use App\Models\User;
use App\Utils\AuthTokenUtil;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Traits\CreatesSubmissions;

class DatabaseSeeder extends Seeder
{
    use CreatesSubmissions;

    private string $admin_auth_token = '';

    /**
     * The main database seeder method.
     */
    public function run(): void
    {
        User::factory(1000)->normalUser()->create();
        $this->admin_auth_token = $this->createAdminUser();

        $this->createPublicProject();
        $this->createTrilinosProject();
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
        $response = Http::withToken($this->admin_auth_token)->post(url('/api/v1/project.php'), [
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

        $files_to_submit = array_map(fn ($filename) => app_path("/cdash/tests/data/ActualTrilinosSubmission/$filename"), $files_to_submit);

        $this->submitFiles($project->name, $files_to_submit, 1);
    }
}
