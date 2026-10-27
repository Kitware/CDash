<?php

namespace Tests\Feature\GraphQL\Queries;

use App\Enums\ProjectRole;
use App\GraphQL\Directives\CountHistogramDirective;
use App\Models\Build;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class ProjectTypeTest extends TestCase
{
    use CreatesProjects;

    use DatabaseTransactions;

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
            'normal' => User::factory()->create(),
            'admin' => User::factory()->adminUser()->create(),
        ];

        $user2project_data = [
            'emailtype' => 0,
            'emailcategory' => 0,
            'emailsuccess' => true,
            'emailmissingsites' => true,
        ];

        $this->projects['public1']
            ->users()
            ->attach($this->users['normal']->id, $user2project_data + ['role' => ProjectRole::ADMINISTRATOR]);

        $this->projects['public1']
            ->users()
            ->attach($this->users['admin']->id, $user2project_data + ['role' => ProjectRole::ADMINISTRATOR]);

        $this->projects['protected2']
            ->users()
            ->attach($this->users['normal']->id, $user2project_data + ['role' => ProjectRole::USER]);

        $this->projects['private1']
            ->users()
            ->attach($this->users['normal']->id, $user2project_data + ['role' => ProjectRole::USER]);

        $this->projects['private2']
            ->users()
            ->attach($this->users['normal']->id, $user2project_data + ['role' => ProjectRole::ADMINISTRATOR]);
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
     * @return array<array{
     *     string, mixed, string, mixed,
     * }>
     */
    public static function fieldValues(): array
    {
        // TODO: once VcsViewer is an enum, get cases dynamically.
        $vcsViewerValues = [
            ['cvsviewertype', 'github', 'vcsViewer', 'GITHUB'],
            ['cvsviewertype', 'gitlab', 'vcsViewer', 'GITLAB'],
            ['cvsviewertype', null, 'vcsViewer', null],
        ];

        // TODO: once BugTracker is an enum, get cases dynamically.
        $bugTrackerValues = [
            ['bugtrackertype', 'GitHub', 'bugTracker', 'GITHUB'],
            ['bugtrackertype', 'Buganizer', 'bugTracker', 'BUGANIZER'],
            ['bugtrackertype', 'JIRA', 'bugTracker', 'JIRA'],
            ['bugtrackertype', null, 'bugTracker', null],
        ];

        return [
            ['description', 'abc', 'description', 'abc'],
            ['description', null, 'description', null],
            ['contact_info', 'maintainers@example.com', 'contactInfo', 'maintainers@example.com'],
            ['contact_info', null, 'contactInfo', null],
            ['homeurl', 'cdash.org', 'homeUrl', 'cdash.org'],
            ['homeurl', 'https://cdash.org', 'homeUrl', 'https://cdash.org'],
            ['homeurl', null, 'homeUrl', null],
            ...$vcsViewerValues,
            ['cvsurl', 'https://github.com/Kitware/CDash', 'vcsUrl', 'https://github.com/Kitware/CDash'],
            ['cvsurl', null, 'vcsUrl', null],
            ['cmakeprojectroot', '/abc', 'cmakeProjectRoot', '/abc'],
            ['cmakeprojectroot', null, 'cmakeProjectRoot', null],
            ...$bugTrackerValues,
            ['bugtrackerurl', 'https://github.com/Kitware/CDash/issues', 'bugTrackerUrl', 'https://github.com/Kitware/CDash/issues'],
            ['bugtrackerurl', null, 'bugTrackerUrl', null],
            ['bugtrackernewissueurl', 'https://github.com/Kitware/CDash/issues/new', 'bugTrackerNewIssueUrl', 'https://github.com/Kitware/CDash/issues/new'],
            ['bugtrackernewissueurl', null, 'bugTrackerNewIssueUrl', null],
            ['documentationurl', 'https://cmake.org/cmake/help/latest', 'documentationUrl', 'https://cmake.org/cmake/help/latest'],
            ['documentationurl', null, 'documentationUrl', null],
            ['securitypolicyurl', 'https://github.com/Kitware/CDash/security/policy', 'securityPolicyUrl', 'https://github.com/Kitware/CDash/security/policy'],
            ['securitypolicyurl', null, 'securityPolicyUrl', null],
            ['testingdataurl', 'https://example.com', 'testDataUrl', 'https://example.com'],
            ['testingdataurl', null, 'testDataUrl', null],
            ['authenticatesubmissions', true, 'authenticateSubmissions', true],
            ['authenticatesubmissions', false, 'authenticateSubmissions', false],
            ['ldapfilter', '(uid=*group_1*)', 'ldapFilter', '(uid=*group_1*)'],
            ['ldapfilter', null, 'ldapFilter', null],
            ['coveragethreshold', 80, 'coverageThreshold', 80],
            ['nightlytime', '00:00:00', 'nightlyTime', '00:00:00'],
            ['nightlytime', '02:00:00 UTC', 'nightlyTime', '02:00:00 UTC'],
            ['nightlytime', '22:00:00 EST', 'nightlyTime', '22:00:00 EST'],
            ['nightlytime', '00:00:00 America/New_York', 'nightlyTime', '00:00:00 America/New_York'],
            ['emaillowcoverage', true, 'emailLowCoverage', true],
            ['emaillowcoverage', false, 'emailLowCoverage', false],
            ['emailtesttimingchanged', true, 'emailTestTimingChanged', true],
            ['emailtesttimingchanged', false, 'emailTestTimingChanged', false],
            ['emailbrokensubmission', true, 'emailBrokenSubmissions', true],
            ['emailbrokensubmission', false, 'emailBrokenSubmissions', false],
            ['emailredundantfailures', true, 'emailRedundantFailures', true],
            ['emailredundantfailures', false, 'emailRedundantFailures', false],
            ['testtimestd', 10, 'testTimeStdMultiplier', 10],
            ['testtimestdthreshold', 10, 'testTimeStdThreshold', 10],
            ['showtesttime', true, 'enableTestTiming', true],
            ['showtesttime', false, 'enableTestTiming', false],
            ['testtimemaxstatus', 10, 'timeStatusFailureThreshold', 10],
            ['emailmaxitems', 10, 'emailMaxItems', 10],
            ['emailmaxchars', 10, 'emailMaxCharacters', 10],
            ['displaylabels', true, 'displayLabels', true],
            ['displaylabels', false, 'displayLabels', false],
            ['uploadquota', 10737418240, 'fileUploadLimit', 10],
            ['showcoveragecode', true, 'showCoverageCode', true],
            ['showcoveragecode', false, 'showCoverageCode', false],
            ['banner', 'test', 'banner', 'test'],
            ['banner', null, 'banner', null],
        ];
    }

    /**
     * A basic test to ensure that each of the non-relationship fields works
     */
    #[DataProvider('fieldValues')]
    public function testBasicFieldAccess(string $modelField, mixed $modelValue, string $graphqlField, mixed $graphqlValue): void
    {
        $project = $this->makePublicProject();
        $project->setAttribute($modelField, $modelValue);
        $project->save();
        $this->projects['test'] = $project;

        $this->graphQL("
            query project(\$id: ID) {
                project(id: \$id) {
                    $graphqlField
                }
            }
        ", [
            'id' => $project->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    $graphqlField => $graphqlValue,
                ],
            ],
        ]);
    }

    /**
     * @return array<array{
     *     string|null, array<string>,
     * }>
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
     * @return array<array{
     *     string|null, string, bool,
     * }>
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

    public function testBuildsOnlyParentsArgument(): void
    {
        // Create two parent builds
        $this->projects['public1']->builds()->create([
            'name' => 'parent1',
            'uuid' => Str::uuid(),
        ]);
        $this->projects['public1']->builds()->create([
            'name' => 'parent2',
            'uuid' => Str::uuid(),
        ]);

        // Create a parent build that will serve as the "real" parent for child builds
        $parent = $this->projects['public1']->builds()->create([
            'name' => 'parent3',
            'uuid' => Str::uuid(),
        ]);

        // Create a child build
        $this->projects['public1']->builds()->create([
            'name' => 'child1',
            'parentid' => $parent->id,
            'uuid' => Str::uuid(),
        ]);

        // Default behavior: only parent builds should be returned
        $this->graphQL('
            query($projectId: ID!) {
                project(id: $projectId) {
                    builds {
                        edges {
                            node {
                                name
                            }
                        }
                    }
                }
            }
        ', [
            'projectId' => $this->projects['public1']->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'builds' => [
                        'edges' => [
                            ['node' => ['name' => 'parent1']],
                            ['node' => ['name' => 'parent2']],
                            ['node' => ['name' => 'parent3']],
                        ],
                    ],
                ],
            ],
        ]);

        // Explicit onlyParents: true: same as default
        $this->graphQL('
            query($projectId: ID!) {
                project(id: $projectId) {
                    builds(onlyParents: true) {
                        edges {
                            node {
                                name
                            }
                        }
                    }
                }
            }
        ', [
            'projectId' => $this->projects['public1']->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'builds' => [
                        'edges' => [
                            ['node' => ['name' => 'parent1']],
                            ['node' => ['name' => 'parent2']],
                            ['node' => ['name' => 'parent3']],
                        ],
                    ],
                ],
            ],
        ]);

        // onlyParents: false: all builds including child builds are returned
        $this->graphQL('
            query($projectId: ID!) {
                project(id: $projectId) {
                    builds(onlyParents: false) {
                        edges {
                            node {
                                name
                            }
                        }
                    }
                }
            }
        ', [
            'projectId' => $this->projects['public1']->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'builds' => [
                        'edges' => [
                            ['node' => ['name' => 'parent1']],
                            ['node' => ['name' => 'parent2']],
                            ['node' => ['name' => 'parent3']],
                            ['node' => ['name' => 'child1']],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Tests that the buildCount field respects the onlyParents argument.
     */
    public function testBuildCountOnlyParentsArgument(): void
    {
        // Create two parent builds
        $this->projects['public1']->builds()->create([
            'name' => 'parent1',
            'uuid' => Str::uuid(),
        ]);
        $this->projects['public1']->builds()->create([
            'name' => 'parent2',
            'uuid' => Str::uuid(),
        ]);

        // Create a parent build that will serve as the "real" parent for child builds
        $parent = $this->projects['public1']->builds()->create([
            'name' => 'parent3',
            'uuid' => Str::uuid(),
        ]);

        // Create a child build
        $this->projects['public1']->builds()->create([
            'name' => 'child1',
            'parentid' => $parent->id,
            'uuid' => Str::uuid(),
        ]);

        // Default behavior: only parent builds counted
        $this->graphQL('
            query($projectId: ID!) {
                project(id: $projectId) {
                    buildCount
                }
            }
        ', [
            'projectId' => $this->projects['public1']->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'buildCount' => 3,
                ],
            ],
        ]);

        // Explicit onlyParents: true: same as default
        $this->graphQL('
            query($projectId: ID!) {
                project(id: $projectId) {
                    buildCount(onlyParents: true)
                }
            }
        ', [
            'projectId' => $this->projects['public1']->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'buildCount' => 3,
                ],
            ],
        ]);

        // onlyParents: false: all builds counted including child builds
        $this->graphQL('
            query($projectId: ID!) {
                project(id: $projectId) {
                    buildCount(onlyParents: false)
                }
            }
        ', ['projectId' => $this->projects['public1']->id])->assertExactJson([
            'data' => [
                'project' => [
                    'buildCount' => 4,
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

    public function testTestsRelationship(): void
    {
        // First build with two tests.
        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid(),
        ])->tests()->createMany([
            [
                'testname' => 'test1',
                'status' => 'passed',
            ],
            [
                'testname' => 'test2',
                'status' => 'failed',
            ],
        ]);

        // Second build with a single test.
        $this->projects['public1']->builds()->create([
            'name' => 'build2',
            'uuid' => Str::uuid(),
        ])->tests()->create([
            'testname' => 'test3',
            'status' => 'notrun',
        ]);

        // A test belonging to a different project should not be returned.
        $this->projects['public2']->builds()->create([
            'name' => 'build3',
            'uuid' => Str::uuid(),
        ])->tests()->create([
            'testname' => 'test4',
            'status' => 'passed',
        ]);

        $this->graphQL('
            query($projectId: ID!) {
                project(id: $projectId) {
                    tests {
                        edges {
                            node {
                                name
                                status
                            }
                        }
                    }
                }
            }
        ', [
            'projectId' => $this->projects['public1']->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'tests' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'test1',
                                    'status' => 'PASSED',
                                ],
                            ],
                            [
                                'node' => [
                                    'name' => 'test2',
                                    'status' => 'FAILED',
                                ],
                            ],
                            [
                                'node' => [
                                    'name' => 'test3',
                                    'status' => 'NOT_RUN',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Tests that a project with no builds (and therefore no tests) returns an
     * empty tests relationship.
     */
    public function testTestsRelationshipEmpty(): void
    {
        $this->graphQL('
            query($projectId: ID!) {
                project(id: $projectId) {
                    tests {
                        edges {
                            node {
                                name
                            }
                        }
                    }
                }
            }
        ', [
            'projectId' => $this->projects['public1']->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'tests' => [
                        'edges' => [],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Tests that the project's tests relationship can be filtered.
     */
    public function testTestsRelationshipWithFilters(): void
    {
        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid(),
        ])->tests()->createMany([
            [
                'testname' => 'passing_test',
                'status' => 'passed',
            ],
            [
                'testname' => 'failing_test',
                'status' => 'failed',
            ],
        ]);

        $this->graphQL('
            query($projectId: ID!) {
                project(id: $projectId) {
                    tests(filters: { eq: { status: FAILED } }) {
                        edges {
                            node {
                                name
                                status
                            }
                        }
                    }
                }
            }
        ', [
            'projectId' => $this->projects['public1']->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'tests' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'failing_test',
                                    'status' => 'FAILED',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private function createBuild(Project $project, string $starttime, array $attributes = []): Build
    {
        return $project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'starttime' => $starttime,
            ...$attributes,
        ]);
    }

    /**
     * @param array<string,mixed> $arguments
     *
     * @return TestResponse<Response>
     */
    private function queryBuildCountHistogram(array $arguments): TestResponse
    {
        return $this->graphQL('
            query(
                $projectId: ID!
                $filters: ProjectBuildCountHistogramFiltersMultiFilterInput
                $onlyParents: Boolean
                $interval: TimeInterval!
                $by: BuildTimeField
                $timezone: String
                $dayStart: Time
            ) {
                project(id: $projectId) {
                    buildCountHistogram(
                        filters: $filters
                        onlyParents: $onlyParents
                        interval: $interval
                        by: $by
                        timezone: $timezone
                        dayStart: $dayStart
                    ) {
                        start
                        count
                    }
                }
            }
        ', [
            'projectId' => $this->projects['public1']->id,
            ...$arguments,
        ]);
    }

    public function testBuildCountHistogramOnlyReturnsIntervalsWithBuilds(): void
    {
        $this->createBuild($this->projects['public1'], '2026-01-01 00:00:00');
        $this->createBuild($this->projects['public1'], '2026-01-01 23:59:59');
        $this->createBuild($this->projects['public1'], '2026-01-03 12:00:00');

        // Builds from other projects are not counted.
        $this->createBuild($this->projects['public2'], '2026-01-02 12:00:00');

        $this->queryBuildCountHistogram(['interval' => 'DAY'])->assertExactJson([
            'data' => [
                'project' => [
                    'buildCountHistogram' => [
                        ['start' => '2026-01-01T00:00:00+00:00', 'count' => 2],
                        ['start' => '2026-01-03T00:00:00+00:00', 'count' => 1],
                    ],
                ],
            ],
        ]);
    }

    public function testBuildCountHistogramWithNoBuilds(): void
    {
        $this->queryBuildCountHistogram(['interval' => 'DAY'])->assertExactJson([
            'data' => [
                'project' => [
                    'buildCountHistogram' => [],
                ],
            ],
        ]);
    }

    /**
     * @return array<string,array{string, array<array{start: string, count: int}>}>
     */
    public static function histogramIntervals(): array
    {
        return [
            'hour' => ['HOUR', [
                ['start' => '2026-03-15T23:00:00+00:00', 'count' => 1],
                ['start' => '2026-03-16T10:00:00+00:00', 'count' => 2],
                ['start' => '2026-04-02T08:00:00+00:00', 'count' => 1],
                ['start' => '2027-01-01T00:00:00+00:00', 'count' => 1],
            ]],
            'day' => ['DAY', [
                ['start' => '2026-03-15T00:00:00+00:00', 'count' => 1],
                ['start' => '2026-03-16T00:00:00+00:00', 'count' => 2],
                ['start' => '2026-04-02T00:00:00+00:00', 'count' => 1],
                ['start' => '2027-01-01T00:00:00+00:00', 'count' => 1],
            ]],
            // Weeks start on Monday, so the Sunday build belongs to the previous week.
            'week' => ['WEEK', [
                ['start' => '2026-03-09T00:00:00+00:00', 'count' => 1],
                ['start' => '2026-03-16T00:00:00+00:00', 'count' => 2],
                ['start' => '2026-03-30T00:00:00+00:00', 'count' => 1],
                ['start' => '2026-12-28T00:00:00+00:00', 'count' => 1],
            ]],
            'month' => ['MONTH', [
                ['start' => '2026-03-01T00:00:00+00:00', 'count' => 3],
                ['start' => '2026-04-01T00:00:00+00:00', 'count' => 1],
                ['start' => '2027-01-01T00:00:00+00:00', 'count' => 1],
            ]],
            'year' => ['YEAR', [
                ['start' => '2026-01-01T00:00:00+00:00', 'count' => 4],
                ['start' => '2027-01-01T00:00:00+00:00', 'count' => 1],
            ]],
        ];
    }

    /**
     * @param array<array{start: string, count: int}> $expected
     */
    #[DataProvider('histogramIntervals')]
    public function testBuildCountHistogramIntervals(string $interval, array $expected): void
    {
        $this->createBuild($this->projects['public1'], '2026-03-15 23:30:00'); // Sunday
        $this->createBuild($this->projects['public1'], '2026-03-16 10:15:00'); // Monday
        $this->createBuild($this->projects['public1'], '2026-03-16 10:45:00'); // Monday
        $this->createBuild($this->projects['public1'], '2026-04-02 08:00:00'); // Thursday
        $this->createBuild($this->projects['public1'], '2027-01-01 00:00:00'); // Friday

        $this->queryBuildCountHistogram(['interval' => $interval])->assertExactJson([
            'data' => [
                'project' => [
                    'buildCountHistogram' => $expected,
                ],
            ],
        ]);
    }

    /**
     * @return array<array{string, string, string}>
     */
    public static function histogramTimezones(): array
    {
        return [
            ['UTC', 'DAY', '2026-03-10T00:00:00+00:00'],
            ['UTC', 'HOUR', '2026-03-10T02:00:00+00:00'],
            ['America/New_York', 'DAY', '2026-03-09T00:00:00-04:00'],
            // Daylight saving time started on 2026-03-08, so the start of the month has a different offset.
            ['America/New_York', 'MONTH', '2026-03-01T00:00:00-05:00'],
            ['Asia/Kolkata', 'HOUR', '2026-03-10T08:00:00+05:30'],
        ];
    }

    #[DataProvider('histogramTimezones')]
    public function testBuildCountHistogramTimezones(string $timezone, string $interval, string $expectedStart): void
    {
        // 2026-03-09 22:45 in New York, and 2026-03-10 08:15 in Kolkata.
        $this->createBuild($this->projects['public1'], '2026-03-10 02:45:00');

        $this->queryBuildCountHistogram([
            'interval' => $interval,
            'timezone' => $timezone,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'buildCountHistogram' => [
                        ['start' => $expectedStart, 'count' => 1],
                    ],
                ],
            ],
        ]);
    }

    public function testBuildCountHistogramDayStart(): void
    {
        // Evening day starts, like a nightly start time of 21:00.  Daylight saving time started on
        // 2026-03-08, so the second day starts at a different UTC offset.
        $this->createBuild($this->projects['public1'], '2026-03-09 00:59:59'); // 2026-03-08 20:59:59 in New York
        $this->createBuild($this->projects['public1'], '2026-03-09 01:00:00'); // 2026-03-08 21:00:00 in New York

        $this->queryBuildCountHistogram([
            'interval' => 'DAY',
            'timezone' => 'America/New_York',
            'dayStart' => '21:00:00',
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'buildCountHistogram' => [
                        ['start' => '2026-03-07T21:00:00-05:00', 'count' => 1],
                        ['start' => '2026-03-08T21:00:00-04:00', 'count' => 1],
                    ],
                ],
            ],
        ]);

        // Morning day starts.
        $this->createBuild($this->projects['public2'], '2026-01-02 00:59:59');
        $this->createBuild($this->projects['public2'], '2026-01-02 01:00:00');

        $this->queryBuildCountHistogram([
            'projectId' => $this->projects['public2']->id,
            'interval' => 'DAY',
            'dayStart' => '01:00:00',
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'buildCountHistogram' => [
                        ['start' => '2026-01-01T01:00:00+00:00', 'count' => 1],
                        ['start' => '2026-01-02T01:00:00+00:00', 'count' => 1],
                    ],
                ],
            ],
        ]);

        // Nightly start times may include a time zone, which must be passed separately.
        $this->queryBuildCountHistogram([
            'interval' => 'DAY',
            'dayStart' => '21:00:00 America/New_York',
        ])->assertGraphQLValidationKeys(['dayStart']);
    }

    public function testBuildCountHistogramHoursAtEndOfDaylightSavingTime(): void
    {
        // Daylight saving time ended on 2026-11-01, so 01:00 in New York happened twice.
        $this->createBuild($this->projects['public1'], '2026-11-01 05:30:00');
        $this->createBuild($this->projects['public1'], '2026-11-01 06:30:00');

        $this->queryBuildCountHistogram([
            'interval' => 'HOUR',
            'timezone' => 'America/New_York',
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'buildCountHistogram' => [
                        ['start' => '2026-11-01T01:00:00-04:00', 'count' => 1],
                        ['start' => '2026-11-01T01:00:00-05:00', 'count' => 1],
                    ],
                ],
            ],
        ]);
    }

    public function testBuildCountHistogramRejectsInvalidTimezone(): void
    {
        $this->queryBuildCountHistogram([
            'interval' => 'DAY',
            'timezone' => 'Not/A_Timezone',
        ])->assertGraphQLValidationKeys(['timezone']);
    }

    /**
     * @return array<array{string|null, string}>
     */
    public static function histogramTimeFields(): array
    {
        return [
            [null, '2026-01-01T00:00:00+00:00'],
            ['START_TIME', '2026-01-01T00:00:00+00:00'],
            ['SUBMISSION_TIME', '2026-02-01T00:00:00+00:00'],
            ['END_TIME', '2026-03-01T00:00:00+00:00'],
        ];
    }

    #[DataProvider('histogramTimeFields')]
    public function testBuildCountHistogramByTimeField(?string $by, string $expectedStart): void
    {
        $this->createBuild($this->projects['public1'], '2026-01-01 12:00:00', [
            'submittime' => '2026-02-01 12:00:00',
            'endtime' => '2026-03-01 12:00:00',
        ]);

        $arguments = ['interval' => 'MONTH'];
        if ($by !== null) {
            $arguments['by'] = $by;
        }

        $this->queryBuildCountHistogram($arguments)->assertExactJson([
            'data' => [
                'project' => [
                    'buildCountHistogram' => [
                        ['start' => $expectedStart, 'count' => 1],
                    ],
                ],
            ],
        ]);
    }

    public function testBuildCountHistogramOnlyParentsArgument(): void
    {
        $parent = $this->createBuild($this->projects['public1'], '2026-01-01 10:00:00');
        $this->createBuild($this->projects['public1'], '2026-01-01 11:00:00', [
            'parentid' => $parent->id,
        ]);

        $this->queryBuildCountHistogram(['interval' => 'DAY'])->assertExactJson([
            'data' => [
                'project' => [
                    'buildCountHistogram' => [
                        ['start' => '2026-01-01T00:00:00+00:00', 'count' => 1],
                    ],
                ],
            ],
        ]);

        $this->queryBuildCountHistogram([
            'interval' => 'DAY',
            'onlyParents' => false,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'buildCountHistogram' => [
                        ['start' => '2026-01-01T00:00:00+00:00', 'count' => 2],
                    ],
                ],
            ],
        ]);
    }

    public function testBuildCountHistogramWithFilters(): void
    {
        $this->createBuild($this->projects['public1'], '2026-01-01 12:00:00', ['name' => 'a']);
        $this->createBuild($this->projects['public1'], '2026-01-01 12:00:00', ['name' => 'b']);
        $this->createBuild($this->projects['public1'], '2026-01-02 12:00:00', ['name' => 'a']);

        $this->queryBuildCountHistogram([
            'interval' => 'DAY',
            'filters' => [
                'eq' => [
                    'name' => 'a',
                ],
            ],
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'buildCountHistogram' => [
                        ['start' => '2026-01-01T00:00:00+00:00', 'count' => 1],
                        ['start' => '2026-01-02T00:00:00+00:00', 'count' => 1],
                    ],
                ],
            ],
        ]);

        // The time window is selected with filters.
        $this->queryBuildCountHistogram([
            'interval' => 'DAY',
            'filters' => [
                'all' => [
                    ['eq' => ['name' => 'a']],
                    ['ge' => ['startTime' => '2026-01-02T00:00:00+00:00']],
                ],
            ],
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'buildCountHistogram' => [
                        ['start' => '2026-01-02T00:00:00+00:00', 'count' => 1],
                    ],
                ],
            ],
        ]);
    }

    public function testBuildCountHistogramMaximumIntervals(): void
    {
        $start = Carbon::parse('2026-01-01 00:00:00');
        $builds = [];
        for ($i = 0; $i < CountHistogramDirective::MAX_BUCKETS; $i++) {
            $builds[] = [
                'projectid' => $this->projects['public1']->id,
                'name' => 'build',
                'uuid' => Str::uuid()->toString(),
                'starttime' => $start->copy()->addHours($i)->toDateTimeString(),
            ];
        }
        DB::table('build')->insert($builds);

        $histogram = $this->queryBuildCountHistogram(['interval' => 'HOUR'])
            ->assertGraphQLErrorFree()
            ->json('data.project.buildCountHistogram');
        self::assertIsArray($histogram);
        self::assertCount(CountHistogramDirective::MAX_BUCKETS, $histogram);

        $this->createBuild(
            $this->projects['public1'],
            $start->copy()->addHours(CountHistogramDirective::MAX_BUCKETS)->toDateTimeString(),
        );

        $this->queryBuildCountHistogram(['interval' => 'HOUR'])
            ->assertGraphQLErrorMessage('More than ' . CountHistogramDirective::MAX_BUCKETS . ' intervals matched.  Narrow the filters or choose a longer interval.');

        // A longer interval reduces the number of intervals below the limit.
        $numBuilds = CountHistogramDirective::MAX_BUCKETS + 1;
        $histogram = $this->queryBuildCountHistogram(['interval' => 'DAY'])
            ->assertGraphQLErrorFree()
            ->json('data.project.buildCountHistogram');
        self::assertIsArray($histogram);
        self::assertCount((int) ceil($numBuilds / 24), $histogram);
        self::assertSame(['start' => '2026-01-01T00:00:00+00:00', 'count' => 24], $histogram[0]);
        self::assertSame($numBuilds, array_sum(array_column($histogram, 'count')));
    }
}
