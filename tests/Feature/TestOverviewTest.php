<?php

namespace Tests\Feature;

use App\Enums\BuildGroupType;
use App\Models\BuildGroup;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class TestOverviewTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    protected function tearDown(): void
    {
        $_GET = $_REQUEST = [];
        unset($_SERVER['REQUEST_URI']);

        $this->project->delete();

        parent::tearDown();
    }

    public function testGroupsExcludeDynamicGroups(): void
    {
        BuildGroup::factory()->for($this->project)->create([
            'type' => BuildGroupType::LATEST,
        ]);

        // The legacy code reads these superglobals directly, and Laravel doesn't populate them.
        $_SERVER['REQUEST_URI'] = '/api/v1/testOverview.php';
        $_GET = $_REQUEST = ['date' => '2025-01-01'];
        $response = $this->getJson('/api/v1/testOverview.php?project=' . urlencode($this->project->name))
            ->assertOk();

        self::assertEqualsCanonicalizing(
            ['Non-Experimental Builds', 'Nightly', 'Continuous', 'Experimental'],
            $response->collect('groups')->pluck('name')->all(),
        );
    }
}
