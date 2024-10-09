<?php

namespace Tests\Feature\GraphQL;

use App\Models\Project;
use App\Models\TestOutput;
use Illuminate\Support\Str;
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
     * @throws \Random\RandomException
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
        ])->assertJson([
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
        ], true);
    }

    /**
     * @return array<array<string>>
     */
    public function statuses(): array
    {
        return [
            ['passed', 'PASSED'],
            ['failed', 'FAILED'],
            ['Timeout', 'TIMEOUT'],
            ['notrun', 'NOT_RUN'],
            ['Disabled', 'DISABLED'],
        ];
    }

    /**
     * @dataProvider statuses
     */
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
        ])->assertJson([
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
        ], true);
    }
}
