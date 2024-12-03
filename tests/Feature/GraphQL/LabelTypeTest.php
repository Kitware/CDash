<?php

namespace Tests\Feature\GraphQL;

use App\Enums\BuildCommandType;
use App\Models\Label;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class LabelTypeTest extends TestCase
{
    use CreatesUsers;
    use CreatesProjects;

    private Project $project;

    /**
     * @var array<Label>
     */
    private array $labels = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        foreach ($this->labels as $label) {
            $label->delete();
        }
        $this->labels = [];

        parent::tearDown();
    }

    public function testBuildRelationship(): void
    {
        $build = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->labels['label1'] = $build->labels()->create([
            'text' => Str::uuid()->toString(),
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    labels {
                        edges {
                            node {
                                id
                                text
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
                    'labels' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $this->labels['label1']->id,
                                    'text' => $this->labels['label1']->text,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testLabelFilters(): void
    {
        $build = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->labels['label1'] = $build->labels()->create([
            'text' => 'text1',
        ]);

        $this->labels['label2'] = $build->labels()->create([
            'text' => 'text2',
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    labels(
                        filters: {
                            eq: {
                                text: "text1"
                            }
                        }
                    ){
                        edges {
                            node {
                                id
                                text
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
                    'labels' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $this->labels['label1']->id,
                                    'text' => $this->labels['label1']->text,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return array{
     *     array{
     *         BuildCommandType
     *     }
     * }
     */
    public static function buildCommandTypes(): array
    {
        return [
            [BuildCommandType::COMPILE_COMMAND, 'compileCommands'],
            [BuildCommandType::LINK_COMMAND, 'linkCommands'],
            [BuildCommandType::CMAKE_BUILD_COMMAND, 'cmakeBuildCommands'],
            [BuildCommandType::CUSTOM_COMMAND, 'customCommands'],
        ];
    }

    /**
     * @dataProvider buildCommandTypes
     */
    public function testBuildCommandRelationships(BuildCommandType $commandType, string $graphqlFieldName): void
    {
        $build = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->labels['label1'] = $build->commands()->create([
            'type' => $commandType,
            'starttime' => Carbon::now(),
            'endtime' => Carbon::now(),
            'command' => Str::uuid()->toString(),
            'binarydirectory' => Str::uuid()->toString(),
            'returnvalue' => Str::uuid()->toString(),
        ])->labels()->create([
            'text' => Str::uuid()->toString(),
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    ' . $graphqlFieldName . ' {
                        edges {
                            node {
                                labels {
                                    edges {
                                        node {
                                            id
                                            text
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
                    $graphqlFieldName => [
                        'edges' => [
                            [
                                'node' => [
                                    'labels' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'id' => (string) $this->labels['label1']->id,
                                                    'text' => $this->labels['label1']->text,
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
