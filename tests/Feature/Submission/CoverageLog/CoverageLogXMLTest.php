<?php

namespace Tests\Feature\Submission\Tests;

use App\Models\Project;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class CoverageLogXMLTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;

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
     * Test parsing a valid CoverageLog.xml file that contains branch
     * coverage information in the attributes of each line
     */
    public function testBranchCoverage(): void
    {
        $this->submitFiles($this->project->name, [
            base_path(
                'tests/Feature/Submission/CoverageLog/data/with_branchCoverage.xml'
            ),
            base_path(
                'tests/Feature/Submission/CoverageLog/data/with_LogBranchCoverage.xml'
            ),
        ]);

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                coverage {
                  edges {
                    node {
                      branchPercentage
                      branchesTested
                      branchesUntested
                      coveredLines {
                        lineNumber
                        branchesHit
                        totalBranches
                      }
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
                    'coverage' => [
                        'edges' => [
                            [
                                'node' => [
                                    'branchPercentage' => 75,
                                    'branchesTested' => 3,
                                    'branchesUntested' => 1,
                                    'coveredLines' => [
                                        [
                                            'lineNumber' => 3,
                                            'branchesHit' => 2,
                                            'totalBranches' => 2,
                                        ],
                                        [
                                            'lineNumber' => 18,
                                            'branchesHit' => 1,
                                            'totalBranches' => 2,
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
