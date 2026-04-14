<?php

namespace Database\Seeders;

use App\Models\User;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class DatabaseSeeder extends Seeder
{
    use CreatesProjects;
    use CreatesSubmissions;

    /**
     * The main database seeder method.
     */
    public function run(): void
    {
        $this->createAdminUser();
        User::factory(1000)->normalUser()->create();

        $this->createPublicProject();
        $this->createTrilinosProject();
        $this->createInstrumentationProject();
    }

    private function createAdminUser(): void
    {
        Artisan::call('user:save --email=admin@cdash --firstname=Admin --lastname=User --password=12345 --institution=Kitware --admin=true');
    }

    private function createPublicProject(): void
    {
        $project = $this->makePublicProject('PublicProject');
        $project->description = 'A testing playground for basic submissions.';
        $project->save();
    }

    private function createTrilinosProject(): void
    {
        $project = $this->makePublicProject('Trilinos');
        $project->description = 'Submission files donated by the Trilinos project.';
        $project->save();

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

    private function createInstrumentationProject(): void
    {
        $project = $this->makePublicProject('Instrumentation');
        $project->description = 'Submissions containing build instrumentation data.';
        $project->save();

        $this->submitFiles($project->name, [
            base_path('tests/Feature/Submission/Build/data/with_instrumentation_data.xml'),
        ], 1);
    }
}
