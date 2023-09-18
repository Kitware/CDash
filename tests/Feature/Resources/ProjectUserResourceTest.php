<?php

namespace Tests\Feature\Resources;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class ProjectUserResourceTest extends TestCase
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

    private const user2project_data = [
        'emailtype' => 0,
        'emailcategory' => 0,
        'emailsuccess' => true,
        'emailmissingsites' => true,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->projects = [
            'project1' => Project::findOrFail((int) $this->makePublicProject()->Id),
            'project2' => Project::findOrFail((int) $this->makePublicProject()->Id),
        ];

        $this->users = [
            'normal_no_projects' => $this->makeNormalUser(),
            'normal_project1' => $this->makeNormalUser(),
            'normal_both' => $this->makeNormalUser(),
            'project_admin_project1' => $this->makeNormalUser(),
            'project_admin_project1_normal_project2' => $this->makeNormalUser(),
            'admin_no_projects' => $this->makeAdminUser(),
            'admin_normal_project1' => $this->makeAdminUser(),
        ];

        // Delete any preexisting relationship data before we begin.
        // Admin users are always added to projects when they are created, so any admin users will exist here...
        DB::table('user2project')->truncate();

        $this->projects['project1']
            ->users()
            ->attach($this->users['normal_project1']->id, self::user2project_data + ['role' => Project::PROJECT_USER, 'cvslogin' => '']);

        $this->projects['project1']
            ->users()
            ->attach($this->users['normal_both']->id, self::user2project_data + ['role' => Project::PROJECT_USER, 'cvslogin' => '']);
        $this->projects['project2']
            ->users()
            ->attach($this->users['normal_both']->id, self::user2project_data + ['role' => Project::PROJECT_USER, 'cvslogin' => '']);

        $this->projects['project1']
            ->users()
            ->attach($this->users['project_admin_project1']->id, self::user2project_data + ['role' => Project::PROJECT_ADMIN, 'cvslogin' => '']);

        $this->projects['project1']
            ->users()
            ->attach($this->users['project_admin_project1_normal_project2']->id, self::user2project_data + ['role' => Project::PROJECT_ADMIN, 'cvslogin' => '']);
        $this->projects['project2']
            ->users()
            ->attach($this->users['project_admin_project1_normal_project2']->id, self::user2project_data + ['role' => Project::PROJECT_USER, 'cvslogin' => '']);

        $this->projects['project1']
            ->users()
            ->attach($this->users['admin_normal_project1']->id, self::user2project_data + ['role' => Project::PROJECT_USER, 'cvslogin' => '']);

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

    public function testIndexProject1(): void
    {
        $this->get("/api/v2/projects/{$this->projects['project1']->id}/users")
            ->assertOk()
            ->assertJson([
                'users' => [
                    [
                        'id' => $this->users['normal_project1']->id,
                        'role' => Project::PROJECT_USER,
                    ] + self::user2project_data,
                    [
                        'id' => $this->users['normal_both']->id,
                        'role' => Project::PROJECT_USER,
                    ] + self::user2project_data,
                    [
                        'id' => $this->users['project_admin_project1']->id,
                        'role' => Project::PROJECT_ADMIN,
                    ] + self::user2project_data,
                    [
                        'id' => $this->users['project_admin_project1_normal_project2']->id,
                        'role' => Project::PROJECT_ADMIN,
                    ] + self::user2project_data,
                    [
                        'id' => $this->users['admin_normal_project1']->id,
                        'role' => Project::PROJECT_USER,
                    ] + self::user2project_data,
                ],
            ], true);
    }

    public function testIndexProject2(): void
    {
        $this->get("/api/v2/projects/{$this->projects['project2']->id}/users")
            ->assertOk()
            ->assertJson([
                'users' => [
                    [
                        'id' => $this->users['normal_both']->id,
                        'role' => Project::PROJECT_USER,
                    ] + self::user2project_data,
                    [
                        'id' => $this->users['project_admin_project1_normal_project2']->id,
                        'role' => Project::PROJECT_USER,
                    ] + self::user2project_data,
                ],
            ], true);
    }
}
