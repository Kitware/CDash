<?php

namespace Tests\Feature\Resources;

use App\Models\Project;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class ProjectResourceTest extends TestCase
{
    use CreatesUsers;
    use CreatesProjects;

    /**
     * @var array<string, Project> $projects
     */
    private array $projects;

    /**
     * @var array<string, User> $users
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

        $this->users = [
            'normal' => $this->makeNormalUser(),
            'admin' => $this->makeAdminUser(),
        ];

        $user2project_data = [
            'cvslogin' => '', // TODO: Delete this. Only here to satisfy the database constraint until the column is removed
            'emailtype' => 0,
            'emailcategory' => 0,
            'emailsuccess' => true,
            'emailmissingsites' => true,
        ];

        $this->projects['public1']
            ->users()
            ->attach($this->users['normal']->id, $user2project_data + ['role' => Project::PROJECT_USER]);

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

    private const PROJECT_ATTRIBUTES = [
        'id',
        'name',
        'description',
        'homeurl',
        'cvsurl',
        'bugtrackerurl',
        'bugtrackerfileurl',
        'bugtrackernewissueurl',
        'bugtrackertype',
        'documentationurl',
        'imageid',
        'public',
        'coveragethreshold',
        'testingdataurl',
        'nightlytime',
        'googletracker',
        'emaillowcoverage',
        'emailtesttimingchanged',
        'emailbrokensubmission',
        'emailredundantfailures',
        'emailadministrator',
        'showipaddresses',
        'cvsviewertype',
        'testtimestd',
        'testtimestdthreshold',
        'showtesttime',
        'testtimemaxstatus',
        'emailmaxitems',
        'emailmaxchars',
        'displaylabels',
        'autoremovetimeframe',
        'autoremovemaxbuilds',
        'uploadquota',
        'showcoveragecode',
        'sharelabelfilters',
        'authenticatesubmissions',
        'viewsubprojectslink',
    ];

    /**
     * @return array{
     *     array{
     *         string|null, array<string>
     *     }
     * }
     */
    public function projectIndexAccess(): array
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
     * @dataProvider projectIndexAccess
     */
    public function testGetIndex(?string $user, array $allowable_projects): void
    {
        $test = ($user === null ? $this : $this->actingAs($this->users[$user]))
            ->getJson('/api/v2/projects')
            ->assertOk()
            ->assertJsonCount(count($allowable_projects), 'projects')
            ->assertJsonStructure([
                'projects' => [
                    '*' => self::PROJECT_ATTRIBUTES,
                ],
            ]);

        for ($i = 0; $i < count($allowable_projects); $i++) {
            $test->assertJson(function (AssertableJson $json) use ($allowable_projects, $i) {
                $json->where("projects.$i.id", $this->projects[$allowable_projects[$i]]->id)
                    ->where("projects.$i.name", $this->projects[$allowable_projects[$i]]->name)
                    ->whereAllType([
                        "projects.$i.id" => 'integer',
                        "projects.$i.name" => 'string',
                        "projects.$i.imageid" => 'integer',
                        "projects.$i.coveragethreshold" => 'integer',
                        // TODO: figure out the rest of the integer & boolean types for the project model
                    ]);
            });
        }
    }

    /**
     * @dataProvider perProjectAccess
     */
    public function testGet(?string $user, string $project, bool $can_access): void
    {
        $test = ($user === null ? $this : $this->actingAs($this->users[$user]))
            ->getJson('/api/v2/projects/' . $this->projects[$project]->id);

        if ($can_access) {
            $test->assertOk()
                ->assertJsonStructure([
                    'project' => self::PROJECT_ATTRIBUTES,
                ])
                ->assertJson(function (AssertableJson $json) use ($project) {
                    $json
                        ->where('project.id', $this->projects[$project]->id)
                        ->where('project.name', $this->projects[$project]->name)
                        ->whereAllType([
                            "project.id" => 'integer',
                            "project.name" => 'string',
                            "project.imageid" => 'integer',
                            "project.coveragethreshold" => 'integer',
                            // TODO: figure out the rest of the integer & boolean types for the project model
                        ]);
                });
        } else {
            $test->assertNotFound();
        }
    }
}
