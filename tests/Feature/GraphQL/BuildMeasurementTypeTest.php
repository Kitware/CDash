<?php

namespace Tests\Feature\GraphQL;

use App\Enums\BuildCommandType;
use App\Models\Build;
use App\Models\BuildMeasurement;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class BuildMeasurementTypeTest extends TestCase
{
    use CreatesUsers;
    use CreatesProjects;

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

    /**
     * @return array<array<mixed>>
     */
    public static function relationships(): array
    {
        return [
            ['compileCommands', BuildCommandType::COMPILE_COMMAND],
            ['linkCommands', BuildCommandType::LINK_COMMAND],
            ['cmakeBuildCommands', BuildCommandType::CMAKE_BUILD_COMMAND],
            ['customCommands', BuildCommandType::CUSTOM_COMMAND],
        ];
    }

    /**
     * A basic test to ensure that each of the fields works for a given relationship.
     *
     * @dataProvider relationships
     */
    public function testBasicFieldAccess(string $relationshipName, BuildCommandType $commandType): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildMeasurement $measurement */
        $measurement = $build->commands()->create([
            'type' => $commandType,
            'starttime' => Carbon::now(),
            'duration' => 1,
        ])->measurements()->create([
            'name' => Str::uuid()->toString(),
            'type' => Str::uuid()->toString(),
            'value' => Str::uuid()->toString(),
        ]);

        $this->graphQL('
            query($id: ID) {
                build(id: $id) {
                    ' . $relationshipName . ' {
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
        ])->assertJson([
            'data' => [
                'build' => [
                    $relationshipName => [
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
        ], true);
    }
}
