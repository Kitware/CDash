<?php

namespace Tests\Feature\GraphQL;

use App\Enums\BuildCommandType;
use App\Models\Build;
use App\Models\BuildCommand;
use App\Models\BuildCommandOutput;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class BuildCommandOutputTypeTest extends TestCase
{
    use CreatesProjects;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    protected function tearDown(): void
    {
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

        /** @var BuildCommandOutput $output */
        $output = $command->outputs()->create([
            'name' => Str::uuid()->toString(),
            'size' => fake()->randomDigitNotNull(),
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    commands {
                        edges {
                            node {
                                outputs {
                                    edges {
                                        node {
                                            id
                                            name
                                            size
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
                                    'outputs' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'id' => (string) $output->id,
                                                    'name' => $output->name,
                                                    'size' => $output->size,
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
