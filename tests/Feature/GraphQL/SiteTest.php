<?php

namespace Tests\Feature\GraphQL;

use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;
use Tests\Traits\CreatesUsers;

class SiteTest extends TestCase
{
    use CreatesUsers;
    use CreatesProjects;
    use CreatesSites;

    /**
     * @var array<Project> $projects
     */
    private array $projects;

    /**
     * @var array<User> $users
     */
    private array $users;

    /**
     * @var array<Site> $sites
     */
    private array $sites = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->projects = [
            'public1' => Project::findOrFail((int) $this->makePublicProject()->Id),
            'private1' => Project::findOrFail((int) $this->makePrivateProject()->Id),
        ];

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
            ->attach($this->users['normal']->id, $user2project_data + ['role' => Project::PROJECT_USER]);

        $this->projects['private1']
            ->users()
            ->attach($this->users['normal']->id, $user2project_data + ['role' => Project::PROJECT_USER]);
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

        foreach ($this->sites as $site) {
            $site->delete();
        }
        $this->users = [];

        parent::tearDown();
    }

    public function testSiteBuildRelationship(): void
    {
        $this->sites['site1'] = $this->makeSite();

        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['site1']->id,
        ]);

        $this->graphQL('
            query {
                projects {
                    builds {
                        site {
                            name
                        }
                    }
                }
            }
        ')->assertJson([
            'data' => [
                'projects' => [
                    [
                        'builds' => [
                            [
                                'site' => [
                                    'name' => $this->sites['site1']->name,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], true);
    }

    /**
     * Users can see sites which have submitted a build to any project they can see.
     */
    public function testSiteVisibility(): void
    {
        // No usages
        $this->sites['unused'] = $this->makeSite([
            'name' => 'unused',
        ]);

        // Only submits to public projects
        $this->sites['public_submission'] = $this->makeSite([
            'name' => 'public_submission',
        ]);

        // Only submits to private projects
        $this->sites['private_submission'] = $this->makeSite([
            'name' => 'private_submission',
        ]);

        // Submits to both public and private projects
        $this->sites['public_private_submission'] = $this->makeSite([
            'name' => 'public_private_submission',
        ]);

        // Add a few builds
        $this->projects['public1']->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['public_submission']->id,
        ]);
        $this->projects['public1']->builds()->create([
            'name' => 'build2',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['public_private_submission']->id,
        ]);
        $this->projects['private1']->builds()->create([
            'name' => 'build3',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['public_private_submission']->id,
        ]);
        $this->projects['private1']->builds()->create([
            'name' => 'build4',
            'uuid' => Str::uuid(),
            'siteid' => $this->sites['private_submission']->id,
        ]);

        $this->graphQL('
            query {
                projects {
                    name
                    sites {
                        name
                    }
                }
            }
        ')->assertJson([
            'data' => [
                'projects' => [
                    [
                        'name' => $this->projects['public1']->name,
                        'sites' => [
                            [
                                'name' => $this->sites['public_submission']->name,
                            ],
                            [
                                'name' => $this->sites['public_private_submission']->name,
                            ],
                        ],
                    ],
                ],
            ],
        ], true);

        $this->actingAs($this->users['normal'])->graphQL('
            query {
                projects {
                    name
                    sites {
                        name
                    }
                }
            }
        ')->assertJson([
            'data' => [
                'projects' => [
                    [
                        'name' => $this->projects['public1']->name,
                        'sites' => [
                            [
                                'name' => $this->sites['public_submission']->name,
                            ],
                            [
                                'name' => $this->sites['public_private_submission']->name,
                            ],
                        ],
                    ],
                ],
            ],
        ], true);

        $this->actingAs($this->users['admin'])->graphQL('
            query {
                projects {
                    name
                    sites {
                        name
                    }
                }
            }
        ')->assertJson([
            'data' => [
                'projects' => [
                    [
                        'name' => $this->projects['public1']->name,
                        'sites' => [
                            [
                                'name' => $this->sites['public_submission']->name,
                            ],
                            [
                                'name' => $this->sites['public_private_submission']->name,
                            ],
                        ],
                    ],
                    [
                        'name' => $this->projects['private1']->name,
                        'sites' => [
                            [
                                'name' => $this->sites['private_submission']->name,
                            ],
                            [
                                'name' => $this->sites['public_private_submission']->name,
                            ],
                        ],
                    ],
                ],
            ],
        ], true);
    }
}
