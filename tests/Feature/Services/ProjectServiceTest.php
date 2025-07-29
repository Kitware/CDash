<?php

namespace Tests\Feature\Services;

use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectServiceTest extends TestCase
{
    protected ?Project $project = null;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->project?->delete();
    }

    public function testCreatesDefaultBuildGroups(): void
    {
        $project = ProjectService::create([
            'name' => Str::uuid()->toString(),
            'public' => Project::ACCESS_PUBLIC,
        ]);

        $project = $project->refresh();

        self::assertTrue($project->exists());
        self::assertEquals(3, $project->buildgroups()->count());
    }
}
