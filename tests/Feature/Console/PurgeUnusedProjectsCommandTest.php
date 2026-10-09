<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class PurgeUnusedProjectsCommandTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    public function testDeletesProjectsWithoutBuilds(): void
    {
        $project = $this->makePublicProject();

        $this->expectOutputString("Deleting project: {$project->name}\n");
        Artisan::call('projects:clean');

        self::assertModelMissing($project);
    }

    public function testKeepsProjectsWithBuilds(): void
    {
        $project = $this->makePublicProject();
        $project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->expectOutputString('');
        Artisan::call('projects:clean');

        self::assertModelExists($project);
    }
}
