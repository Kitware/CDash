<?php

namespace Tests\Feature;

use App\Enums\BuildGroupType;
use App\Models\BuildGroup;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class ManageOverviewTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    private Project $project;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
        $this->admin = User::factory()->adminUser()->create();
    }

    protected function tearDown(): void
    {
        $this->project->delete();
        $this->admin->delete();

        parent::tearDown();
    }

    public function testAvailableGroupsExcludeDynamicGroups(): void
    {
        BuildGroup::factory()->for($this->project)->create([
            'type' => BuildGroupType::LATEST,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/manageOverview.php?projectid={$this->project->id}")
            ->assertOk();

        // Nightly is part of the overview by default, so it isn't available to add.
        self::assertEqualsCanonicalizing(
            ['Continuous', 'Experimental'],
            $response->collect('availablegroups')->pluck('name')->all(),
        );
    }
}
