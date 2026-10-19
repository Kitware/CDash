<?php

namespace Tests\Feature\GraphQL\Queries;

use App\Enums\BuildGroupType;
use App\Models\Build;
use App\Models\BuildGroup;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
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

    private function createBuild(string $name, ?Build $parent = null): Build
    {
        return $this->project->builds()->create([
            'name' => $name,
            'parentid' => $parent?->id,
            'uuid' => Str::uuid(),
        ]);
    }
}
