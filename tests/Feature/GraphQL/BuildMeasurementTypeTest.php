<?php

namespace Tests\Feature\GraphQL;

use App\Enums\BuildMeasurementType;
use App\Models\Build;
use App\Models\BuildMeasurement;
use App\Models\Project;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class BuildMeasurementTypeTest extends TestCase
{
    use CreatesUsers;
    use CreatesProjects;

    private Project $project;
    private Project $project2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
        $this->project2 = $this->makePrivateProject();
    }

    protected function tearDown(): void
    {
        // Deleting the project will delete all corresponding builds and build measurements
        $this->project->delete();
        $this->project2->delete();

        parent::tearDown();
    }

    /**
     * A basic test to ensure that each of the fields works
     */
    public function testBasicFieldAccess(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildMeasurement $measurement */
        $measurement = $build->measurements()->create([
            'name' => Str::uuid()->toString(),
            'source' => Str::uuid()->toString(),
            'type' => BuildMeasurementType::TARGET,
            'value' => 5,
        ]);

        $this->graphQL('
            query($id: ID) {
                build(id: $id) {
                    measurements {
                        edges {
                            node {
                                id
                                name
                                source
                                type
                                value
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $build->id,
        ])->assertJson([
            'data' => [
                'build' => [
                    'measurements' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $measurement->id,
                                    'name' => $measurement->name,
                                    'source' => $measurement->source,
                                    'type' => 'TARGET',
                                    'value' => '5',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], true);
    }

    public function testMeasurementFiltering(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $build->measurements()->create([
            'name' => Str::uuid()->toString(),
            'source' => Str::uuid()->toString(),
            'type' => BuildMeasurementType::TARGET,
            'value' => 4,
        ]);

        $build->measurements()->create([
            'name' => Str::uuid()->toString(),
            'source' => Str::uuid()->toString(),
            'type' => BuildMeasurementType::TARGET,
            'value' => 5,
        ]);

        $build->measurements()->create([
            'name' => Str::uuid()->toString(),
            'source' => Str::uuid()->toString(),
            'type' => BuildMeasurementType::TARGET,
            'value' => 6,
        ]);

        $this->graphQL('
            query($id: ID) {
                build(id: $id) {
                    measurements(filters: {
                        gt: {
                            value: "4"
                        }
                    }) {
                        edges {
                            node {
                                value
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $build->id,
        ])->assertJson([
            'data' => [
                'build' => [
                    'measurements' => [
                        'edges' => [
                            [
                                'node' => [
                                    'value' => '6',
                                ],
                            ],
                            [
                                'node' => [
                                    'value' => '5',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], true);
    }
}
