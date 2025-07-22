<?php

namespace Tests\Feature\GraphQL;

use App\Models\Project;
use App\Models\TestOutput;
use Illuminate\Support\Str;
use Random\RandomException;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class TestMeasurementTypeTest extends TestCase
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
            'outputid' => $this->test_output->id,
        ])->testMeasurements()->createMany([
            [
                'name' => 'measurement 1',
                'type' => 'text/string',
                'value' => 'test',
            ],
            [
                'name' => 'measurement 2',
                'type' => 'numeric/double',
                'value' => '6',
            ],
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
                                            testMeasurements {
                                                edges {
                                                    node {
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
                                                    'testMeasurements' => [
                                                        'edges' => [
                                                            [
                                                                'node' => [
                                                                    'name' => 'measurement 2',
                                                                    'type' => 'numeric/double',
                                                                    'value' => '6',
                                                                ],
                                                            ],
                                                            [
                                                                'node' => [
                                                                    'name' => 'measurement 1',
                                                                    'type' => 'text/string',
                                                                    'value' => 'test',
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
                        ],
                    ],
                ],
            ],
        ]);
    }
}
