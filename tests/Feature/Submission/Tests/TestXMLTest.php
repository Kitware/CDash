<?php

namespace Tests\Feature\Submission\Instrumentation;

use App\Models\Project;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class TestXMLTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        // The trait doesn't initialize the default buildgroups for us, so we do it manually
        $legacy_project = new \CDash\Model\Project();
        $legacy_project->Id = $this->project->id;
        $legacy_project->InitialSetup();

        $this->project->refresh();
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * Test parsing a valid Test.xml file that contains angle brackets
     * in the test name.
     */
    public function testAngleBracketsInName(): void
    {
        $this->submitFiles($this->project->name, [
            base_path(
                'tests/Feature/Submission/Tests/data/angle_brackets_in_test_name.xml'
            ),
        ]);

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                tests {
                  edges {
                    node {
                      name
                    }
                  }
                }
              }
            }
        ', [
            'id' => $this->project->builds()->firstOrFail()->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'tests' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'MyTest<parameterized>',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test parsing a valid Test.xml file that contains non-UTF-8 characters
     * in the test output.
     */
    public function testNonUTF8Output(): void
    {
        $this->submitFiles($this->project->name, [
            base_path(
                'tests/Feature/Submission/Tests/data/non_utf8_output.xml'
            ),
        ]);

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                tests {
                  edges {
                    node {
                      name
                    }
                  }
                }
              }
            }
        ', [
            'id' => $this->project->builds()->firstOrFail()->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'tests' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'NonUtf8Output',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
