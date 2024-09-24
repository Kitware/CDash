<?php

namespace Tests\Feature\GraphQL;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class ProjectTypeTest extends TestCase
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
        ')->assertJson([
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
                'visibility' => 'PUBLIC',
                'authenticateSubmissions' => false,
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
                'visibility' => 'PUBLIC',
                'authenticateSubmissions' => false,
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
                'visibility' => 'PUBLIC',
                'authenticateSubmissions' => false,
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
                'visibility' => 'PUBLIC',
                'authenticateSubmissions' => false,
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
                'visibility' => 'PUBLIC',
                'authenticateSubmissions' => false,
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
        ')->assertJson([
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
                                'administrators' => [],
                            ],
                        ],
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
        ')->assertJson([
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
                                'administrators' => [],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['protected1']->name,
                                'administrators' => [],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['protected2']->name,
                                'administrators' => [],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['private1']->name,
                                'administrators' => [],
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
        ], true);
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
        ')->assertJson([
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
                                'administrators' => [],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['protected1']->name,
                                'administrators' => [],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['protected2']->name,
                                'administrators' => [],
                            ],
                        ],
                        [
                            'node' => [
                                'name' => $this->projects['private1']->name,
                                'administrators' => [],
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
                                'administrators' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ], true);
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
        ')->assertJson([
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
        ], true);
    }

    /**
     * @return array{
     *     array{
     *         string,
     *         string,
     *         string,
     *         bool
     *     }
     * }
     */
    public function createProjectVisibilityRules(): array
    {
        return [
            ['normal', 'PUBLIC', 'PUBLIC',  true],
            ['normal', 'PROTECTED', 'PUBLIC', true],
            ['normal', 'PRIVATE', 'PUBLIC', true],
            ['normal', 'PUBLIC', 'PROTECTED', false],
            ['normal', 'PROTECTED', 'PROTECTED', true],
            ['normal', 'PRIVATE', 'PROTECTED', true],
            ['normal', 'PUBLIC', 'PRIVATE', false],
            ['normal', 'PROTECTED', 'PRIVATE', false],
            ['normal', 'PRIVATE', 'PRIVATE', true],
            ['admin', 'PUBLIC', 'PUBLIC', true],
            ['admin', 'PROTECTED', 'PUBLIC', true],
            ['admin', 'PRIVATE', 'PUBLIC', true],
            ['admin', 'PUBLIC', 'PROTECTED', true],
            ['admin', 'PROTECTED', 'PROTECTED', true],
            ['admin', 'PRIVATE', 'PROTECTED', true],
            ['admin', 'PUBLIC', 'PRIVATE', true],
            ['admin', 'PROTECTED', 'PRIVATE',  true],
            ['admin', 'PRIVATE', 'PRIVATE',  true],
        ];
    }

    /**
     * @dataProvider createProjectVisibilityRules
     */
    public function testCreateProjectMaxVisibility(string $user, string $visibility, string $max_visibility, bool $can_create): void
    {
        Config::set('cdash.user_create_projects', true);
        Config::set('cdash.max_project_visibility', $max_visibility);

        $name = 'test-project' . Str::uuid();
        $response = $this->actingAs($this->users[$user])->graphQL('
            mutation CreateProject($input: CreateProjectInput!) {
                createProject(input: $input) {
                    visibility
                }
            }
        ', [
            'input' => [
                'name' => $name,
                'description' => 'test',
                'homeurl' => 'https://cdash.org',
                'visibility' => $visibility,
                'authenticateSubmissions' => false,
            ],
        ]);

        if ($can_create) {
            $project = Project::where('name', $name)->firstOrFail();
            $response->assertJson([
                'data' => [
                    'createProject' => [
                        'visibility' => $visibility,
                    ],
                ],
            ], true);
            $project->delete();
        } else {
            // A final check to ensure this project wasn't created anyway
            $this->assertDatabaseMissing(Project::class, [
                'name' => $name,
            ]);
            $response->assertGraphQLErrorMessage('Validation failed for the field [createProject].');
        }
    }

    /**
     * @return array{
     *     array{
     *         string,
     *         bool,
     *         bool,
     *         bool
     *     }
     * }
     */
    public function authenticatedSubmissionRules(): array
    {
        return [
            ['normal', false, false,  true],
            ['normal', true, false,  true],
            ['normal', false, true,  false],
            ['normal', true, true,  true],
            // Instance admins can set any value
            ['admin', false, false,  true],
            ['admin', true, false,  true],
            ['admin', false, true,  true],
            ['admin', true, true,  true],
        ];
    }

    /**
     * @dataProvider authenticatedSubmissionRules
     */
    public function testRequireAuthenticatedSubmissions(
        string $user,
        bool $use_authenticated_submits,
        bool $require_authenticated_submissions,
        bool $result
    ): void {
        Config::set('cdash.user_create_projects', true);
        Config::set('cdash.require_authenticated_submissions', $require_authenticated_submissions);

        $name = 'test-project' . Str::uuid();
        $response = $this->actingAs($this->users[$user])->graphQL('
            mutation CreateProject($input: CreateProjectInput!) {
                createProject(input: $input) {
                    authenticateSubmissions
                }
            }
        ', [
            'input' => [
                'name' => $name,
                'description' => 'test',
                'homeurl' => 'https://cdash.org',
                'visibility' => 'PUBLIC',
                'authenticateSubmissions' => $use_authenticated_submits,
            ],
        ]);

        if ($result) {
            $project = Project::where('name', $name)->firstOrFail();
            $response->assertJson([
                'data' => [
                    'createProject' => [
                        'authenticateSubmissions' => $use_authenticated_submits,
                    ],
                ],
            ], true);
            $project->delete();
        } else {
            // A final check to ensure this project wasn't created anyway
            $this->assertDatabaseMissing(Project::class, [
                'name' => $name,
            ]);
            $response->assertGraphQLErrorMessage('Validation failed for the field [createProject].');
        }
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
        ')->assertJson([
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
        ], true);
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
        ')->assertJson([
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
        ], true);
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
        ')->assertJson([
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
        ], true);
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
        ')->assertJson([
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
        ], true);
    }
}
