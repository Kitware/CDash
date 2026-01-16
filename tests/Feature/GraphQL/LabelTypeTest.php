<?php

namespace Tests\Feature\GraphQL;

use App\Enums\TargetType;
use App\Models\Label;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class LabelTypeTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;
    use DatabaseTransactions;

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
        ])->assertExactJson([
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
            'text' => Str::uuid()->toString(),
        ]);

        $this->labels['label2'] = $build->labels()->create([
            'text' => Str::uuid()->toString(),
        ]);

        $this->graphQL('
            query build($id: ID, $labeltext: String!) {
                build(id: $id) {
                    labels(
                        filters: {
                            eq: {
                                text: $labeltext
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
            'labeltext' => $this->labels['label1']->text,
        ])->assertExactJson([
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

    public function testTargetRelationship(): void
    {
        $build = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        $target = $build->targets()->create([
            'name' => Str::uuid()->toString(),
            'type' => TargetType::UNKNOWN,
        ]);

        $this->labels['label1'] = $target->labels()->create([
            'text' => Str::uuid()->toString(),
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    targets {
                        edges {
                            node {
                                labels {
                                    edges {
                                        node {
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
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'targets' => [
                        'edges' => [
                            [
                                'node' => [
                                    'labels' => [
                                        'edges' => [
                                            [
                                                'node' => [
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

    public function testDynamicAnalysisRelationship(): void
    {
        $build = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        $da = $build->dynamicAnalyses()->create([
            'log' => Str::uuid()->toString(),
        ]);

        $this->labels['label1'] = $da->labels()->create([
            'text' => Str::uuid()->toString(),
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    dynamicAnalyses {
                        edges {
                            node {
                                labels {
                                    edges {
                                        node {
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
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'dynamicAnalyses' => [
                        'edges' => [
                            [
                                'node' => [
                                    'labels' => [
                                        'edges' => [
                                            [
                                                'node' => [
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
