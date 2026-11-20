<?php

namespace Tests\Feature\GraphQL\Queries;

use App\Enums\BuildCommandType;
use App\Models\Build;
use App\Models\BuildCommand;
use App\Models\BuildMeasurement;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class BuildMeasurementTypeTest extends TestCase
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
        // Deleting the project will delete all corresponding builds and build measurements
        $this->project->delete();

        parent::tearDown();
    }

    public function testBasicFieldAccess(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildCommand $command */
        $command = $build->commands()->create([
            'type' => BuildCommandType::CUSTOM,
            'starttime' => Carbon::now(),
            'duration' => 12345,
            'command' => Str::random(10),
            'result' => Str::random(10),
            'workingdirectory' => Str::uuid()->toString(),
        ]);

        /** @var BuildMeasurement $measurement */
        $measurement = $command->measurements()->create([
            'name' => Str::uuid()->toString(),
            'type' => Str::uuid()->toString(),
            'value' => Str::uuid()->toString(),
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    commands {
                        edges {
                            node {
                                measurements {
                                    edges {
                                        node {
                                            id
                                            name
                                            type
                                            value
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $build->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'commands' => [
                        'edges' => [
                            [
                                'node' => [
                                    'measurements' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'id' => (string) $measurement->id,
                                                    'name' => $measurement->name,
                                                    'type' => $measurement->type,
                                                    'value' => $measurement->value,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
