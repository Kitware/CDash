<?php

namespace Tests\Feature\GraphQL;

use App\Enums\BuildCommandType;
use App\Enums\TargetType;
use App\Models\Build;
use App\Models\BuildCommand;
use App\Models\Project;
use App\Models\Target;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class TargetTypeTest extends TestCase
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
        $this->project->delete();

        parent::tearDown();
    }

    public function testTypeEnumValues(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        // Create one target of each type
        $targets = [];
        foreach (TargetType::cases() as $target_type) {
            $targets[] = [
                'name' => Str::uuid()->toString(),
                'type' => $target_type,
            ];
        }
        $build->targets()->createMany($targets);

        $target_node_list = [];
        foreach ($targets as $target) {
            $target_node_list[] = [
                'node' => [
                    'name' => $target['name'],
                    'type' => $target['type']->name,
                ],
            ];
        }

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    targets {
                        edges {
                            node {
                                name
                                type
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
                    'targets' => [
                        'edges' => $target_node_list,
                    ],
                ],
            ],
        ]);
    }

    public function testFilterByName(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $build->targets()->createMany([
            [
                'name' => 'name1',
                'type' => TargetType::UNKNOWN,
            ],
            [
                'name' => 'name2',
                'type' => TargetType::UNKNOWN,
            ],
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    targets(
                        filters: {
                            eq: {
                                name: "name2"
                            }
                        }
                    ){
                        edges {
                            node {
                                name
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
                    'targets' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'name2',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return array<array<string>>
     */
    public static function targetTypes(): array
    {
        $return_arr = [];
        foreach (TargetType::cases() as $type) {
            $return_arr[] = [$type->name];
        }

        return $return_arr;
    }

    #[DataProvider('targetTypes')]
    public function testFilterByType(string $type): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        // Create one target of each type
        $targets = [];
        foreach (TargetType::cases() as $target_type) {
            $targets[] = [
                'name' => Str::uuid()->toString(),
                'type' => $target_type,
            ];
        }
        $build->targets()->createMany($targets);

        $this->graphQL('
            query build($id: ID, $type: TargetType) {
                build(id: $id) {
                    targets(
                        filters: {
                            eq: {
                                type: $type
                            }
                        }
                    ){
                        edges {
                            node {
                                type
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $build->id,
            'type' => $type,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'targets' => [
                        'edges' => [
                            [
                                'node' => [
                                    'type' => $type,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testBuildCommandRelationship(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Target $target */
        $target = $build->targets()->create([
            'name' => 'name1',
            'type' => TargetType::UNKNOWN,
        ]);

        /** @var BuildCommand $command */
        $command = $build->commands()->create([
            'type' => BuildCommandType::CUSTOM,
            'starttime' => Carbon::now(),
            'duration' => 0,
            'command' => '',
            'result' => '',
            'workingdirectory' => Str::uuid()->toString(),
        ]);

        $target->commands()->save($command);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    targets {
                        edges {
                            node {
                                commands {
                                    edges {
                                        node {
                                            id
                                            type
                                        }
                                    }
                                }
                            }
                        }
                    }
                    commands {
                        edges {
                            node {
                                id
                                type
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
                    'targets' => [
                        'edges' => [
                            [
                                'node' => [
                                    'commands' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'id' => (string) $command->id,
                                                    'type' => 'CUSTOM',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'commands' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $command->id,
                                    'type' => 'CUSTOM',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
