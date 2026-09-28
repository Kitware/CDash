<?php

namespace Tests\Feature\Console;

use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class DeleteProjectCommandTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    public function testFailsForNonexistentProject(): void
    {
        Artisan::call('project:delete', ['name' => 'does-not-exist']);

        self::assertStringContainsString('Project does-not-exist does not exist', Artisan::output());
    }

    public function testDeletesProjectSynchronously(): void
    {
        $project = $this->makePublicProject('DeleteProjectCommandTest_Project');

        Artisan::call('project:delete', ['name' => $project->name]);

        self::assertStringContainsString("Deleted project {$project->name}", Artisan::output());
        self::assertDatabaseMissing(Project::class, ['id' => $project->id]);
    }
}
