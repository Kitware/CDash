<?php

namespace Tests\Feature\GraphQL;

use App\Models\Project;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class UserTypeTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;
    use DatabaseTransactions;

    public function testBasicFieldAccess(): void
    {
        $normalUser = $this->makeNormalUser();

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
                ],
            ],
        ]);
    }

    public function testCanSeeOwnEmail(): void
    {
        $normalUser = $this->makeNormalUser();

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
        $normalUser = $this->makeNormalUser();
        $adminUser = $this->makeNormalUser();

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
        $normalUser = $this->makeNormalUser();

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
        $normalUser = $this->makeNormalUser();
        $adminUser = $this->makeAdminUser();

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

        $projectUser = $this->makeNormalUser();
        $publicProject->users()->attach($projectUser, ['role' => Project::PROJECT_USER]);
        $protectedProject->users()->attach($projectUser, ['role' => Project::PROJECT_USER]);
        $privateProject->users()->attach($projectUser, ['role' => Project::PROJECT_USER]);

        if ($user === 'normal') {
            $user = $this->makeNormalUser();
        } elseif ($user === 'project_member') {
            $user = $this->makeNormalUser();
            $publicProject->users()->attach($user, ['role' => Project::PROJECT_USER]);
            $protectedProject->users()->attach($user, ['role' => Project::PROJECT_USER]);
            $privateProject->users()->attach($user, ['role' => Project::PROJECT_USER]);
        } elseif ($user === 'project_admin') {
            $user = $this->makeNormalUser();
            $publicProject->users()->attach($user, ['role' => Project::PROJECT_ADMIN]);
            $protectedProject->users()->attach($user, ['role' => Project::PROJECT_ADMIN]);
            $privateProject->users()->attach($user, ['role' => Project::PROJECT_ADMIN]);
        } elseif ($user === 'admin') {
            $user = $this->makeAdminUser();
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
}
