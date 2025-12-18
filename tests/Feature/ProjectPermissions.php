<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class ProjectPermissions extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;

    protected Project $public_project;
    protected Project $protected_project;
    protected Project $private_project1;
    protected Project $private_project2;
    protected User $normal_user;
    protected User $admin_user;

    protected function setUp(): void
    {
        parent::setUp();
        // Create some projects.
        $this->public_project = $this->makePublicProject();
        $this->protected_project = $this->makeProtectedProject();
        $this->private_project1 = $this->makePrivateProject();
        $this->private_project2 = $this->makePrivateProject();

        $this->normal_user = $this->makeNormalUser();
        $this->admin_user = $this->makeAdminUser();
    }

    protected function tearDown(): void
    {
        $this->public_project->delete();
        $this->protected_project->delete();
        $this->private_project1->delete();
        $this->private_project2->delete();
        $this->normal_user->delete();
        $this->admin_user->delete();

        parent::tearDown();
    }

    /**
     * Feature test for project permissions (private/protected/public)
     */
    public function testProjectPermissions(): void
    {
        URL::forceRootUrl('http://localhost');

        // Get the missing project response so we can verify that all responses are the same
        $missing_project_response = $this->get('/api/v2/projects/9999999')->json();

        // Verify that we can access the public project.
        $_GET['project'] = $this->public_project->name;
        $response = $this->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => $this->public_project->name,
            'public' => Project::ACCESS_PUBLIC,
        ]);

        // Verify that we cannot access the protected project or the private projects.
        $_GET['project'] = $this->protected_project->name;
        $response = $this->get('/api/v1/index.php');
        $response->assertJson(['requirelogin' => 1]);
        $_GET['project'] = $this->private_project1->name;
        $response = $this->get('/api/v1/index.php');
        $response->assertJson(['requirelogin' => 1]);
        $_GET['project'] = $this->private_project2->name;
        $response = $this->get('/api/v1/index.php');
        $response->assertJson(['requirelogin' => 1]);

        // Verify that we can still access the public project when logged in
        // as this user.
        $_GET['project'] = $this->public_project->name;
        $response = $this->actingAs($this->normal_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => $this->public_project->name,
            'public' => Project::ACCESS_PUBLIC,
        ]);

        // Verify that we can access the protected project when logged in
        // as this user.
        $_GET['project'] = $this->protected_project->name;
        $response = $this->actingAs($this->normal_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => $this->protected_project->name,
            'public' => Project::ACCESS_PROTECTED,
        ]);

        // Add the user to PrivateProject1.
        DB::table('user2project')->insert([
            'userid' => $this->normal_user->id,
            'projectid' => $this->private_project1->id,
            'role' => 0,
            'emailtype' => 0,
            'emailcategory' => 0,
            'emailsuccess' => 0,
            'emailmissingsites' => 0,
        ]);

        // Verify that she can access it.
        $_GET['project'] = $this->private_project1->name;
        $response = $this->actingAs($this->normal_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => $this->private_project1->name,
            'public' => Project::ACCESS_PRIVATE,
        ]);

        // Verify that she cannot access PrivateProject2.
        $_GET['project'] = $this->private_project2->name;
        $response = $this->actingAs($this->normal_user)->get('/api/v1/index.php');
        $response->assertJson(['error' => 'You do not have access to the requested project or the requested project does not exist.']);

        // Verify that they can access all 4 projects.
        $_GET['project'] = $this->public_project->name;
        $response = $this->actingAs($this->admin_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => $this->public_project->name,
            'public' => Project::ACCESS_PUBLIC,
        ]);
        $_GET['project'] = $this->protected_project->name;
        $response = $this->actingAs($this->admin_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => $this->protected_project->name,
            'public' => Project::ACCESS_PROTECTED,
        ]);
        $_GET['project'] = $this->private_project1->name;
        $response = $this->actingAs($this->admin_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => $this->private_project1->name,
            'public' => Project::ACCESS_PRIVATE,
        ]);
        $_GET['project'] = $this->private_project2->name;
        $response = $this->actingAs($this->admin_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => $this->private_project2->name,
            'public' => Project::ACCESS_PRIVATE,
        ]);
    }
}
