<?php

namespace Tests\Feature\Submission\Tests;

use App\Models\Project;
use Illuminate\Testing\Fluent\AssertableJson;
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
                'tests/Feature/Submission/Tests/data/with_branchCoverage.xml'
            ),
            base_path(
                'tests/Feature/Submission/Tests/data/with_LogBranchCoverage.xml'
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
        ])->assertJson(function (AssertableJson $json): void {
            // This is to check that branch data exists overall
            $json->has('data.build.coverage.edges.0.node', function (AssertableJson $json): void {
                $json->where('branchesTested', 3)
                ->where('branchesUntested', 1)
                ->where('branchPercentage', 75)
                ->etc();
            });
            // This is to check that one numbered line has branch data
            $json->has('data.build.coverage.edges.0.node.coveredLines.19', function (AssertableJson $json): void {
                $json->where('branchesHit', 1)
                ->where('totalBranches', 2);
            });
        });
    }
}
