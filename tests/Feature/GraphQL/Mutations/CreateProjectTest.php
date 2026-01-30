<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesUsers;

class CreateProjectTest extends TestCase
{
    use CreatesUsers;
    use DatabaseTransactions;

    /**
     * @var array<Project>
     */
    private array $projects = [];

    /**
     * @var array<User>
     */
    private array $users = [];

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
                'homeUrl' => 'https://cdash.org',
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
        $this->users['normal'] = $this->makeNormalUser();

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
                'homeUrl' => 'https://cdash.org',
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
                'homeUrl' => 'https://cdash.org',
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

        $this->users['normal'] = $this->makeNormalUser();

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
                'homeUrl' => 'https://cdash.org',
                'visibility' => 'PUBLIC',
                'authenticateSubmissions' => false,
            ],
        ]);

        $project = Project::where('name', $name)->firstOrFail();
        $this->projects[] = $project;

        $response->assertExactJson([
            'data' => [
                'createProject' => [
                    'id' => (string) $project->id,
                    'name' => $name,
                ],
            ],
        ]);

        self::assertContains($this->users['normal']->id, $project->administrators()->pluck('id')->all());
    }

    public function testCreateProjectAdmin(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

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
                'homeUrl' => 'https://cdash.org',
                'visibility' => 'PUBLIC',
                'authenticateSubmissions' => false,
            ],
        ]);

        $project = Project::where('name', $name)->firstOrFail();
        $this->projects[] = $project;

        $response->assertExactJson([
            'data' => [
                'createProject' => [
                    'id' => (string) $project->id,
                    'name' => $name,
                ],
            ],
        ]);

        self::assertContains($this->users['admin']->id, $project->administrators()->pluck('id')->all());
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
    public static function createProjectVisibilityRules(): array
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

    #[DataProvider('createProjectVisibilityRules')]
    public function testCreateProjectMaxVisibility(string $user, string $visibility, string $max_visibility, bool $can_create): void
    {
        Config::set('cdash.user_create_projects', true);
        Config::set('cdash.max_project_visibility', $max_visibility);

        $this->users['normal'] = $this->makeNormalUser();
        $this->users['admin'] = $this->makeAdminUser();

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
                'homeUrl' => 'https://cdash.org',
                'visibility' => $visibility,
                'authenticateSubmissions' => false,
            ],
        ]);

        if ($can_create) {
            $this->projects[] = Project::where('name', $name)->firstOrFail();
            $response->assertExactJson([
                'data' => [
                    'createProject' => [
                        'visibility' => $visibility,
                    ],
                ],
            ]);
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
    public static function authenticatedSubmissionRules(): array
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

    #[DataProvider('authenticatedSubmissionRules')]
    public function testRequireAuthenticatedSubmissions(
        string $user,
        bool $use_authenticated_submits,
        bool $require_authenticated_submissions,
        bool $result,
    ): void {
        Config::set('cdash.user_create_projects', true);
        Config::set('cdash.require_authenticated_submissions', $require_authenticated_submissions);

        $this->users['normal'] = $this->makeNormalUser();
        $this->users['admin'] = $this->makeAdminUser();

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
                'homeUrl' => 'https://cdash.org',
                'visibility' => 'PUBLIC',
                'authenticateSubmissions' => $use_authenticated_submits,
            ],
        ]);

        if ($result) {
            $this->projects[] = Project::where('name', $name)->firstOrFail();
            $response->assertExactJson([
                'data' => [
                    'createProject' => [
                        'authenticateSubmissions' => $use_authenticated_submits,
                    ],
                ],
            ]);
        } else {
            // A final check to ensure this project wasn't created anyway
            $this->assertDatabaseMissing(Project::class, [
                'name' => $name,
            ]);
            $response->assertGraphQLErrorMessage('Validation failed for the field [createProject].');
        }
    }
}
