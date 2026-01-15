<?php

namespace Tests\Feature\GraphQL;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class ProjectTypeTest extends TestCase
{
    use CreatesProjects;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->projects = [
            'public1' => $this->makePublicProject(),
            'public2' => $this->makePublicProject(),
            'protected1' => $this->makeProtectedProject(),
            'protected2' => $this->makeProtectedProject(),
            'private1' => $this->makePrivateProject(),
            'private2' => $this->makePrivateProject(),
            'private3' => $this->makePrivateProject(),
        ];

        // Wipe any existing users before creating new ones
        User::query()->delete();

        $this->users = [
            'normal' => $this->makeNormalUser(),
            'admin' => $this->makeAdminUser(),
        ];

        $user2project_data = [
            'emailtype' => 0,
            'emailcategory' => 0,
            'emailsuccess' => true,
            'emailmissingsites' => true,
        ];

        $this->projects['public1']
            ->users()
            ->attach($this->users['normal']->id, $user2project_data + ['role' => Project::PROJECT_ADMIN]);

        $this->projects['public1']
            ->users()
            ->attach($this->users['admin']->id, $user2project_data + ['role' => Project::PROJECT_ADMIN]);

        $this->projects['protected2']
            ->users()
            ->attach($this->users['normal']->id, $user2project_data + ['role' => Project::PROJECT_USER]);

        $this->projects['private1']
            ->users()
            ->attach($this->users['normal']->id, $user2project_data + ['role' => Project::PROJECT_USER]);

        $this->projects['private2']
            ->users()
            ->attach($this->users['normal']->id, $user2project_data + ['role' => Project::PROJECT_ADMIN]);
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
     * @return array{
     *     array{
     *         string|null, array<string>
     *     }
     * }
     */
    public static function projectAccessByUser(): array
    {
        return [
            [
                null,
                [
                    'public1',
                    'public2',
                ],
            ],
            [
                'normal',
                [
                    'public1',
                    'public2',
                    'protected1',
                    'protected2',
                    'private1',
                    'private2',
                ],
            ],
            [
                'admin',
                [
                    'public1',
                    'public2',
                    'protected1',
                    'protected2',
                    'private1',
                    'private2',
                    'private3',
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     array{
     *         string|null, string, bool
     *     }
     * }
     */
    public static function perProjectAccess(): array
    {
        return [
            // No user
            [null, 'public1', true],
            [null, 'public2', true],
            [null, 'protected1', false],
            [null, 'protected2', false],
            [null, 'private1', false],
            [null, 'private2', false],
            [null, 'private3', false],
            // Normal user
            ['normal', 'public1', true],
            ['normal', 'public2', true],
            ['normal', 'protected1', true],
            ['normal', 'protected2', true],
            ['normal', 'private1', true],
            ['normal', 'private2', true],
            ['normal', 'private3', false],
            // Admin user
            ['admin', 'public1', true],
            ['admin', 'public2', true],
            ['admin', 'protected1', true],
            ['admin', 'protected2', true],
            ['admin', 'private1', true],
            ['admin', 'private2', true],
            ['admin', 'private3', true],
        ];
    }

    /**
     * @param array<string> $allowable_projects
     */
    #[DataProvider('projectAccessByUser')]
    public function testProjectPermissions(?string $user, array $allowable_projects): void
    {
        $expected_json_response = [
            'data' => [
                'projects' => [
                    'edges' => [],
                ],
            ],
        ];
        foreach ($allowable_projects as $project_name) {
            $expected_json_response['data']['projects']['edges'][] = [
                'node' => [
                    'name' => $this->projects[$project_name]->name,
                ],
            ];
        }

        ($user === null ? $this : $this->actingAs($this->users[$user]))
            ->graphQL('
                query {
                    projects {
                        edges {
                            node {
                                name
                            }
                        }
                    }
                }
            ')->assertExactJson($expected_json_response);
    }

    #[DataProvider('perProjectAccess')]
    public function testIndividualProjectPermissions(?string $user, string $project_name, bool $allow_access): void
    {
        $response = ($user === null ? $this : $this->actingAs($this->users[$user]))
            ->graphQL('
                query ($id:ID) {
                    project(id:$id) {
                        name
                    }
                }
            ', [
                'id' => $this->projects[$project_name]->id,
            ]);

        if ($allow_access) {
            $response->assertExactJson([
                'data' => [
                    'project' => [
                        'name' => $this->projects[$project_name]->name,
                    ],
                ],
            ]);
        } else {
            $response->assertExactJson([
                'data' => [
                    'project' => null,
                ],
            ]);
        }
    }

    public function testBuildRelationship(): void
    {
        // Note: Deleting projects will also delete associated builds automatically via the foreign-key relationship.
        //       There is no need to clean up anything manually after this test.

        // Add a few builds
        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid(),
        ]);
        $this->projects['public1']->builds()->create([
            'name' => 'build2',
            'uuid' => Str::uuid(),
        ]);
        $this->projects['public1']->builds()->create([
            'name' => 'build3',
            'uuid' => Str::uuid(),
        ]);

        $this->projects['public2']->builds()->create([
            'name' => 'build4',
            'uuid' => Str::uuid(),
        ]);
        $this->projects['public2']->builds()->create([
            'name' => 'build5',
            'uuid' => Str::uuid(),
        ]);

        $this->graphQL('
            query {
                projects {
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
        ')->assertExactJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => $this->projects['public1']->name,
                                'builds' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'name' => 'build1',
                                            ],
                                        ],
                                        [
                                            'node' => [
                                                'name' => 'build2',
                                            ],
                                        ],
                                        [
                                            'node' => [
                                                'name' => 'build3',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['public2']->name,
                                'builds' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'name' => 'build4',
                                            ],
                                        ],
                                        [
                                            'node' => [
                                                'name' => 'build5',
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

    public function testGetProjectAdministratorsNoUser(): void
    {
        $this->graphQL('
            query {
                projects {
                    edges {
                        node {
                            name
                            administrators {
                                edges {
                                    node {
                                        id
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
                                'name' => $this->projects['public1']->name,
                                'administrators' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'id' => (string) $this->users['normal']->id,
                                            ],
                                        ],
                                        [
                                            'node' => [
                                                'id' => (string) $this->users['admin']->id,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['public2']->name,
                                'administrators' => [
                                    'edges' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testGetProjectAdministratorsAsNormalUser(): void
    {
        $this->actingAs($this->users['normal'])->graphQL('
            query {
                projects {
                    edges {
                        node {
                            name
                            administrators {
                                edges {
                                    node {
                                        id
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
                                'name' => $this->projects['public1']->name,
                                'administrators' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'id' => (string) $this->users['normal']->id,
                                            ],
                                        ],
                                        [
                                            'node' => [
                                                'id' => (string) $this->users['admin']->id,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['public2']->name,
                                'administrators' => [
                                    'edges' => [],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['protected1']->name,
                                'administrators' => [
                                    'edges' => [],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['protected2']->name,
                                'administrators' => [
                                    'edges' => [],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['private1']->name,
                                'administrators' => [
                                    'edges' => [],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['private2']->name,
                                'administrators' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'id' => (string) $this->users['normal']->id,
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

    public function testGetProjectAdministratorsAsAdmin(): void
    {
        $this->actingAs($this->users['admin'])->graphQL('
            query {
                projects {
                    edges {
                        node {
                            name
                            administrators {
                                edges {
                                    node {
                                        id
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
                                'name' => $this->projects['public1']->name,
                                'administrators' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'id' => (string) $this->users['normal']->id,
                                            ],
                                        ],
                                        [
                                            'node' => [
                                                'id' => (string) $this->users['admin']->id,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['public2']->name,
                                'administrators' => [
                                    'edges' => [],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['protected1']->name,
                                'administrators' => [
                                    'edges' => [],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['protected2']->name,
                                'administrators' => [
                                    'edges' => [],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['private1']->name,
                                'administrators' => [
                                    'edges' => [],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['private2']->name,
                                'administrators' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'id' => (string) $this->users['normal']->id,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['private3']->name,
                                'administrators' => [
                                    'edges' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testGetProjectUsersAsAdmin(): void
    {
        $this->actingAs($this->users['admin'])->graphQL('
            query {
                projects {
                    edges {
                        node {
                            name
                            basicUsers {
                                edges {
                                    node {
                                        id
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
                                'name' => $this->projects['public1']->name,
                                'basicUsers' => [
                                    'edges' => [],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['public2']->name,
                                'basicUsers' => [
                                    'edges' => [],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['protected1']->name,
                                'basicUsers' => [
                                    'edges' => [],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['protected2']->name,
                                'basicUsers' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'id' => (string) $this->users['normal']->id,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['private1']->name,
                                'basicUsers' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'id' => (string) $this->users['normal']->id,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['private2']->name,
                                'basicUsers' => [
                                    'edges' => [],
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['private3']->name,
                                'basicUsers' => [
                                    'edges' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testProjectVisibilityValue(): void
    {
        $this->actingAs($this->users['admin'])->graphQL('
            query {
                projects {
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
                                'name' => $this->projects['public1']->name,
                                'visibility' => 'PUBLIC',
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['public2']->name,
                                'visibility' => 'PUBLIC',
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['protected1']->name,
                                'visibility' => 'PROTECTED',
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['protected2']->name,
                                'visibility' => 'PROTECTED',
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['private1']->name,
                                'visibility' => 'PRIVATE',
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['private2']->name,
                                'visibility' => 'PRIVATE',
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['private3']->name,
                                'visibility' => 'PRIVATE',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * This test isn't intended to be a complete test of the GraphQL filtering
     * capability, but rather a quick smoke check to verify that the most basic
     * filters work for the projects relation, and that extra information is not leaked.
     */
    public function testBasicProjectFiltering(): void
    {
        $this->actingAs($this->users['normal'])->graphQL('
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
                                'name' => $this->projects['private1']->name,
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['private2']->name,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testMostRecentBuild(): void
    {
        // Add a few builds
        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'submittime' => '2009-02-23 10:07:03',
            'uuid' => Str::uuid(),
        ]);
        $this->projects['public1']->builds()->create([
            'name' => 'build2',
            'submittime' => '2010-02-23 11:07:03',
            'uuid' => Str::uuid(),
        ]);
        $this->projects['public1']->builds()->create([
            'name' => 'build3',
            'submittime' => '2009-02-23 11:07:03',
            'uuid' => Str::uuid(),
        ]);

        $this->graphQL('
            query {
                projects {
                    edges {
                        node {
                            name
                            mostRecentBuild {
                                name
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
                                'name' => $this->projects['public1']->name,
                                'mostRecentBuild' => [
                                    'name' => 'build2',
                                ],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['public2']->name,
                                'mostRecentBuild' => null,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testBuildCountFieldWithNoFilters(): void
    {
        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'submittime' => '2009-02-23 10:07:03',
            'uuid' => Str::uuid(),
        ]);
        $this->projects['public1']->builds()->create([
            'name' => 'build2',
            'submittime' => '2010-02-23 11:07:03',
            'uuid' => Str::uuid(),
        ]);
        $this->projects['public1']->builds()->create([
            'name' => 'build3',
            'submittime' => '2009-02-23 11:07:03',
            'uuid' => Str::uuid(),
        ]);

        $this->projects['public2']->builds()->create([
            'name' => 'build4',
            'submittime' => '2009-02-23 11:07:03',
            'uuid' => Str::uuid(),
        ]);

        $this->projects['private1']->builds()->create([
            'name' => 'build5',
            'submittime' => '2009-02-23 11:07:03',
            'uuid' => Str::uuid(),
        ]);

        $this->graphQL('
            query {
                projects {
                    edges {
                        node {
                            name
                            buildCount
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
                                'name' => $this->projects['public1']->name,
                                'buildCount' => 3,
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['public2']->name,
                                'buildCount' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testBuildCountFieldWithFilters(): void
    {
        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'submittime' => '2009-02-23 10:07:03',
            'uuid' => Str::uuid(),
        ]);
        $this->projects['public1']->builds()->create([
            'name' => 'build2',
            'submittime' => '2010-02-23 11:07:03',
            'uuid' => Str::uuid(),
        ]);
        $this->projects['public1']->builds()->create([
            'name' => 'build3',
            'submittime' => '2009-02-23 11:07:03',
            'uuid' => Str::uuid(),
        ]);

        $this->projects['public2']->builds()->create([
            'name' => 'build4',
            'submittime' => '2009-02-23 11:07:03',
            'uuid' => Str::uuid(),
        ]);

        $this->projects['private1']->builds()->create([
            'name' => 'build5',
            'submittime' => '2009-02-23 11:07:03',
            'uuid' => Str::uuid(),
        ]);

        $this->graphQL('
            query {
                projects {
                    edges {
                        node {
                            name
                            buildCount(filters: {
                                gt: {
                                    submissionTime: "2010-01-01T00:00:00+00:00"
                                }
                            })
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
                                'name' => $this->projects['public1']->name,
                                'buildCount' => 1,
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['public2']->name,
                                'buildCount' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
