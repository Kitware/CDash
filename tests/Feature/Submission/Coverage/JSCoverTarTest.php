<?php

namespace Tests\Feature\Submission\Coverage;

use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class JSCoverTarTest extends TestCase
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
     * Test parsing a tarball of JSCover .json coverage files, some of which report coverage for
     * the same source file.
     */
    public function testJSCoverCoverage(): void
    {
        $buildid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Coverage/data/JSCoverTar_example.tar'
        ), 'JSCoverTar');

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
                                    'filePath' => 'bff_content.js',
                                    'linesOfCodeTested' => 16,
                                    'linesOfCodeUntested' => 18,
                                ],
                            ],
                            [
                                'node' => [
                                    'filePath' => 'index_content.js',
                                    'linesOfCodeTested' => 151,
                                    'linesOfCodeUntested' => 24,
                                ],
                            ],
                            [
                                'node' => [
                                    'filePath' => 'menus_content.js',
                                    'linesOfCodeTested' => 59,
                                    'linesOfCodeUntested' => 9,
                                ],
                            ],
                            [
                                'node' => [
                                    'filePath' => 'vista_pkg_dep_content.js',
                                    'linesOfCodeTested' => 173,
                                    'linesOfCodeUntested' => 15,
                                ],
                            ],
                            [
                                'node' => [
                                    'filePath' => 'vivian_tree_layout_common.js',
                                    'linesOfCodeTested' => 27,
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
     * Test that line hit counts are summed when a source file is covered by multiple .json files.
     */
    public function testJSCoverCoverageAcrossMultipleFiles(): void
    {
        $buildid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Coverage/data/JSCoverTar_example.tar'
        ), 'JSCoverTar');

        // vivian_tree_layout_common.js appears in four of the five .json files in the tarball.
        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                coverage(filters: { eq: { filePath: "vivian_tree_layout_common.js" } }) {
                  edges {
                    node {
                      coveredLines {
                        lineNumber
                        timesHit
                      }
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
                                    'coveredLines' => [
                                        ['lineNumber' => 0, 'timesHit' => 4],
                                        ['lineNumber' => 1, 'timesHit' => 0],
                                        ['lineNumber' => 2, 'timesHit' => 0],
                                        ['lineNumber' => 3, 'timesHit' => 0],
                                        ['lineNumber' => 7, 'timesHit' => 4],
                                        ['lineNumber' => 9, 'timesHit' => 2],
                                        ['lineNumber' => 10, 'timesHit' => 2],
                                        ['lineNumber' => 13, 'timesHit' => 4],
                                        ['lineNumber' => 14, 'timesHit' => 3],
                                        ['lineNumber' => 15, 'timesHit' => 3],
                                        ['lineNumber' => 18, 'timesHit' => 4],
                                        ['lineNumber' => 19, 'timesHit' => 707],
                                        ['lineNumber' => 20, 'timesHit' => 707],
                                        ['lineNumber' => 21, 'timesHit' => 115],
                                        ['lineNumber' => 25, 'timesHit' => 4],
                                        ['lineNumber' => 26, 'timesHit' => 7],
                                        ['lineNumber' => 27, 'timesHit' => 7],
                                        ['lineNumber' => 29, 'timesHit' => 7],
                                        ['lineNumber' => 31, 'timesHit' => 7],
                                        ['lineNumber' => 45, 'timesHit' => 4],
                                        ['lineNumber' => 46, 'timesHit' => 14835],
                                        ['lineNumber' => 47, 'timesHit' => 2123],
                                        ['lineNumber' => 48, 'timesHit' => 2123],
                                        ['lineNumber' => 53, 'timesHit' => 4],
                                        ['lineNumber' => 54, 'timesHit' => 2126],
                                        ['lineNumber' => 55, 'timesHit' => 2126],
                                        ['lineNumber' => 56, 'timesHit' => 2126],
                                        ['lineNumber' => 61, 'timesHit' => 4],
                                        ['lineNumber' => 62, 'timesHit' => 716],
                                        ['lineNumber' => 63, 'timesHit' => 104],
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
