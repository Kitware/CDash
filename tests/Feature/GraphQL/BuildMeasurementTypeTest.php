<?php

namespace Tests\Feature\GraphQL;

use App\Models\Build;
use App\Models\Project;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class BuildMeasurementTypeTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    protected function tearDown(): void
    {
        // Deleting the project will delete all corresponding builds and build measurements
        $this->project->delete();

        parent::tearDown();
    }
}
