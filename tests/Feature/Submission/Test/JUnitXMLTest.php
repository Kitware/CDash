<?php

namespace Tests\Feature\Submission\Test;

use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class JUnitXMLTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;
    use DatabaseTransactions;

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

    /**
     * Test parsing a JUnit XML file containing passed, failed and not run tests.
     */
    public function testJUnitResults(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Test/data/JUnit_example.xml'
        ));

        $this->graphQL('
            query project($id: ID) {
              project(id: $id) {
                builds {
                  edges {
                    node {
                      name
                      stamp
                      buildType
                      site {
                        name
                      }
                      passedTestsCount
                      failedTestsCount
                      notRunTestsCount
                      tests {
                        edges {
                          node {
                            name
                            status
                            runningTime
                            details
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
                                    'name' => 'junit-test-build',
                                    'stamp' => '20170517-1200-my-custom-track',
                                    'buildType' => 'my-custom-track',
                                    'site' => [
                                        'name' => 'localhost',
                                    ],
                                    'passedTestsCount' => 2,
                                    'failedTestsCount' => 3,
                                    'notRunTestsCount' => 1,
                                    'tests' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'name' => 'Passes',
                                                    'status' => 'PASSED',
                                                    'runningTime' => 0.0,
                                                    'details' => '',
                                                ],
                                            ],
                                            [
                                                'node' => [
                                                    'name' => 'PassesToo',
                                                    'status' => 'PASSED',
                                                    'runningTime' => 0.1,
                                                    'details' => '',
                                                ],
                                            ],
                                            [
                                                'node' => [
                                                    'name' => 'Fails',
                                                    'status' => 'FAILED',
                                                    'runningTime' => 0.2,
                                                    'details' => '',
                                                ],
                                            ],
                                            [
                                                'node' => [
                                                    'name' => 'AlsoFails',
                                                    'status' => 'FAILED',
                                                    'runningTime' => 0.2,
                                                    'details' => 'NotEnoughFoo',
                                                ],
                                            ],
                                            [
                                                'node' => [
                                                    'name' => 'AnotherFailure',
                                                    'status' => 'FAILED',
                                                    'runningTime' => 0.0,
                                                    'details' => 'TooMuchFoo',
                                                ],
                                            ],
                                            [
                                                'node' => [
                                                    'name' => 'DoesNotRun',
                                                    'status' => 'NOT_RUN',
                                                    'runningTime' => 0.0,
                                                    'details' => '',
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
