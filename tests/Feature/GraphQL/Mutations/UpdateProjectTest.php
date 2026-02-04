<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class UpdateProjectTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;
    use DatabaseTransactions;

    public function testCannotUpdateNonExistentProject(): void
    {
        $user = $this->makeAdminUser();
        $this->actingAs($user)->graphQL('
            mutation updateProject($input: UpdateProjectInput!) {
                updateProject(input: $input) {
                    project {
                        name
                    }
                    message
                }
            }
        ', [
            'input' => [
                'id' => 123456789,
                'name' => Str::uuid()->toString(),
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');
    }

    public function testCannotUpdateProjectAsAnonymousUser(): void
    {
        $project = $this->makePublicProject();
        $original_name = $project->name;
        $this->graphQL('
            mutation updateProject($input: UpdateProjectInput!) {
                updateProject(input: $input) {
                    project {
                        name
                    }
                    message
                }
            }
        ', [
            'input' => [
                'id' => $project->id,
                'name' => Str::uuid()->toString(),
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');
        self::assertSame($original_name, $project->fresh()?->name);
    }

    public function testCannotUpdateProjectAsNormalUser(): void
    {
        $project = $this->makePublicProject();
        $original_name = $project->name;
        $user = $this->makeNormalUser();
        $this->actingAs($user)->graphQL('
            mutation updateProject($input: UpdateProjectInput!) {
                updateProject(input: $input) {
                    project {
                        name
                    }
                    message
                }
            }
        ', [
            'input' => [
                'id' => $project->id,
                'name' => Str::uuid()->toString(),
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');
        self::assertSame($original_name, $project->fresh()?->name);
    }

    public function testCannotUpdateProjectAsNormalProjectUser(): void
    {
        $project = $this->makePublicProject();
        $original_name = $project->name;
        $user = $this->makeNormalUser();
        $project->users()->attach($user, ['role' => Project::PROJECT_USER]);

        $this->actingAs($user)->graphQL('
            mutation updateProject($input: UpdateProjectInput!) {
                updateProject(input: $input) {
                    project {
                        name
                    }
                    message
                }
            }
        ', [
            'input' => [
                'id' => $project->id,
                'name' => Str::uuid()->toString(),
            ],
        ])->assertGraphQLErrorMessage('This action is unauthorized.');
        self::assertSame($original_name, $project->fresh()?->name);
    }

    public function testCanUpdateProjectAsProjectAdmin(): void
    {
        $project = $this->makePublicProject();
        $user = $this->makeNormalUser();
        $project->users()->attach($user, ['role' => Project::PROJECT_ADMIN]);

        $name = Str::uuid()->toString();
        $this->actingAs($user)->graphQL('
            mutation updateProject($input: UpdateProjectInput!) {
                updateProject(input: $input) {
                    project {
                        name
                    }
                    message
                }
            }
        ', [
            'input' => [
                'id' => $project->id,
                'name' => $name,
            ],
        ])->assertExactJson([
            'data' => [
                'updateProject' => [
                    'project' => [
                        'name' => $name,
                    ],
                    'message' => null,
                ],
            ],
        ]);
        self::assertSame($name, $project->fresh()?->name);
    }

    public function testCanUpdateProjectAsGlobalAdmin(): void
    {
        $project = $this->makePublicProject();
        $user = $this->makeAdminUser();
        $name = Str::uuid()->toString();

        $this->actingAs($user)->graphQL('
            mutation updateProject($input: UpdateProjectInput!) {
                updateProject(input: $input) {
                    project {
                        name
                    }
                    message
                }
            }
        ', [
            'input' => [
                'id' => $project->id,
                'name' => $name,
            ],
        ])->assertExactJson([
            'data' => [
                'updateProject' => [
                    'project' => [
                        'name' => $name,
                    ],
                    'message' => null,
                ],
            ],
        ]);
        self::assertSame($name, $project->fresh()?->name);
    }

    public static function fieldValues(): array
    {
        return [
            ['description', 'new description', 'description', 'new description'],
            ['homeUrl', 'https://kitware.com', 'homeurl', 'https://kitware.com'],
            ['vcsViewer', 'GITLAB', 'cvsviewertype', 'gitlab'],
            ['vcsUrl', 'https://gitlab.kitware.com/kitware/cdash', 'cvsurl', 'https://gitlab.kitware.com/kitware/cdash'],
            ['bugTracker', 'JIRA', 'bugtrackertype', 'JIRA'],
            ['bugTrackerUrl', 'https://jira.kitware.com', 'bugtrackerurl', 'https://jira.kitware.com'],
            ['bugTrackerNewIssueUrl', 'https://jira.kitware.com/new', 'bugtrackernewissueurl', 'https://jira.kitware.com/new'],
            ['documentationUrl', 'https://cdash.org/documentation', 'documentationurl', 'https://cdash.org/documentation'],
            ['testDataUrl', 'https://cdash.org/test-data', 'testingdataurl', 'https://cdash.org/test-data'],
            ['visibility', 'PROTECTED', 'public', 2],
            ['authenticateSubmissions', true, 'authenticatesubmissions', true],
            ['ldapFilter', '(uid=user)', 'ldapfilter', '(uid=user)'],
            ['coverageThreshold', 90, 'coveragethreshold', 90],
            ['nightlyTime', '01:00:00', 'nightlytime', '01:00:00'],
            ['emailLowCoverage', true, 'emaillowcoverage', true],
            ['emailTestTimingChanged', true, 'emailtesttimingchanged', true],
            ['emailBrokenSubmissions', true, 'emailbrokensubmission', true],
            ['emailRedundantFailures', true, 'emailredundantfailures', true],
            ['testTimeStdMultiplier', 5.0, 'testtimestd', '5.00'],
            ['testTimeStdThreshold', 2.0, 'testtimestdthreshold', '2.00'],
            ['enableTestTiming', false, 'showtesttime', false],
            ['timeStatusFailureThreshold', 3, 'testtimemaxstatus', 3],
            ['emailMaxItems', 20, 'emailmaxitems', 20],
            ['emailMaxCharacters', 2000, 'emailmaxchars', 2000],
            ['displayLabels', false, 'displaylabels', false],
            ['autoRemoveTimeFrame', 10, 'autoremovetimeframe', 10],
            ['autoRemoveMaxBuilds', 20, 'autoremovemaxbuilds', 20],
            ['fileUploadLimit', 50, 'uploadquota', 50],
            ['showCoverageCode', false, 'showcoveragecode', false],
            ['shareLabelFilters', false, 'sharelabelfilters', false],
            ['showViewSubProjectsLink', false, 'viewsubprojectslink', false],
            ['banner', 'new banner', 'banner', 'new banner'],
        ];
    }

    #[DataProvider('fieldValues')]
    public function testUpdateEachField(string $gqlField, mixed $value, string $dbField, mixed $expected): void
    {
        $project = $this->makePublicProject();
        $user = $this->makeAdminUser();

        $this->actingAs($user)->graphQL("
            mutation updateProject(\$input: UpdateProjectInput!) {
                updateProject(input: \$input) {
                    project {
                        {$gqlField}
                    }
                    message
                }
            }
        ", [
            'input' => [
                'id' => $project->id,
                $gqlField => $value,
            ],
        ])->assertExactJson([
            'data' => [
                'updateProject' => [
                    'project' => [
                        $gqlField => $value,
                    ],
                    'message' => null,
                ],
            ],
        ]);

        self::assertSame($expected, $project->fresh()?->getAttribute($dbField));
    }

    public function testCannotChangeNameToExistingProjectName(): void
    {
        $project1 = $this->makePublicProject();
        $original_name = $project1->name;
        $project2 = $this->makePublicProject();
        $user = $this->makeAdminUser();

        $this->actingAs($user)->graphQL('
            mutation updateProject($input: UpdateProjectInput!) {
                updateProject(input: $input) {
                    project {
                        name
                    }
                    message
                }
            }
        ', [
            'input' => [
                'id' => $project1->id,
                'name' => $project2->name,
            ],
        ])->assertGraphQLErrorMessage('Validation failed for the field [updateProject].');
        self::assertSame($original_name, $project1->fresh()?->name);
    }

    public function testCannotChangeNameToInvalidName(): void
    {
        $project = $this->makePublicProject();
        $original_name = $project->name;
        $user = $this->makeAdminUser();

        $this->actingAs($user)->graphQL('
            mutation updateProject($input: UpdateProjectInput!) {
                updateProject(input: $input) {
                    project {
                        name
                    }
                    message
                }
            }
        ', [
            'input' => [
                'id' => $project->id,
                'name' => 'invalid name %',
            ],
        ])->assertGraphQLErrorMessage('Validation failed for the field [updateProject].');
        self::assertSame($original_name, $project->fresh()?->name);
    }

    public static function visibilityRules(): array
    {
        return [
            ['normal', 'PUBLIC', 'PUBLIC', false],
            ['normal', 'PROTECTED', 'PUBLIC', false],
            ['normal', 'PRIVATE', 'PUBLIC', false],
            ['project_admin', 'PUBLIC', 'PUBLIC', true],
            ['project_admin', 'PROTECTED', 'PUBLIC', true],
            ['project_admin', 'PRIVATE', 'PUBLIC', true],
            ['admin', 'PUBLIC', 'PUBLIC', true],
            ['admin', 'PROTECTED', 'PUBLIC', true],
            ['admin', 'PRIVATE', 'PUBLIC', true],
        ];
    }

    #[DataProvider('visibilityRules')]
    public function testVisibilityRules(string $userRole, string $newVisibility, string $maxVisibility, bool $shouldSucceed): void
    {
        Config::set('cdash.user_create_projects', true);
        Config::set('cdash.max_project_visibility', $maxVisibility);

        $project = $this->makePublicProject();

        $user = null;
        if ($userRole === 'normal') {
            $user = $this->makeNormalUser();
        } elseif ($userRole === 'admin') {
            $user = $this->makeAdminUser();
        } elseif ($userRole === 'project_admin') {
            $user = $this->makeNormalUser();
            $project->users()->attach($user, ['role' => Project::PROJECT_ADMIN]);
        }

        $response = $this->actingAs($user)->graphQL('
            mutation updateProject($input: UpdateProjectInput!) {
                updateProject(input: $input) {
                    project {
                        visibility
                    }
                    message
                }
            }
        ', [
            'input' => [
                'id' => $project->id,
                'visibility' => $newVisibility,
            ],
        ]);

        if ($shouldSucceed) {
            $response->assertExactJson([
                'data' => [
                    'updateProject' => [
                        'project' => [
                            'visibility' => $newVisibility,
                        ],
                        'message' => null,
                    ],
                ],
            ]);
        } else {
            $response->assertGraphQLErrorMessage('This action is unauthorized.');
        }
    }

    public static function authenticatedSubmissionRules(): array
    {
        return [
            ['project_admin', true, false, true],
            ['project_admin', false, true, false],
            ['admin', true, false, true],
            ['admin', false, true, true],
        ];
    }

    #[DataProvider('authenticatedSubmissionRules')]
    public function testAuthenticatedSubmissionRules(string $userRole, bool $newAuthValue, bool $configAuthValue, bool $shouldSucceed): void
    {
        Config::set('cdash.user_create_projects', true);
        Config::set('cdash.require_authenticated_submissions', $configAuthValue);

        $project = $this->makePublicProject();

        $user = null;
        if ($userRole === 'admin') {
            $user = $this->makeAdminUser();
        } elseif ($userRole === 'project_admin') {
            $user = $this->makeNormalUser();
            $project->users()->attach($user, ['role' => Project::PROJECT_ADMIN]);
        }

        $response = $this->actingAs($user)->graphQL('
            mutation updateProject($input: UpdateProjectInput!) {
                updateProject(input: $input) {
                    project {
                        authenticateSubmissions
                    }
                    message
                }
            }
        ', [
            'input' => [
                'id' => $project->id,
                'authenticateSubmissions' => $newAuthValue,
            ],
        ]);

        if ($shouldSucceed) {
            $response->assertExactJson([
                'data' => [
                    'updateProject' => [
                        'project' => [
                            'authenticateSubmissions' => $newAuthValue,
                        ],
                        'message' => null,
                    ],
                ],
            ]);
            self::assertSame($newAuthValue, $project->fresh()?->authenticatesubmissions);
        } else {
            $response->assertGraphQLErrorMessage('Validation failed for the field [updateProject].');
        }
    }
}
