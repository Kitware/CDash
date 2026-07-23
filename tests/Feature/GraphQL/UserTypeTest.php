<?php

namespace Tests\Feature\GraphQL;

use App\Models\AuthToken;
use App\Models\Project;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class UserTypeTest extends TestCase
{
    use CreatesProjects;

    use DatabaseTransactions;

    public function testBasicFieldAccess(): void
    {
        $normalUser = User::factory()->create();

        $this->actingAs($normalUser)->graphQL('
            query {
                me {
                    id
                    email
                    firstname
                    lastname
                    institution
                    admin
                    projects {
                        edges {
                            node {
                                id
                            }
                        }
                    }
                    authenticationTokens {
                        edges {
                            node {
                                id
                            }
                        }
                    }
                }
            }
        ')->assertExactJson([
            'data' => [
                'me' => [
                    'id' => (string) $normalUser->id,
                    'email' => $normalUser->email,
                    'firstname' => $normalUser->firstname,
                    'lastname' => $normalUser->lastname,
                    'institution' => $normalUser->institution,
                    'admin' => $normalUser->admin,
                    'projects' => [
                        'edges' => [],
                    ],
                    'authenticationTokens' => [
                        'edges' => [],
                    ],
                ],
            ],
        ]);
    }

    public function testCanSeeOwnEmail(): void
    {
        $normalUser = User::factory()->create();

        $this->actingAs($normalUser)->graphQL('
            query($userid: ID) {
                user(id: $userid) {
                    id
                    email
                }
            }
        ', [
            'userid' => $normalUser->id,
        ])->assertExactJson([
            'data' => [
                'user' => [
                    'id' => (string) $normalUser->id,
                    'email' => $normalUser->email,
                ],
            ],
        ]);
    }

    public function testCannotSeeEmailForOtherUsers(): void
    {
        $normalUser = User::factory()->create();
        $adminUser = User::factory()->create();

        $this->actingAs($normalUser)->graphQL('
            query($userid: ID) {
                user(id: $userid) {
                    id
                    email
                }
            }
        ', [
            'userid' => $adminUser->id,
        ])->assertExactJson([
            'data' => [
                'user' => [
                    'id' => (string) $adminUser->id,
                    'email' => null,
                ],
            ],
        ]);
    }

    public function testAnonUsersCannotSeeEmails(): void
    {
        $normalUser = User::factory()->create();

        $this->graphQL('
            query($userid: ID) {
                user(id: $userid) {
                    id
                    email
                }
            }
        ', [
            'userid' => $normalUser->id,
        ])->assertExactJson([
            'data' => [
                'user' => [
                    'id' => (string) $normalUser->id,
                    'email' => null,
                ],
            ],
        ]);
    }

    public function testAdminCanSeeAllEmails(): void
    {
        $normalUser = User::factory()->create();
        $adminUser = User::factory()->adminUser()->create();

        $this->actingAs($adminUser)->graphQL('
            query($userid: ID) {
                user(id: $userid) {
                    id
                    email
                }
            }
        ', [
            'userid' => $normalUser->id,
        ])->assertExactJson([
            'data' => [
                'user' => [
                    'id' => (string) $normalUser->id,
                    'email' => $normalUser->email,
                ],
            ],
        ]);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function projectsRelationshipVisibilityCases(): array
    {
        return [
            [null, true, false, false],
            ['normal', true, true, false],
            ['project_member', true, true, true],
            ['project_admin', true, true, true],
            ['admin', true, true, true],
        ];
    }

    #[DataProvider('projectsRelationshipVisibilityCases')]
    public function testProjectsRelationshipVisibility(
        ?string $user,
        bool $canSeePublicProject,
        bool $canSeeProtectedProject,
        bool $canSeePrivateProject,
    ): void {
        $publicProject = $this->makePublicProject();
        $protectedProject = $this->makeProtectedProject();
        $privateProject = $this->makePrivateProject();

        $projectUser = User::factory()->create();
        $publicProject->users()->attach($projectUser, ['role' => Project::PROJECT_USER]);
        $protectedProject->users()->attach($projectUser, ['role' => Project::PROJECT_USER]);
        $privateProject->users()->attach($projectUser, ['role' => Project::PROJECT_USER]);

        if ($user === 'normal') {
            $user = User::factory()->create();
        } elseif ($user === 'project_member') {
            $user = User::factory()->create();
            $publicProject->users()->attach($user, ['role' => Project::PROJECT_USER]);
            $protectedProject->users()->attach($user, ['role' => Project::PROJECT_USER]);
            $privateProject->users()->attach($user, ['role' => Project::PROJECT_USER]);
        } elseif ($user === 'project_admin') {
            $user = User::factory()->create();
            $publicProject->users()->attach($user, ['role' => Project::PROJECT_ADMIN]);
            $protectedProject->users()->attach($user, ['role' => Project::PROJECT_ADMIN]);
            $privateProject->users()->attach($user, ['role' => Project::PROJECT_ADMIN]);
        } elseif ($user === 'admin') {
            $user = User::factory()->adminUser()->create();
        } elseif ($user === null) {
            $user = null;
        } else {
            throw new Exception('Invalid user.');
        }

        ($user === null ? $this : $this->actingAs($user))->graphQL('
            query($userid: ID) {
                user(id: $userid) {
                    id
                    projects {
                        edges {
                            node {
                                id
                            }
                        }
                    }
                }
            }
        ', [
            'userid' => $projectUser->id,
        ])->assertExactJson([
            'data' => [
                'user' => [
                    'id' => (string) $projectUser->id,
                    'projects' => [
                        'edges' => [
                            ...($canSeePublicProject ? [['node' => ['id' => (string) $publicProject->id]]] : []),
                            ...($canSeeProtectedProject ? [['node' => ['id' => (string) $protectedProject->id]]] : []),
                            ...($canSeePrivateProject ? [['node' => ['id' => (string) $privateProject->id]]] : []),
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function authenticationTokensRelationshipVisibilityCases(): array
    {
        return [
            [null, false],
            ['normal', false],
            ['self', true],
            ['admin', true],
        ];
    }

    #[DataProvider('authenticationTokensRelationshipVisibilityCases')]
    public function testAuthenticationTokensRelationshipVisibility(
        ?string $user,
        bool $canSeeAuthToken,
    ): void {
        AuthToken::factory()->create([
            'userid' => User::factory()->create()->id,
        ]);

        $tokenOwner = User::factory()->create();
        /** @var AuthToken $authToken */
        $authToken = $tokenOwner->authenticationTokens()->save(AuthToken::factory()->make());

        if ($user === 'normal') {
            $user = User::factory()->create();
        } elseif ($user === 'self') {
            $user = $tokenOwner;
        } elseif ($user === 'admin') {
            $user = User::factory()->adminUser()->create();
        } elseif ($user === null) {
            $user = null;
        } else {
            throw new Exception('Invalid user.');
        }

        $response = ($user === null ? $this : $this->actingAs($user))->graphQL('
            query($userid: ID) {
                user(id: $userid) {
                    id
                    authenticationTokens {
                        edges {
                            node {
                                id
                            }
                        }
                    }
                }
            }
        ', [
            'userid' => $tokenOwner->id,
        ]);

        if ($canSeeAuthToken) {
            $response->assertExactJson([
                'data' => [
                    'user' => [
                        'id' => (string) $tokenOwner->id,
                        'authenticationTokens' => [
                            'edges' => [
                                [
                                    'node' => [
                                        'id' => (string) $authToken->id,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        } else {
            $response->assertGraphQLErrorMessage('This action is unauthorized.');
        }
    }
}
