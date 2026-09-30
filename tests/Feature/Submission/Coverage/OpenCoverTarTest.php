<?php

namespace Tests\Feature\Submission\Coverage;

use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class OpenCoverTarTest extends TestCase
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
     * Test parsing a tarball containing an OpenCover .xml report and the .cs source files it covers
     */
    public function testOpenCoverCoverage(): void
    {
        $buildid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Coverage/data/OpenCoverTar_example.tar'
        ), 'OpenCoverTar');

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                coverage(orderBy: [{column: FILE_PATH, order: ASC}]) {
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
                    'coverage' => [
                        'edges' => [
                            [
                                'node' => [
                                    'filePath' => 'ConsoleApplication1/Program.cs',
                                    'linesOfCodeTested' => 9,
                                    'linesOfCodeUntested' => 5,
                                ],
                            ],
                            [
                                'node' => [
                                    'filePath' => 'ConsoleApplication1/Properties/AssemblyInfo.cs',
                                    'linesOfCodeTested' => 0,
                                    'linesOfCodeUntested' => 0,
                                ],
                            ],
                            [
                                'node' => [
                                    'filePath' => 'ConsoleApplication1/Unused.cs',
                                    'linesOfCodeTested' => 0,
                                    'linesOfCodeUntested' => 2,
                                ],
                            ],
                            [
                                'node' => [
                                    'filePath' => 'UnitTestProject1Test/Properties/AssemblyInfo.cs',
                                    'linesOfCodeTested' => 0,
                                    'linesOfCodeUntested' => 0,
                                ],
                            ],
                            [
                                'node' => [
                                    'filePath' => 'UnitTestProject1Test/UnitTest1.cs',
                                    'linesOfCodeTested' => 0,
                                    'linesOfCodeUntested' => 3,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test that setting "parseCSFiles" to false in a data.json file causes only the lines listed in
     * the OpenCover .xml report to be counted
     */
    public function testOpenCoverCoverageWithDataJson(): void
    {
        $buildid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Coverage/data/OpenCoverTar_with_data_json.tar'
        ), 'OpenCoverTar');

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                coverage(orderBy: [{column: FILE_PATH, order: ASC}]) {
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
                    'coverage' => [
                        'edges' => [
                            [
                                'node' => [
                                    'filePath' => 'ConsoleApplication1/Program.cs',
                                    'linesOfCodeTested' => 9,
                                    'linesOfCodeUntested' => 4,
                                ],
                            ],
                            [
                                'node' => [
                                    'filePath' => 'ConsoleApplication1/Properties/AssemblyInfo.cs',
                                    'linesOfCodeTested' => 0,
                                    'linesOfCodeUntested' => 0,
                                ],
                            ],
                            [
                                'node' => [
                                    'filePath' => 'ConsoleApplication1/Unused.cs',
                                    'linesOfCodeTested' => 0,
                                    'linesOfCodeUntested' => 0,
                                ],
                            ],
                            [
                                'node' => [
                                    'filePath' => 'UnitTestProject1Test/Properties/AssemblyInfo.cs',
                                    'linesOfCodeTested' => 0,
                                    'linesOfCodeUntested' => 0,
                                ],
                            ],
                            [
                                'node' => [
                                    'filePath' => 'UnitTestProject1Test/UnitTest1.cs',
                                    'linesOfCodeTested' => 0,
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
