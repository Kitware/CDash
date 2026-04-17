<?php

namespace Tests\Feature\GraphQL;

use App\Enums\BuildCommandType;
use App\Models\AuthToken;
use App\Models\BuildCommand;
use App\Models\DynamicAnalysis;
use App\Models\Project;
use App\Models\Test;
use App\Models\TestOutput;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class QueryTypeTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;
    use DatabaseTransactions;

    /** @var array<User> */
    private array $users = [];

    /** @var array<Project> */
    private array $projects = [];

    protected function tearDown(): void
    {
        foreach ($this->users as $user) {
            $user->delete();
        }
        $this->users = [];

        foreach ($this->projects as $project) {
            $project->delete();
        }
        $this->projects = [];

        parent::tearDown();
    }

    public function testMeFieldWhenSignedIn(): void
    {
        $user = $this->makeNormalUser();
        $this->users[] = $user;

        $this->actingAs($user)->graphQL('
            query {
                me {
                    id
                }
            }
        ')->assertExactJson([
            'data' => [
                'me' => [
                    'id' => (string) $user->id,
                ],
            ],
        ]);
    }

    public function testMeFieldWhenSignedOut(): void
    {
        $this->graphQL('
            query {
                me {
                    id
                }
            }
        ')->assertExactJson([
            'data' => [
                'me' => null,
            ],
        ]);
    }

    public function testUserFieldInvalidUser(): void
    {
        $this->graphQL('
            query {
                user(
                    id: "123456789"
                ){
                    id
                }
            }
        ')->assertExactJson([
            'data' => [
                'user' => null,
            ],
        ]);
    }

    public function testUserFieldValidUser(): void
    {
        $user = $this->makeNormalUser();
        $this->users[] = $user;

        $this->graphQL('
            query($userid: ID) {
                user(
                    id: $userid
                ){
                    id
                }
            }
        ', [
            'userid' => $user->id,
        ])->assertExactJson([
            'data' => [
                'user' => [
                    'id' => (string) $user->id,
                ],
            ],
        ]);
    }

    public function testUsersFieldBasicAccess(): void
    {
        $user1 = $this->makeNormalUser();
        $user2 = $this->makeNormalUser();
        $this->users[] = $user1;
        $this->users[] = $user2;

        $this->graphQL('
            query($user1: ID, $user2: ID) {
                users(filters: {
                    any: [
                        {
                            eq: {
                                id: $user1
                            }
                        },
                        {
                            eq: {
                                id: $user2
                            }
                        }
                    ]
                }){
                    edges {
                        node {
                            id
                        }
                    }
                }
            }
        ', [
            'user1' => $user1->id,
            'user2' => $user2->id,
        ])->assertExactJson([
            'data' => [
                'users' => [
                    'edges' => [
                        [
                            'node' => [
                                'id' => (string) $user1->id,
                            ],
                        ],
                        [
                            'node' => [
                                'id' => (string) $user2->id,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testBuildCommandFieldRestrictsAccessByProject(): void
    {
        $user = $this->makeNormalUser();
        $this->users[] = $user;

        $project1 = $this->makePrivateProject();
        $project1->users()
            ->attach($user->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_ADMIN,
            ]);
        /** @var BuildCommand $command1 */
        $command1 = $project1->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ])->commands()->create([
            'type' => BuildCommandType::CUSTOM,
            'starttime' => Carbon::now(),
            'duration' => 0,
            'command' => '',
            'result' => '',
            'workingdirectory' => Str::uuid()->toString(),
        ]);
        $this->projects[] = $project1;

        $project2 = $this->makePrivateProject();
        /** @var BuildCommand $command2 */
        $command2 = $project2->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ])->commands()->create([
            'type' => BuildCommandType::CUSTOM,
            'starttime' => Carbon::now(),
            'duration' => 0,
            'command' => '',
            'result' => '',
            'workingdirectory' => Str::uuid()->toString(),
        ]);
        $this->projects[] = $project2;

        $this->actingAs($user)->graphQL('
            query($id: ID!) {
                buildCommand(id: $id) {
                    id
                }
            }
        ', [
            'id' => $command1->id,
        ])->assertExactJson([
            'data' => [
                'buildCommand' => [
                    'id' => (string) $command1->id,
                ],
            ],
        ]);

        $this->actingAs($user)->graphQL('
            query($id: ID!) {
                buildCommand(id: $id) {
                    id
                }
            }
        ', [
            'id' => $command2->id,
        ])->assertExactJson([
            'data' => [
                'buildCommand' => null,
            ],
        ]);
    }

    public function testDynamicAnalysisFieldRestrictsAccessByProject(): void
    {
        $user = $this->makeNormalUser();
        $this->users[] = $user;

        $project1 = $this->makePrivateProject();
        $project1->users()
            ->attach($user->id, [
                'emailtype' => 0,
                'emailcategory' => 0,
                'emailsuccess' => true,
                'emailmissingsites' => true,
                'role' => Project::PROJECT_ADMIN,
            ]);
        /** @var DynamicAnalysis $da1 */
        $da1 = $project1->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ])->dynamicAnalyses()->create([
            'status' => 'Passed',
            'checker' => Str::uuid()->toString(),
            'name' => Str::uuid()->toString(),
            'path' => Str::uuid()->toString(),
            'fullcommandline' => Str::uuid()->toString(),
            'log' => Str::uuid()->toString(),
        ]);

        $project2 = $this->makePrivateProject();
        /** @var DynamicAnalysis $da2 */
        $da2 = $project2->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ])->dynamicAnalyses()->create([
            'status' => 'Passed',
            'checker' => Str::uuid()->toString(),
            'name' => Str::uuid()->toString(),
            'path' => Str::uuid()->toString(),
            'fullcommandline' => Str::uuid()->toString(),
            'log' => Str::uuid()->toString(),
        ]);

        $this->actingAs($user)->graphQL('
            query($id: ID!) {
                dynamicAnalysis(id: $id) {
                    id
                }
            }
        ', [
            'id' => $da1->id,
        ])->assertExactJson([
            'data' => [
                'dynamicAnalysis' => [
                    'id' => (string) $da1->id,
                ],
            ],
        ]);

        $this->actingAs($user)->graphQL('
            query($id: ID!) {
                dynamicAnalysis(id: $id) {
                    id
                }
            }
        ', [
            'id' => $da2->id,
        ])->assertExactJson([
            'data' => [
                'dynamicAnalysis' => null,
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
            ['self', false],
            ['admin', true],
        ];
    }

    #[DataProvider('authenticationTokensRelationshipVisibilityCases')]
    public function testAuthenticationTokensRelationshipVisibility(
        ?string $user,
        bool $canSeeAuthToken,
    ): void {
        $tokenOwner = $this->makeNormalUser();
        /** @var AuthToken $authToken */
        $authToken = $tokenOwner->authenticationTokens()->save(AuthToken::factory()->make());

        if ($user === 'normal') {
            $user = $this->makeNormalUser();
        } elseif ($user === 'self') {
            $user = $tokenOwner;
        } elseif ($user === 'admin') {
            $user = $this->makeAdminUser();
        } elseif ($user === null) {
            $user = null;
        } else {
            throw new Exception('Invalid user.');
        }

        $response = ($user === null ? $this : $this->actingAs($user))->graphQL('
            query {
                authenticationTokens {
                    edges {
                        node {
                            id
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
            ]);
        } else {
            $response->assertGraphQLErrorMessage('This action is unauthorized.');
        }
    }

    public function testTestFieldRestrictsAccessByProject(): void
    {
        $user = $this->makeNormalUser();
        $this->users[] = $user;

        $testOutput = TestOutput::create([
            'path' => Str::uuid()->toString(),
            'command' => Str::uuid()->toString(),
            'output' => Str::uuid()->toString(),
        ]);

        $project1 = $this->makePrivateProject();
        $project1->users()
            ->attach($user->id, [
                'role' => Project::PROJECT_ADMIN,
            ]);
        /** @var Test $test1 */
        $test1 = $project1->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ])->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'failed',
            'outputid' => $testOutput->id,
        ]);

        $project2 = $this->makePrivateProject();
        /** @var Test $test2 */
        $test2 = $project2->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ])->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'failed',
            'outputid' => $testOutput->id,
        ]);

        $this->actingAs($user)->graphQL('
            query($id: ID!) {
                test(id: $id) {
                    id
                }
            }
        ', [
            'id' => $test1->id,
        ])->assertExactJson([
            'data' => [
                'test' => [
                    'id' => (string) $test1->id,
                ],
            ],
        ]);

        $this->actingAs($user)->graphQL('
            query($id: ID!) {
                test(id: $id) {
                    id
                }
            }
        ', [
            'id' => $test2->id,
        ])->assertExactJson([
            'data' => [
                'test' => null,
            ],
        ]);
    }
}
