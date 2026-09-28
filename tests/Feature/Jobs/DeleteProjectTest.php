<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DeleteProject;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class DeleteProjectTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    public function testHandleDoesNothingForMissingProject(): void
    {
        DeleteProject::dispatch(123456789);

        self::assertDatabaseMissing(Project::class, ['id' => 123456789]);
    }

    public function testHandleDeletesProject(): void
    {
        $project = $this->makePublicProject();

        DeleteProject::dispatch($project->id);

        self::assertDatabaseMissing(Project::class, ['id' => $project->id]);
    }
}
