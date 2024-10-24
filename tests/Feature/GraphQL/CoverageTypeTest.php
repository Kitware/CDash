<?php

namespace Tests\Feature\GraphQL;

use App\Models\Project;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class CoverageTypeTest extends TestCase
{
    use CreatesUsers;
    use CreatesProjects;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    protected function tearDown(): void
    {
        // Deleting the project will delete all corresponding builds and coverage results
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * A basic test to ensure that each of the fields works
     */
    public function testBasicFieldAccess(): void
    {
        $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ])->coverageResults()->create([
            'loctested' => 4,
            'locuntested' => 5,
            'branchestested' => 6,
            'branchesuntested' => 7,
            'functionstested' => 8,
            'functionsuntested' => 9,
        ]);

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    builds {
                        edges {
                            node {
                                coverageResults {
                                    edges {
                                        node {
                                            linesOfCodeTested
                                            linesOfCodeUntested
                                            branchesTested
                                            branchesUntested
                                            functionsTested
                                            functionsUntested
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
                                    'coverageResults' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'linesOfCodeTested' => 4,
                                                    'linesOfCodeUntested' => 5,
                                                    'branchesTested' => 6,
                                                    'branchesUntested' => 7,
                                                    'functionsTested' => 8,
                                                    'functionsUntested' => 9,
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
