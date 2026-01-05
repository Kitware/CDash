<?php

namespace Tests\Feature\GraphQL;

use App\Enums\TargetType;
use App\Models\Build;
use App\Models\Project;
use App\Models\Site;
use App\Models\Test;
use App\Models\TestOutput;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;
use Tests\Traits\CreatesUsers;

class FilterTest extends TestCase
{
    use CreatesProjects;
    use CreatesSites;
    use CreatesUsers;
    use DatabaseTruncation;

    /**
     * @var array<Project>
     */
    private array $projects;

    /**
     * @var array<User>
     */
    private array $users;

    /**
     * @var array<Site>
     */
    private array $sites = [];

    private TestOutput $testOutput;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projects['public1'] = $this->makePublicProject('public1');
        $this->projects['public2'] = $this->makePublicProject('public2');
        $this->projects['public4'] = $this->makePublicProject('public4'); // Out of order to check DB ordering
        $this->projects['public3'] = $this->makePublicProject('public3');
        $this->projects['public5'] = $this->makePublicProject('public5');

        // A couple private projects so we can check visibility (enum) filtering
        $this->projects['private1'] = $this->makePrivateProject('private1');
        $this->projects['private2'] = $this->makePrivateProject('private2');

        $this->users = [
            'normal' => $this->makeNormalUser(),
            'admin' => $this->makeAdminUser(),
        ];

        $this->testOutput = TestOutput::create([
            'path' => 'a',
            'command' => 'b',
            'output' => 'c',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->projects as $project) {
            $project->delete();
        }
        $this->projects = [];

        foreach ($this->users as $user) {
            $user->delete();
        }
        $this->users = [];

        foreach ($this->sites as $site) {
            $site->delete();
        }
        $this->sites = [];

        $this->testOutput->delete();

        parent::tearDown();
    }

    /**
     * @param array<string> $visible_projects
     */
    protected function constructNameFilterQuery(string $filters, array $visible_projects): void
    {
        $expected_edges = [];
        foreach ($visible_projects as $project) {
            $expected_edges[] = [
                'node' => [
                    'name' => $project,
                ],
            ];
        }

        $this->actingAs($this->users['admin'])->graphQL("
            query {
                projects(filters: {
                    {$filters}
                }) {
                    edges {
                        node {
                            name
                        }
                    }
                }
            }
        ")->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => $expected_edges,
                ],
            ],
        ]);
    }

    public function testEqualOperator(): void
    {
        $this->constructNameFilterQuery('
            eq: {
                name: "public4"
            }
        ', [
            'public4',
        ]);
    }

    public function testNotEqualOperator(): void
    {
        $this->constructNameFilterQuery('
            ne: {
                name: "public4"
            }
        ', [
            'public1',
            'public2',
            'public3',
            'public5',
            'private1',
            'private2',
        ]);
    }

    public function testGreaterThanOperator(): void
    {
        $this->constructNameFilterQuery('
            gt: {
                name: "public4"
            }
        ', [
            'public5',
        ]);
    }

    public function testLessThanOperator(): void
    {
        $this->constructNameFilterQuery('
            lt: {
                name: "public4"
            }
        ', [
            'public1',
            'public2',
            'public3',
            'private1',
            'private2',
        ]);
    }

    public function testContainsOperator(): void
    {
        $this->constructNameFilterQuery('
            contains: {
                name: "2"
            }
        ', [
            'public2',
            'private2',
        ]);

        $this->constructNameFilterQuery('
            contains: {
                name: "vate"
            }
        ', [
            'private1',
            'private2',
        ]);

        $this->constructNameFilterQuery('
            contains: {
                name: ""
            }
        ', []);
    }

    public function testMultipleContainsClauses(): void
    {
        $this->constructNameFilterQuery('
            any: [
                {
                    contains: {
                        name: "2"
                    }
                },
                {
                    contains: {
                        name: "3"
                    }
                }
            ]
        ', [
            'public2',
            'public3',
            'private2',
        ]);
    }

    public function testAllOperatorWithMultipleEqualOperators(): void
    {
        $this->constructNameFilterQuery('
            all: [
                {
                    eq: {
                        name: "public2"
                    }
                },
                {
                    eq: {
                        name: "public4"
                    }
                }
            ]
        ', []);
    }

    public function testAnyOperatorWithMultipleEqualOperators(): void
    {
        $this->constructNameFilterQuery('
            any: [
                {
                    eq: {
                        name: "public2"
                    }
                },
                {
                    eq: {
                        name: "public4"
                    }
                }
            ]
        ', [
            'public2',
            'public4',
        ]);
    }

    public function testAllOperatorWithLtGtRange(): void
    {
        $this->constructNameFilterQuery('
            all: [
                {
                    gt: {
                        name: "public1"
                    },
                },
                {
                    lt: {
                        name: "public4"
                    }
                }
            ]
        ', [
            'public2',
            'public3',
        ]);
    }

    public function testEnumFiltering(): void
    {
        $this->actingAs($this->users['admin'])->graphQL('
            query {
                projects(filters: {
                    eq: {
                        visibility: PRIVATE
                    }
                }) {
                    edges {
                        node {
                            name
                            visibility
                        }
                    }
                }
            }
        ')->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => 'private1',
                                'visibility' => 'PRIVATE',
                            ],
                        ],
                        [
                            'node' => [
                                'name' => 'private2',
                                'visibility' => 'PRIVATE',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testFilterByAttributeNotInQuery(): void
    {
        $this->actingAs($this->users['admin'])->graphQL('
            query {
                projects(filters: {
                    eq: {
                        visibility: PRIVATE
                    }
                }) {
                    edges {
                        node {
                            name
                        }
                    }
                }
            }
        ')->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => 'private1',
                            ],
                        ],
                        [
                            'node' => [
                                'name' => 'private2',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testNestedFilters(): void
    {
        $this->constructNameFilterQuery('
            any: [
                {
                    all: [
                        {
                            any: [
                                {
                                    eq: {
                                        name: "public1"
                                    }
                                },
                                {
                                    eq: {
                                        name: "private1"
                                    }
                                }
                            ]
                        },
                        {
                            eq: {
                                visibility: PRIVATE
                            }
                        }
                    ]
                }
                {
                    eq: {
                        name: "public2"
                    }
                },
                {
                    eq: {
                        name: "public4"
                    }
                }
            ]
        ', [
            'public2',
            'public4',
            'private1',
        ]);
    }

    public function testFilterCyclicQueries(): void
    {
        $build1uuid = Str::uuid()->toString();
        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => $build1uuid,
        ]);
        $build2uuid = Str::uuid()->toString();
        $this->projects['public1']->builds()->create([
            'name' => 'build2',
            'uuid' => $build2uuid,
        ]);

        $this->actingAs($this->users['admin'])->graphQL('
            query {
                projects(first: 1) {
                    edges {
                        node {
                            name
                            builds(filters: {
                                eq: {
                                    name: "build1"
                                }
                            }) {
                                edges {
                                    node {
                                        name
                                        uuid
                                        project {
                                            name
                                            builds {
                                                edges {
                                                    node {
                                                        name
                                                        uuid
                                                        project {
                                                            name
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ')->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => 'public1',
                                'builds' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'name' => 'build1',
                                                'uuid' => $build1uuid,
                                                'project' => [
                                                    'name' => 'public1',
                                                    'builds' => [
                                                        'edges' => [
                                                            [
                                                                'node' => [
                                                                    'name' => 'build1',
                                                                    'uuid' => $build1uuid,
                                                                    'project' => [
                                                                        'name' => 'public1',
                                                                    ],
                                                                ],
                                                            ],
                                                            [
                                                                'node' => [
                                                                    'name' => 'build2',
                                                                    'uuid' => $build2uuid,
                                                                    'project' => [
                                                                        'name' => 'public1',
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
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testProhibitsMultipleFieldsInSingleFilterObject(): void
    {
        $this->actingAs($this->users['admin'])->graphQL('
            query {
                projects(filters: {
                    eq: {
                        visibility: PRIVATE
                    }
                    ne: {
                        visibility: PUBLIC
                    }
                }) {
                    edges {
                        node {
                            name
                            visibility
                        }
                    }
                }
            }
        ')->assertGraphQLErrorMessage('Validation failed for the field [projects].');
    }

    public function testFilterByRelationship(): void
    {
        $build1uuid = Str::uuid()->toString();
        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => $build1uuid,
        ]);
        $build2uuid = Str::uuid()->toString();
        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => $build2uuid,
        ]);

        $this->actingAs($this->users['admin'])->graphQL('
            query($uuid: String!) {
                projects(filters: {
                    has: {
                        builds: {
                            eq: {
                                uuid: $uuid
                            }
                        }
                    }
                }) {
                    edges {
                        node {
                            name
                        }
                    }
                }
            }
        ', [
            'uuid' => $build1uuid,
        ])->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => $this->projects['public1']->name,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testFilterByRelationshipAndRegularField(): void
    {
        $build1uuid = Str::uuid()->toString();
        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => $build1uuid,
        ]);
        $build2uuid = Str::uuid()->toString();
        $this->projects['public1']->builds()->create([
            'name' => 'build2',
            'uuid' => $build2uuid,
        ]);

        $this->actingAs($this->users['admin'])->graphQL('
            query($uuid: String!, $projectname: String!) {
                projects(filters: {
                    any: [
                        {
                            has: {
                                builds: {
                                    eq: {
                                        uuid: $uuid
                                    }
                                }
                            }
                        },
                        {
                            eq: {
                                name: $projectname
                            }
                        }
                    ]
                }) {
                    edges {
                        node {
                            name
                        }
                    }
                }
            }
        ', [
            'uuid' => $build1uuid,
            'projectname' => $this->projects['public2']->name,
        ])->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => $this->projects['public1']->name,
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['public2']->name,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testFilterByAnyMultipleFieldsInRelationship(): void
    {
        $build1uuid = Str::uuid()->toString();
        $build1name = Str::uuid()->toString();
        $this->projects['public1']->builds()->create([
            'name' => $build1name,
            'uuid' => $build1uuid,
        ]);
        $build2uuid = Str::uuid()->toString();
        $build2name = Str::uuid()->toString();
        $this->projects['public2']->builds()->create([
            'name' => $build2name,
            'uuid' => $build2uuid,
        ]);

        $this->actingAs($this->users['admin'])->graphQL('
            query($uuid: String!, $buildname: String!) {
                projects(filters: {
                    has: {
                        builds: {
                            any: [
                                {
                                    eq: {
                                        uuid: $uuid
                                    }
                                },
                                {
                                    eq: {
                                        name: $buildname
                                    }
                                },
                            ]
                        }
                    }
                }) {
                    edges {
                        node {
                            name
                        }
                    }
                }
            }
        ', [
            'uuid' => $build1uuid,
            'buildname' => $build2name,
        ])->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => $this->projects['public1']->name,
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['public2']->name,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testFilterByAllMultipleFieldsInRelationshipNoneIfNotOnSameRecord(): void
    {
        $build1uuid = Str::uuid()->toString();
        $build1name = Str::uuid()->toString();
        $this->projects['public1']->builds()->create([
            'name' => $build1name,
            'uuid' => $build1uuid,
        ]);
        $build2uuid = Str::uuid()->toString();
        $build2name = Str::uuid()->toString();
        $this->projects['public1']->builds()->create([
            'name' => $build2name,
            'uuid' => $build2uuid,
        ]);

        $this->actingAs($this->users['admin'])->graphQL('
            query($uuid: String!, $buildname: String!) {
                projects(filters: {
                    has: {
                        builds: {
                            all: [
                                {
                                    eq: {
                                        uuid: $uuid
                                    }
                                },
                                {
                                    eq: {
                                        name: $buildname
                                    }
                                },
                            ]
                        }
                    }
                }) {
                    edges {
                        node {
                            name
                        }
                    }
                }
            }
        ', [
            'uuid' => $build1uuid,
            'buildname' => $build2name,
        ])->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [],
                ],
            ],
        ]);
    }

    public function testFilterByAllMultipleFieldsInRelationshipReturnsIfConditionsOnSameRecord(): void
    {
        $build1uuid = Str::uuid()->toString();
        $build1name = Str::uuid()->toString();
        $this->projects['public1']->builds()->create([
            'name' => $build1name,
            'uuid' => $build1uuid,
        ]);
        $build2uuid = Str::uuid()->toString();
        $build2name = Str::uuid()->toString();
        $this->projects['public1']->builds()->create([
            'name' => $build2name,
            'uuid' => $build2uuid,
        ]);

        $this->actingAs($this->users['admin'])->graphQL('
            query($uuid: String!, $buildname: String!) {
                projects(filters: {
                    has: {
                        builds: {
                            all: [
                                {
                                    eq: {
                                        uuid: $uuid
                                    }
                                },
                                {
                                    eq: {
                                        name: $buildname
                                    }
                                },
                            ]
                        }
                    }
                }) {
                    edges {
                        node {
                            name
                        }
                    }
                }
            }
        ', [
            'uuid' => $build1uuid,
            'buildname' => $build1name,
        ])->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => $this->projects['public1']->name,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testFilterByAnyMultipleRelationships(): void
    {
        $this->sites['site1'] = $this->makeSite();

        $build1uuid = Str::uuid()->toString();
        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => $build1uuid,
        ]);
        $build2uuid = Str::uuid()->toString();
        $this->projects['public2']->builds()->create([
            'name' => 'build2',
            'uuid' => $build2uuid,
            'siteid' => $this->sites['site1']->id,
        ]);

        $this->actingAs($this->users['admin'])->graphQL('
            query($uuid: String!, $siteid: ID!) {
                projects(filters: {
                    any: [
                        {
                            has: {
                                builds: {
                                    eq: {
                                        uuid: $uuid
                                    }
                                }
                            }
                        },
                        {
                            has: {
                                sites: {
                                    eq: {
                                        id: $siteid
                                    }
                                }
                            }
                        },
                    ]
                }) {
                    edges {
                        node {
                            name
                        }
                    }
                }
            }
        ', [
            'uuid' => $build1uuid,
            'siteid' => $this->sites['site1']->id,
        ])->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => $this->projects['public1']->name,
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['public2']->name,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testFilterByAllMultipleRelationships(): void
    {
        $this->sites['site1'] = $this->makeSite();

        $build1uuid = Str::uuid()->toString();
        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => $build1uuid,
        ]);
        $build2uuid = Str::uuid()->toString();
        $this->projects['public1']->builds()->create([
            'name' => 'build2',
            'uuid' => $build2uuid,
            'siteid' => $this->sites['site1']->id,
        ]);

        $this->actingAs($this->users['admin'])->graphQL('
            query($uuid: String!, $siteid: ID!) {
                projects(filters: {
                    all: [
                        {
                            has: {
                                builds: {
                                    eq: {
                                        uuid: $uuid
                                    }
                                }
                            }
                        },
                        {
                            has: {
                                sites: {
                                    eq: {
                                        id: $siteid
                                    }
                                }
                            }
                        },
                    ]
                }) {
                    edges {
                        node {
                            name
                        }
                    }
                }
            }
        ', [
            'uuid' => $build1uuid,
            'siteid' => $this->sites['site1']->id,
        ])->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => $this->projects['public1']->name,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testFilterByRelationshipsOfRelationships(): void
    {
        $build1uuid = Str::uuid()->toString();
        $target1name = Str::uuid()->toString();
        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => $build1uuid,
        ])->targets()->create([
            'name' => $target1name,
            'type' => TargetType::UNKNOWN,
        ]);

        $build2uuid = Str::uuid()->toString();
        $target2name = Str::uuid()->toString();
        $this->projects['public2']->builds()->create([
            'name' => 'build1',
            'uuid' => $build2uuid,
        ])->targets()->create([
            'name' => $target2name,
            'type' => TargetType::UNKNOWN,
        ]);

        $this->actingAs($this->users['admin'])->graphQL('
            query($targetname: String!) {
                projects(filters: {
                    has: {
                        builds: {
                            has: {
                                targets: {
                                    eq: {
                                        name: $targetname
                                    }
                                }
                            }
                        }
                    }
                }) {
                    edges {
                        node {
                            name
                        }
                    }
                }
            }
        ', [
            'targetname' => $target1name,
        ])->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => $this->projects['public1']->name,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testFilterAggregateField(): void
    {
        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->projects['public1']->builds()->create([
            'name' => 'build2',
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->projects['public2']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->actingAs($this->users['admin'])->graphQL('
            query($projectid: ID!, $buildname: String!) {
                project(id: $projectid) {
                    countWithoutFilters: buildCount
                    countWithFilters: buildCount(filters: {
                        eq: {
                            name: $buildname
                        }
                    })
                }
            }
        ', [
            'projectid' => $this->projects['public1']->id,
            'buildname' => 'build1',
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'countWithoutFilters' => 2,
                    'countWithFilters' => 1,
                ],
            ],
        ]);
    }

    public function testFilterNonPaginatedList(): void
    {
        /** @var Build $build */
        $build = $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Test $test */
        $test = $build->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'passed',
            'outputid' => $this->testOutput->id,
        ]);

        $measurement1 = $test->testMeasurements()->create([
            'name' => Str::uuid()->toString(),
            'type' => 'text/string',
            'value' => Str::uuid()->toString(),
        ]);

        $measurement2 = $test->testMeasurements()->create([
            'name' => Str::uuid()->toString(),
            'type' => 'text/string',
            'value' => Str::uuid()->toString(),
        ]);

        $this->actingAs($this->users['admin'])->graphQL('
            query($buildid: ID!, $measurementname: String!) {
                build(id: $buildid) {
                    tests {
                        edges {
                            node {
                                testMeasurements(filters: {
                                    eq: {
                                        name: $measurementname
                                    }
                                }) {
                                    name
                                    type
                                    value
                                }
                            }
                        }
                    }
                }
            }
        ', [
            'buildid' => $build->id,
            'measurementname' => $measurement1->name,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'tests' => [
                        'edges' => [
                            [
                                'node' => [
                                    'testMeasurements' => [
                                        [
                                            'name' => $measurement1->name,
                                            'type' => $measurement1->type,
                                            'value' => $measurement1->value,
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
