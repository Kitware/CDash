<?php

namespace Tests\Feature\GraphQL;

use App\Models\CoverageDiff;
use App\Models\Project;
use App\Models\Site;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;

class CoverageDiffTypeTest extends TestCase
{
    use CreatesProjects;
    use CreatesSites;
    use DatabaseTransactions;

    private Project $project;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = $this->makePublicProject();
        $this->site = $this->makeSite();
    }

    public function testQueryCoverageDiffFields(): void
    {
        $baseBuild = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);

        $compareBuild = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);

        $coverageDiff = CoverageDiff::create([
            'basebuildid' => $baseBuild->id,
            'comparebuildid' => $compareBuild->id,
            'coveredlinesadded' => 10,
            'coveredlinesremoved' => 5,
            'coveredlinesuncovered' => 2,
            'uncoveredlinesadded' => 8,
            'uncoveredlinesremoved' => 3,
            'uncoveredlinescovered' => 4,
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    coverageDiffs {
                        edges {
                            node {
                                id
                                baseBuild {
                                    name
                                }
                                compareBuild {
                                    name
                                }
                                coveredLinesAdded
                                coveredLinesRemoved
                                coveredLinesUncovered
                                uncoveredLinesAdded
                                uncoveredLinesRemoved
                                uncoveredLinesCovered
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $compareBuild->id,
        ])
            ->assertGraphQLErrorFree()
            ->assertExactJson([
                'data' => [
                    'build' => [
                        'coverageDiffs' => [
                            'edges' => [
                                [
                                    'node' => [
                                        'id' => (string) $coverageDiff->id,
                                        'baseBuild' => [
                                            'name' => $baseBuild->name,
                                        ],
                                        'compareBuild' => [
                                            'name' => $compareBuild->name,
                                        ],
                                        'coveredLinesAdded' => 10,
                                        'coveredLinesRemoved' => 5,
                                        'coveredLinesUncovered' => 2,
                                        'uncoveredLinesAdded' => 8,
                                        'uncoveredLinesRemoved' => 3,
                                        'uncoveredLinesCovered' => 4,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function testQueryMultipleCoverageDiffs(): void
    {
        $compareBuild = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);

        $baseBuild1 = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);

        $baseBuild2 = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
        ]);

        CoverageDiff::create([
            'basebuildid' => $baseBuild1->id,
            'comparebuildid' => $compareBuild->id,
            'coveredlinesadded' => 1,
            'coveredlinesremoved' => 0,
            'coveredlinesuncovered' => 0,
            'uncoveredlinesadded' => 0,
            'uncoveredlinesremoved' => 0,
            'uncoveredlinescovered' => 0,
        ]);

        CoverageDiff::create([
            'basebuildid' => $baseBuild2->id,
            'comparebuildid' => $compareBuild->id,
            'coveredlinesadded' => 2,
            'coveredlinesremoved' => 0,
            'coveredlinesuncovered' => 0,
            'uncoveredlinesadded' => 0,
            'uncoveredlinesremoved' => 0,
            'uncoveredlinescovered' => 0,
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    coverageDiffs {
                        edges {
                            node {
                                baseBuild {
                                    name
                                }
                                coveredLinesAdded
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $compareBuild->id,
        ])
            ->assertGraphQLErrorFree()
            ->assertExactJson([
                'data' => [
                    'build' => [
                        'coverageDiffs' => [
                            'edges' => [
                                [
                                    'node' => [
                                        'baseBuild' => [
                                            'name' => $baseBuild2->name,
                                        ],
                                        'coveredLinesAdded' => 2,
                                    ],
                                ],
                                [
                                    'node' => [
                                        'baseBuild' => [
                                            'name' => $baseBuild1->name,
                                        ],
                                        'coveredLinesAdded' => 1,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }
}
