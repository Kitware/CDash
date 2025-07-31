<?php

namespace Tests\Feature\GraphQL;

use App\Models\Project;
use App\Models\TestOutput;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Random\RandomException;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class TestTypeTest extends TestCase
{
    use CreatesUsers;
    use CreatesProjects;

    private Project $project;
    private TestOutput $test_output;

    /**
     * @throws RandomException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        // A common test output to share among all of our tests
        $this->test_output = TestOutput::create([
            'crc32' => random_int(0, 100000),
            'path' => 'a',
            'command' => 'b',
            'output' => 'c',
        ]);
    }

    protected function tearDown(): void
    {
        // Deleting the project will delete all corresponding builds and tests
        $this->project->delete();

        $this->test_output->delete();

        parent::tearDown();
    }

    /**
     * A basic test to ensure that each of the fields works
     */
    public function testBasicFieldAccess(): void
    {
        $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ])->tests()->create([
            'testname' => 'test1',
            'status' => 'failed',
            'details' => 'details text',
            'time' => 1.2,
            'outputid' => $this->test_output->id,
        ]);

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    builds {
                        edges {
                            node {
                                name
                                tests {
                                    edges {
                                        node {
                                            name
                                            status
                                            details
                                            runningTime
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $this->project->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'builds' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'build1',
                                    'tests' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'name' => 'test1',
                                                    'status' => 'FAILED',
                                                    'details' => 'details text',
                                                    'runningTime' => 1.2,
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

    /**
     * @return array<array<string>>
     */
    public static function statuses(): array
    {
        return [
            ['passed', 'PASSED'],
            ['failed', 'FAILED'],
            ['notrun', 'NOT_RUN'],
        ];
    }

    #[DataProvider('statuses')]
    public function testStatusEnum(string $db_value, string $enum_value): void
    {
        $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ])->tests()->create([
            'testname' => 'test1',
            'status' => $db_value,
            'outputid' => $this->test_output->id,
        ]);

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    builds {
                        edges {
                            node {
                                name
                                tests {
                                    edges {
                                        node {
                                            name
                                            status
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $this->project->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'builds' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'build1',
                                    'tests' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'name' => 'test1',
                                                    'status' => $enum_value,
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

    public function testLabelRelationship(): void
    {
        $build = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        $label = $build->tests()->create([
            'testname' => Str::uuid()->toString(),
            'outputid' => $this->test_output->id,
            'status' => 'passed',
        ])->labels()->create([
            'text' => Str::uuid()->toString(),
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    tests {
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
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'tests' => [
                        'edges' => [
                            [
                                'node' => [
                                    'labels' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'id' => (string) $label->id,
                                                    'text' => $label->text,
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

        $label->delete();
    }

    public function testLabelFilters(): void
    {
        $build = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        $test = $build->tests()->create([
            'testname' => Str::uuid()->toString(),
            'outputid' => $this->test_output->id,
            'status' => 'passed',
        ]);

        $label1 = $test->labels()->create([
            'text' => Str::uuid()->toString(),
        ]);

        $label2 = $test->labels()->create([
            'text' => Str::uuid()->toString(),
        ]);

        $this->graphQL('
            query build($id: ID, $labeltext: String!) {
                build(id: $id) {
                    tests {
                        edges {
                            node {
                                labels(
                                    filters: {
                                        eq: {
                                            text: $labeltext
                                        }
                                    }
                                ) {
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
            'labeltext' => $label1->text,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'tests' => [
                        'edges' => [
                            [
                                'node' => [
                                    'labels' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'text' => $label1->text,
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

        $label1->delete();
        $label2->delete();
    }
}
