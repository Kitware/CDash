<?php

namespace Tests\Feature\GraphQL;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class FilterTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;

    /**
     * @var array<Project> $projects
     */
    private array $projects;

    /**
     * @var array<User> $users
     */
    private array $users;

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

        // Wipe any existing users before creating new ones
        User::query()->delete();

        $this->users = [
            'normal' => $this->makeNormalUser(),
            'admin' => $this->makeAdminUser(),
        ];
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
        ")->assertJson([
            'data' => [
                'projects' => [
                    'edges' => $expected_edges,
                ],
            ],
        ], true);
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
        $this->actingAs($this->users['admin'])->graphQL("
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
        ")->assertJson([
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
        ], true);
    }

    public function testFilterByAttributeNotInQuery(): void
    {
        $this->actingAs($this->users['admin'])->graphQL("
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
        ")->assertJson([
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
        ], true);
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
        ')->assertJson([
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
        ], true);
    }
}
