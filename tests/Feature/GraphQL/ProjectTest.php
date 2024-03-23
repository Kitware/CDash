<?php

namespace Tests\Feature\GraphQL;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class ProjectTest extends TestCase
{
    use CreatesUsers;
    use CreatesProjects;

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

        $this->projects = [
            'public1' => Project::findOrFail((int) $this->makePublicProject()->Id),
            'public2' => Project::findOrFail((int) $this->makePublicProject()->Id),
            'protected1' => Project::findOrFail((int) $this->makeProtectedProject()->Id),
            'protected2' => Project::findOrFail((int) $this->makeProtectedProject()->Id),
            'private1' => Project::findOrFail((int) $this->makePrivateProject()->Id),
            'private2' => Project::findOrFail((int) $this->makePrivateProject()->Id),
            'private3' => Project::findOrFail((int) $this->makePrivateProject()->Id),
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
    public function projectAccessByUser(): array
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
    public function perProjectAccess(): array
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
     * @dataProvider projectAccessByUser
     */
    public function testProjectPermissions(?string $user, array $allowable_projects): void
    {
        $expected_json_response = [
            'data' => [
                'projects' => [],
            ],
        ];
        foreach ($allowable_projects as $project_name) {
            $expected_json_response['data']['projects'][] = [
                'name' => $this->projects[$project_name]->name,
            ];
        }

        ($user === null ? $this : $this->actingAs($this->users[$user]))
            ->graphQL('
                query {
                    projects {
                        name
                    }
                }
            ')->assertJson($expected_json_response, true);
    }

    /**
     * @dataProvider perProjectAccess
     */
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
            $response->assertJson([
                'data' => [
                    'project' => [
                        'name' => $this->projects[$project_name]->name,
                    ],
                ],
            ], true);
        } else {
            $response->assertJson([
                'data' => [
                    'project' => null,
                ],
            ], true);
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
                    name
                    builds {
                        name
                    }
                }
            }
        ')->assertJson([
            'data' => [
                'projects' => [
                    [
                        'name' => $this->projects['public1']->name,
                        'builds' => [
                            [
                                'name' => 'build1',
                            ],
                            [
                                'name' => 'build2',
                            ],
                            [
                                'name' => 'build3',
                            ],
                        ],
                    ],
                    [
                        'name' => $this->projects['public2']->name,
                        'builds' => [
                            [
                                'name' => 'build4',
                            ],
                            [
                                'name' => 'build5',
                            ],
                        ],
                    ],
                ],
            ],
        ], true);
    }

    public function testCreateProjectNoUser(): void
    {
        $name = 'test-project' . Str::uuid();
        $this->graphQL('
            mutation CreateProject($input: CreateProjectInput!) {
                createProject(input: $input) {
                    id
                    name
                }
            }
        ', [
            'input' => [
                'name' => $name,
                'description' => 'test',
                'homeurl' => 'https://cdash.org',
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        // A final check to ensure this project wasn't created anyway
        $this->assertDatabaseMissing(Project::class, [
            'name' => $name,
        ]);
    }

    public function testCreateProjectUnauthorizedUser(): void
    {
        $name = 'test-project' . Str::uuid();
        $this->actingAs($this->users['normal'])->graphQL('
            mutation CreateProject($input: CreateProjectInput!) {
                createProject(input: $input) {
                    id
                    name
                }
            }
        ', [
            'input' => [
                'name' => $name,
                'description' => 'test',
                'homeurl' => 'https://cdash.org',
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        // A final check to ensure this project wasn't created anyway
        $this->assertDatabaseMissing(Project::class, [
            'name' => $name,
        ]);
    }

    public function testCreateProjectUserCreateProjectNoUser(): void
    {
        Config::set('cdash.user_create_projects', true);

        $name = 'test-project' . Str::uuid();
        $this->graphQL('
            mutation CreateProject($input: CreateProjectInput!) {
                createProject(input: $input) {
                    id
                    name
                }
            }
        ', [
            'input' => [
                'name' => $name,
                'description' => 'test',
                'homeurl' => 'https://cdash.org',
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');

        // A final check to ensure this project wasn't created anyway
        $this->assertDatabaseMissing(Project::class, [
            'name' => $name,
        ]);
    }

    public function testCreateProjectUserCreateProject(): void
    {
        Config::set('cdash.user_create_projects', true);

        $name = 'test-project' . Str::uuid();
        $response = $this->actingAs($this->users['normal'])->graphQL('
            mutation CreateProject($input: CreateProjectInput!) {
                createProject(input: $input) {
                    id
                    name
                }
            }
        ', [
            'input' => [
                'name' => $name,
                'description' => 'test',
                'homeurl' => 'https://cdash.org',
            ],
        ]);

        $project = Project::where('name', $name)->firstOrFail();

        $response->assertJson([
            'data' => [
                'createProject' => [
                    'id' => (string) $project->id,
                    'name' => $name,
                ],
            ],
        ], true);

        $project->delete();
    }

    public function testCreateProjectAdmin(): void
    {
        $name = 'test-project' . Str::uuid();
        $response = $this->actingAs($this->users['admin'])->graphQL('
            mutation CreateProject($input: CreateProjectInput!) {
                createProject(input: $input) {
                    id
                    name
                }
            }
        ', [
            'input' => [
                'name' => $name,
                'description' => 'test',
                'homeurl' => 'https://cdash.org',
            ],
        ]);

        $project = Project::where('name', $name)->firstOrFail();

        $response->assertJson([
            'data' => [
                'createProject' => [
                    'id' => (string) $project->id,
                    'name' => $name,
                ],
            ],
        ], true);

        $project->delete();
    }

    public function testGetProjectAdministratorsNoUser(): void
    {
        $this->graphQL('
            query {
                projects {
                    name
                    administrators {
                        id
                    }
                }
            }
        ')->assertJson([
            'data' => [
                'projects' => [
                    [
                        'name' => $this->projects['public1']->name,
                        'administrators' => [
                            [
                                'id' => (string) $this->users['normal']->id,
                            ],
                            [
                                'id' => (string) $this->users['admin']->id,
                            ],
                        ],
                    ],
                    [
                        'name' => $this->projects['public2']->name,
                        'administrators' => [],
                    ],
                ],
            ],
        ], true);
    }

    public function testGetProjectAdministratorsAsNormalUser(): void
    {
        $this->actingAs($this->users['normal'])->graphQL('
            query {
                projects {
                    name
                    administrators {
                        id
                    }
                }
            }
        ')->assertJson([
            'data' => [
                'projects' => [
                    [
                        'name' => $this->projects['public1']->name,
                        'administrators' => [
                            [
                                'id' => (string) $this->users['normal']->id,
                            ],
                            [
                                'id' => (string) $this->users['admin']->id,
                            ],
                        ],
                    ],
                    [
                        'name' => $this->projects['public2']->name,
                        'administrators' => [],
                    ],
                    [
                        'name' => $this->projects['protected1']->name,
                        'administrators' => [],
                    ],
                    [
                        'name' => $this->projects['protected2']->name,
                        'administrators' => [],
                    ],
                    [
                        'name' => $this->projects['private1']->name,
                        'administrators' => [],
                    ],
                    [
                        'name' => $this->projects['private2']->name,
                        'administrators' => [
                            [
                                'id' => (string) $this->users['normal']->id,
                            ],
                        ],
                    ],
                ],
            ],
        ], true);
    }

    public function testGetProjectAdministratorsAsAdmin(): void
    {
        $this->actingAs($this->users['admin'])->graphQL('
            query {
                projects {
                    name
                    administrators {
                        id
                    }
                }
            }
        ')->assertJson([
            'data' => [
                'projects' => [
                    [
                        'name' => $this->projects['public1']->name,
                        'administrators' => [
                            [
                                'id' => (string) $this->users['normal']->id,
                            ],
                            [
                                'id' => (string) $this->users['admin']->id,
                            ],
                        ],
                    ],
                    [
                        'name' => $this->projects['public2']->name,
                        'administrators' => [],
                    ],
                    [
                        'name' => $this->projects['protected1']->name,
                        'administrators' => [],
                    ],
                    [
                        'name' => $this->projects['protected2']->name,
                        'administrators' => [],
                    ],
                    [
                        'name' => $this->projects['private1']->name,
                        'administrators' => [],
                    ],
                    [
                        'name' => $this->projects['private2']->name,
                        'administrators' => [
                            [
                                'id' => (string) $this->users['normal']->id,
                            ],
                        ],
                    ],
                    [
                        'name' => $this->projects['private3']->name,
                        'administrators' => [],
                    ],
                ],
            ],
        ], true);
    }
}
