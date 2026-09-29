<?php

namespace Tests\Feature\Submission\Coverage;

use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class JavaJSONTarTest extends TestCase
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
     * Test parsing a tarball of .java.json coverage files
     */
    public function testJavaJSONCoverage(): void
    {
        $buildid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Coverage/data/JavaJSONTar_example.tar'
        ), 'JavaJSONTar');

        // Coverage files are stored in an arbitrary order, so query each file separately.
        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                teuchos: coverage(filters: { eq: { filePath: "TeuchosMain.java" } }) {
                  edges {
                    node {
                      filePath
                      linesOfCodeTested
                      linesOfCodeUntested
                    }
                  }
                }
                trios: coverage(filters: { eq: { filePath: "TriosMain.java" } }) {
                  edges {
                    node {
                      filePath
                      linesOfCodeTested
                      linesOfCodeUntested
                    }
                  }
                }
              }
            }
        ', [
            'id' => $buildid,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'teuchos' => [
                        'edges' => [
                            [
                                'node' => [
                                    'filePath' => 'TeuchosMain.java',
                                    'linesOfCodeTested' => 8,
                                    'linesOfCodeUntested' => 3,
                                ],
                            ],
                        ],
                    ],
                    'trios' => [
                        'edges' => [
                            [
                                'node' => [
                                    'filePath' => 'TriosMain.java',
                                    'linesOfCodeTested' => 11,
                                    'linesOfCodeUntested' => 0,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
