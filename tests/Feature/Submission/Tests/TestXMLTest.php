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
     * Test parsing valid Test.xml file(s).
     */
    public function testValidXML(): void
    {
        $this->submitFiles($this->project->name, [
            base_path(
                'tests/Feature/Submission/Tests/data/angle_brackets_in_test_name.xml'
            ),
        ]);

        $this->graphQL('
            query project($id: ID) {
              project(id: $id) {
                builds {
                  edges {
                    node {
                      tests {
                        edges {
                          node {
                            name
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
                        ],
                    ],
                ],
            ],
        ]);
    }
}
