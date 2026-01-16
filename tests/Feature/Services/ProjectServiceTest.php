<?php

namespace Tests\Feature\Services;

use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected ?Project $project = null;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->project?->delete();

        parent::tearDown();
    }

    public function testCreatesDefaultBuildGroups(): void
    {
        $project = ProjectService::create([
            'name' => Str::uuid()->toString(),
            'public' => Project::ACCESS_PUBLIC,
        ]);

        $project = $project->refresh();

        self::assertTrue($project->exists());
        self::assertSame(3, $project->buildgroups()->count());
        self::assertEquals(
            ['Nightly', 'Continuous', 'Experimental'],
            $project->buildgroups()->pluck('name')->toArray(),
        );
    }
}
