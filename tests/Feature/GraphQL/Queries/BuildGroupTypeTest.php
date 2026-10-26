<?php

namespace Tests\Feature\GraphQL\Queries;

use App\Enums\BuildGroupType;
use App\Models\Build;
use App\Models\BuildGroup;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class BuildGroupTypeTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        // Remove the default build groups so each test controls exactly which groups exist.
        $this->project->buildgroups()->delete();
    }

    /**
     * @return array<array{
     *     string, mixed, string, mixed,
     * }>
     */
    public static function fieldValues(): array
    {
        return [
            ['name', 'Nightly', 'name', 'Nightly'],
            ['description', 'Nightly builds', 'description', 'Nightly builds'],
            ['description', null, 'description', null],
            ['starttime', Carbon::create(2024, 3, 21, 20, 30, 51), 'startTime', '2024-03-21T20:30:51+00:00'],
            ['endtime', Carbon::create(2025, 1, 2, 3, 4, 5), 'endTime', '2025-01-02T03:04:05+00:00'],
            ['endtime', null, 'endTime', null],
        ];
    }

    /**
     * A basic test to ensure that each of the non-relationship fields works
     */
    #[DataProvider('fieldValues')]
    public function testBasicFieldAccess(string $modelField, mixed $modelValue, string $graphqlField, mixed $graphqlValue): void
    {
        $buildGroup = BuildGroup::factory()->for($this->project)->create([
            $modelField => $modelValue,
        ]);

        $this->graphQL("
            query project(\$id: ID) {
                project(id: \$id) {
                    buildGroups {
                        edges {
                            node {
                                id
                                $graphqlField
                            }
                        }
                    }
                }
            }
        ", [
            'id' => $this->project->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'buildGroups' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $buildGroup->id,
                                    $graphqlField => $graphqlValue,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testOnlyDailyBuildGroupsReturned(): void
    {
        $daily1 = BuildGroup::factory()->for($this->project)->create([
            'type' => BuildGroupType::DAILY,
        ]);
        BuildGroup::factory()->for($this->project)->create([
            'type' => BuildGroupType::LATEST,
        ]);
        $daily2 = BuildGroup::factory()->for($this->project)->create([
            'type' => BuildGroupType::DAILY,
        ]);

        // Build groups for other projects shouldn't be returned either.
        BuildGroup::factory()->for($this->makePublicProject())->create();

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    buildGroups {
                        edges {
                            node {
                                name
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
                    'buildGroups' => [
                        'edges' => [
                            ['node' => ['name' => $daily1->name]],
                            ['node' => ['name' => $daily2->name]],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testBuildGroupFiltering(): void
    {
        BuildGroup::factory()->for($this->project)->create();
        $buildGroup = BuildGroup::factory()->for($this->project)->create();

        $this->graphQL('
            query project($id: ID, $name: String) {
                project(id: $id) {
                    buildGroups(filters: {
                        eq: {
                            name: $name
                        }
                    }) {
                        edges {
                            node {
                                id
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $this->project->id,
            'name' => $buildGroup->name,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'buildGroups' => [
                        'edges' => [
                            ['node' => ['id' => (string) $buildGroup->id]],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testBuildsRelationship(): void
    {
        $buildGroup1 = BuildGroup::factory()->for($this->project)->create();
        $buildGroup2 = BuildGroup::factory()->for($this->project)->create();

        $buildGroup1->builds()->attach($this->createBuild('build1'));
        $buildGroup1->builds()->attach($this->createBuild('build2'));
        $buildGroup2->builds()->attach($this->createBuild('build3'));

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    buildGroups {
                        edges {
                            node {
                                name
                                builds {
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
                    'buildGroups' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => $buildGroup1->name,
                                    'builds' => [
                                        'edges' => [
                                            ['node' => ['name' => 'build1']],
                                            ['node' => ['name' => 'build2']],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'node' => [
                                    'name' => $buildGroup2->name,
                                    'builds' => [
                                        'edges' => [
                                            ['node' => ['name' => 'build3']],
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

    public function testBuildsOnlyParentsArgument(): void
    {
        $buildGroup = BuildGroup::factory()->for($this->project)->create();

        $parent = $this->createBuild('parent');
        $child = $this->createBuild('child', $parent);
        $buildGroup->builds()->attach([$parent->id, $child->id]);

        $query = '
            query project($id: ID, $onlyParents: Boolean) {
                project(id: $id) {
                    buildGroups {
                        edges {
                            node {
                                builds(onlyParents: $onlyParents) {
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
        ';

        $expectedJson = fn (array $buildNames): array => [
            'data' => [
                'project' => [
                    'buildGroups' => [
                        'edges' => [
                            [
                                'node' => [
                                    'builds' => [
                                        'edges' => array_map(fn (string $name): array => ['node' => ['name' => $name]], $buildNames),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Default behavior: only parent builds should be returned
        $this->graphQL($query, [
            'id' => $this->project->id,
        ])->assertExactJson($expectedJson(['parent']));

        $this->graphQL($query, [
            'id' => $this->project->id,
            'onlyParents' => true,
        ])->assertExactJson($expectedJson(['parent']));

        $this->graphQL($query, [
            'id' => $this->project->id,
            'onlyParents' => false,
        ])->assertExactJson($expectedJson(['parent', 'child']));
    }

    /**
     * @param array<string,mixed> $arguments
     *
     * @return TestResponse<Response>
     */
    private function queryBuildCount(array $arguments = []): TestResponse
    {
        return $this->graphQL('
            query(
                $id: ID
                $filters: BuildGroupBuildCountFiltersMultiFilterInput
                $onlyParents: Boolean
            ) {
                project(id: $id) {
                    buildGroups {
                        edges {
                            node {
                                name
                                buildCount(
                                    filters: $filters
                                    onlyParents: $onlyParents
                                )
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $this->project->id,
            ...$arguments,
        ]);
    }

    /**
     * @param array<string,int> $counts Build counts keyed by build group name
     *
     * @return array<string,mixed>
     */
    private static function buildCountJson(array $counts): array
    {
        $edges = [];
        foreach ($counts as $name => $count) {
            $edges[] = ['node' => ['name' => $name, 'buildCount' => $count]];
        }

        return [
            'data' => [
                'project' => [
                    'buildGroups' => [
                        'edges' => $edges,
                    ],
                ],
            ],
        ];
    }

    public function testBuildCountOnlyCountsBuildsInBuildGroup(): void
    {
        $buildGroup1 = BuildGroup::factory()->for($this->project)->create();
        $buildGroup2 = BuildGroup::factory()->for($this->project)->create();
        $emptyBuildGroup = BuildGroup::factory()->for($this->project)->create();

        $buildGroup1->builds()->attach([
            $this->createBuild('build1')->id,
            $this->createBuild('build2')->id,
        ]);
        $buildGroup2->builds()->attach($this->createBuild('build3'));

        // Builds which are not in a build group are not counted.
        $this->createBuild('build4');

        $this->queryBuildCount()->assertExactJson(self::buildCountJson([
            $buildGroup1->name => 2,
            $buildGroup2->name => 1,
            $emptyBuildGroup->name => 0,
        ]));
    }

    public function testBuildCountOnlyParentsArgument(): void
    {
        $buildGroup = BuildGroup::factory()->for($this->project)->create();

        $parent = $this->createBuild('parent');
        $child = $this->createBuild('child', $parent);
        $buildGroup->builds()->attach([$parent->id, $child->id]);

        // Default behavior: only parent builds counted
        $this->queryBuildCount()->assertExactJson(self::buildCountJson([
            $buildGroup->name => 1,
        ]));

        $this->queryBuildCount([
            'onlyParents' => true,
        ])->assertExactJson(self::buildCountJson([
            $buildGroup->name => 1,
        ]));

        $this->queryBuildCount([
            'onlyParents' => false,
        ])->assertExactJson(self::buildCountJson([
            $buildGroup->name => 2,
        ]));
    }

    public function testBuildCountWithFilters(): void
    {
        $buildGroup1 = BuildGroup::factory()->for($this->project)->create();
        $buildGroup2 = BuildGroup::factory()->for($this->project)->create();

        $buildGroup1->builds()->attach([
            $this->createBuild('a')->id,
            $this->createBuild('b')->id,
            $this->createBuild('a')->id,
        ]);
        $buildGroup2->builds()->attach($this->createBuild('b'));

        $this->queryBuildCount([
            'filters' => [
                'eq' => [
                    'name' => 'a',
                ],
            ],
        ])->assertExactJson(self::buildCountJson([
            $buildGroup1->name => 2,
            $buildGroup2->name => 0,
        ]));
    }

    /**
     * @param array<string,mixed> $arguments
     *
     * @return TestResponse<Response>
     */
    private function queryBuildCountHistogram(array $arguments): TestResponse
    {
        return $this->graphQL('
            query(
                $id: ID
                $filters: BuildGroupBuildCountHistogramFiltersMultiFilterInput
                $onlyParents: Boolean
                $interval: TimeInterval!
                $by: BuildTimeField
                $timezone: String
            ) {
                project(id: $id) {
                    buildGroups {
                        edges {
                            node {
                                name
                                buildCountHistogram(
                                    filters: $filters
                                    onlyParents: $onlyParents
                                    interval: $interval
                                    by: $by
                                    timezone: $timezone
                                ) {
                                    start
                                    count
                                }
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $this->project->id,
            ...$arguments,
        ]);
    }

    /**
     * @param array<string,array<array{start: string, count: int}>> $histograms Histograms keyed by build group name
     *
     * @return array<string,mixed>
     */
    private static function buildCountHistogramJson(array $histograms): array
    {
        $edges = [];
        foreach ($histograms as $name => $histogram) {
            $edges[] = ['node' => ['name' => $name, 'buildCountHistogram' => $histogram]];
        }

        return [
            'data' => [
                'project' => [
                    'buildGroups' => [
                        'edges' => $edges,
                    ],
                ],
            ],
        ];
    }

    public function testBuildCountHistogramOnlyCountsBuildsInBuildGroup(): void
    {
        $buildGroup1 = BuildGroup::factory()->for($this->project)->create();
        $buildGroup2 = BuildGroup::factory()->for($this->project)->create();
        $emptyBuildGroup = BuildGroup::factory()->for($this->project)->create();

        $buildGroup1->builds()->attach([
            $this->createBuild('build1', null, ['starttime' => '2026-01-01 00:00:00'])->id,
            $this->createBuild('build2', null, ['starttime' => '2026-01-01 23:59:59'])->id,
            $this->createBuild('build3', null, ['starttime' => '2026-01-03 12:00:00'])->id,
        ]);
        $buildGroup2->builds()->attach($this->createBuild('build4', null, ['starttime' => '2026-01-02 12:00:00']));

        // Builds which are not in a build group are not counted.
        $this->createBuild('build5', null, ['starttime' => '2026-01-02 12:00:00']);

        $this->queryBuildCountHistogram(['interval' => 'DAY'])->assertExactJson(self::buildCountHistogramJson([
            $buildGroup1->name => [
                ['start' => '2026-01-01T00:00:00+00:00', 'count' => 2],
                ['start' => '2026-01-03T00:00:00+00:00', 'count' => 1],
            ],
            $buildGroup2->name => [
                ['start' => '2026-01-02T00:00:00+00:00', 'count' => 1],
            ],
            $emptyBuildGroup->name => [],
        ]));
    }

    public function testBuildCountHistogramOnlyParentsArgument(): void
    {
        $buildGroup = BuildGroup::factory()->for($this->project)->create();

        $parent = $this->createBuild('parent', null, ['starttime' => '2026-01-01 10:00:00']);
        $child = $this->createBuild('child', $parent, ['starttime' => '2026-01-01 11:00:00']);
        $buildGroup->builds()->attach([$parent->id, $child->id]);

        $this->queryBuildCountHistogram(['interval' => 'DAY'])->assertExactJson(self::buildCountHistogramJson([
            $buildGroup->name => [
                ['start' => '2026-01-01T00:00:00+00:00', 'count' => 1],
            ],
        ]));

        $this->queryBuildCountHistogram([
            'interval' => 'DAY',
            'onlyParents' => false,
        ])->assertExactJson(self::buildCountHistogramJson([
            $buildGroup->name => [
                ['start' => '2026-01-01T00:00:00+00:00', 'count' => 2],
            ],
        ]));
    }

    public function testBuildCountHistogramWithFilters(): void
    {
        $buildGroup = BuildGroup::factory()->for($this->project)->create();
        $buildGroup->builds()->attach([
            $this->createBuild('a', null, ['starttime' => '2026-01-01 12:00:00'])->id,
            $this->createBuild('b', null, ['starttime' => '2026-01-01 12:00:00'])->id,
            $this->createBuild('a', null, ['starttime' => '2026-01-02 12:00:00'])->id,
        ]);

        $this->queryBuildCountHistogram([
            'interval' => 'DAY',
            'filters' => [
                'eq' => [
                    'name' => 'a',
                ],
            ],
        ])->assertExactJson(self::buildCountHistogramJson([
            $buildGroup->name => [
                ['start' => '2026-01-01T00:00:00+00:00', 'count' => 1],
                ['start' => '2026-01-02T00:00:00+00:00', 'count' => 1],
            ],
        ]));

        // The time window is selected with filters.
        $this->queryBuildCountHistogram([
            'interval' => 'DAY',
            'filters' => [
                'all' => [
                    ['eq' => ['name' => 'a']],
                    ['ge' => ['startTime' => '2026-01-02T00:00:00+00:00']],
                ],
            ],
        ])->assertExactJson(self::buildCountHistogramJson([
            $buildGroup->name => [
                ['start' => '2026-01-02T00:00:00+00:00', 'count' => 1],
            ],
        ]));
    }

    public function testBuildCountHistogramByTimeFieldAndTimezone(): void
    {
        $buildGroup = BuildGroup::factory()->for($this->project)->create();

        // The submission time is 2026-03-09 22:45 in New York.
        $buildGroup->builds()->attach($this->createBuild('build', null, [
            'starttime' => '2026-01-01 12:00:00',
            'submittime' => '2026-03-10 02:45:00',
        ]));

        $this->queryBuildCountHistogram([
            'interval' => 'DAY',
            'by' => 'SUBMISSION_TIME',
            'timezone' => 'America/New_York',
        ])->assertExactJson(self::buildCountHistogramJson([
            $buildGroup->name => [
                ['start' => '2026-03-09T00:00:00-04:00', 'count' => 1],
            ],
        ]));

        $this->queryBuildCountHistogram([
            'interval' => 'DAY',
            'timezone' => 'Not/A_Timezone',
        ])->assertGraphQLValidationKeys(['timezone']);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private function createBuild(string $name, ?Build $parent = null, array $attributes = []): Build
    {
        return $this->project->builds()->create([
            'name' => $name,
            'parentid' => $parent?->id,
            'uuid' => Str::uuid(),
            ...$attributes,
        ]);
    }
}
