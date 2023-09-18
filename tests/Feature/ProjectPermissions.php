<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use CDash\Model\Project;
use CDash\ServiceContainer;
use CDash\System;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;
use Tests\TestCase;

class ProjectPermissions extends TestCase
{
    use CreatesUsers;
    use CreatesProjects;

    protected Project $public_project;
    protected Project $protected_project;
    protected Project $private_project1;
    protected Project $private_project2;
    protected User $normal_user;
    protected User $admin_user;
    protected $mock_system;

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

        $container = ServiceContainer::container();
        $this->mock_system = $this->getMockBuilder(System::class)
            ->disableOriginalConstructor()
            ->setMethods(['system_exit'])
            ->getMock();
        $container->set(System::class, $this->mock_system);
    }

    protected function tearDown(): void
    {
        $this->public_project->Delete();
        $this->protected_project->Delete();
        $this->private_project1->Delete();
        $this->private_project2->Delete();
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
        $_GET['project'] = $this->public_project->Name;
        $response = $this->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => $this->public_project->Name,
            'public' => Project::ACCESS_PUBLIC,
        ]);

        // Verify that viewProjects.php only lists the public project.
        $_GET['project'] = '';
        $_SERVER['SERVER_NAME'] = '';
        $_GET['allprojects'] = 1;
        $response = $this->get('/api/v1/viewProjects.php');
        $response->assertJson([
            'nprojects' => 1,
            'projects' => [
                ['name' => $this->public_project->Name],
            ],
        ]);

        // Verify that we cannot access the protected project or the private projects.
        $_GET['project'] = $this->protected_project->Name;
        $response = $this->get('/api/v1/index.php');
        $response->assertJson(['requirelogin' => 1]);
        $_GET['project'] = $this->private_project1->Name;
        $response = $this->get('/api/v1/index.php');
        $response->assertJson(['requirelogin' => 1]);
        $_GET['project'] = $this->private_project2->Name;
        $response = $this->get('/api/v1/index.php');
        $response->assertJson(['requirelogin' => 1]);

        // Test the v2 API
        $response = $this->get('/api/v2/projects')->assertOk();
        $response->assertJsonFragment(['name' => $this->public_project->Name]);
        $response->assertJsonMissing(['name' => $this->protected_project->Name]);
        $response->assertJsonMissing(['name' => $this->private_project1->Name]);
        $response->assertJsonMissing(['name' => $this->private_project2->Name]);

        $this->get('/api/v2/projects/' . $this->public_project->Id)
            ->assertOk()
            ->assertJsonFragment(['name' => $this->public_project->Name]);
        $this->get('/api/v2/projects/' . $this->protected_project->Id)
            ->assertNotFound()
            ->assertJson($missing_project_response);
        $this->get('/api/v2/projects/' . $this->private_project1->Id)
            ->assertNotFound()
            ->assertJson($missing_project_response);
        $this->get('/api/v2/projects/' . $this->private_project2->Id)
            ->assertNotFound()
            ->assertJson($missing_project_response);

        // Verify that we can still access the public project when logged in
        // as this user.
        $_GET['project'] = $this->public_project->Name;
        $response = $this->actingAs($this->normal_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => $this->public_project->Name,
            'public' => Project::ACCESS_PUBLIC,
        ]);

        // Verify that we can access the protected project when logged in
        // as this user.
        $_GET['project'] = $this->protected_project->Name;
        $response = $this->actingAs($this->normal_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => $this->protected_project->Name,
            'public' => Project::ACCESS_PROTECTED,
        ]);

        // Test the v2 API
        $response = $this->actingAs($this->normal_user)->get('/api/v2/projects')->assertOk();
        $response->assertJsonFragment(['name' => $this->public_project->Name]);
        $response->assertJsonFragment(['name' => $this->protected_project->Name]);
        $response->assertJsonMissing(['name' => $this->private_project1->Name]);
        $response->assertJsonMissing(['name' => $this->private_project2->Name]);

        $this->actingAs($this->normal_user)->get('/api/v2/projects/' . $this->public_project->Id)
            ->assertOk()
            ->assertJsonFragment(['name' => $this->public_project->Name]);
        $this->actingAs($this->normal_user)->get('/api/v2/projects/' . $this->protected_project->Id)
            ->assertOk()
            ->assertJsonFragment(['name' => $this->protected_project->Name]);
        $this->actingAs($this->normal_user)->get('/api/v2/projects/' . $this->private_project1->Id)
            ->assertNotFound()
            ->assertJson($missing_project_response);
        $this->actingAs($this->normal_user)->get('/api/v2/projects/' . $this->private_project2->Id)
            ->assertNotFound()
            ->assertJson($missing_project_response);

        // Add the user to PrivateProject1.
        DB::table('user2project')->insert([
            'userid' => $this->normal_user->id,
            'projectid' => $this->private_project1->Id,
            'role' => 0,
            'cvslogin' => '',
            'emailtype' => 0,
            'emailcategory' => 0,
            'emailsuccess' => 0,
            'emailmissingsites' => 0,
        ]);

        // Verify that she can access it.
        $_GET['project'] = $this->private_project1->Name;
        $response = $this->actingAs($this->normal_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => $this->private_project1->Name,
            'public' => Project::ACCESS_PRIVATE,
        ]);

        // Verify that she cannot access PrivateProject2.
        $_GET['project'] = $this->private_project2->Name;
        $response = $this->actingAs($this->normal_user)->get('/api/v1/index.php');
        $response->assertJson(['error' => 'You do not have permission to access this page.']);

        // Verify that viewProjects.php lists public, protected, and private1, but not private2.
        $_GET['project'] = '';
        $_SERVER['SERVER_NAME'] = '';
        $_GET['allprojects'] = 1;
        $response = $this->actingAs($this->normal_user)->get('/api/v1/viewProjects.php');
        $response->assertJson([
            'nprojects' => 3,
            'projects' => [
                ['name' => $this->private_project1->Name],
                ['name' => $this->protected_project->Name],
                ['name' => $this->public_project->Name],
            ],
        ]);

        // Test the v2 API
        $response = $this->actingAs($this->normal_user)->get('/api/v2/projects')->assertOk();
        $response->assertJsonFragment(['name' => $this->public_project->Name]);
        $response->assertJsonFragment(['name' => $this->protected_project->Name]);
        $response->assertJsonFragment(['name' => $this->private_project1->Name]);
        $response->assertJsonMissing(['name' => $this->private_project2->Name]);

        $this->actingAs($this->normal_user)->get('/api/v2/projects/' . $this->public_project->Id)
            ->assertOk()
            ->assertJsonFragment(['name' => $this->public_project->Name]);
        $this->actingAs($this->normal_user)->get('/api/v2/projects/' . $this->protected_project->Id)
            ->assertOk()
            ->assertJsonFragment(['name' => $this->protected_project->Name]);
        $this->actingAs($this->normal_user)->get('/api/v2/projects/' . $this->private_project1->Id)
            ->assertOk()
            ->assertJsonFragment(['name' => $this->private_project1->Name]);
        $this->actingAs($this->normal_user)->get('/api/v2/projects/' . $this->private_project2->Id)
            ->assertNotFound()
            ->assertJson($missing_project_response);

        // Verify that they can access all 4 projects.
        $_GET['project'] = $this->public_project->Name;
        $response = $this->actingAs($this->admin_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => $this->public_project->Name,
            'public' => Project::ACCESS_PUBLIC,
        ]);
        $_GET['project'] = $this->protected_project->Name;
        $response = $this->actingAs($this->admin_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => $this->protected_project->Name,
            'public' => Project::ACCESS_PROTECTED,
        ]);
        $_GET['project'] = $this->private_project1->Name;
        $response = $this->actingAs($this->admin_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => $this->private_project1->Name,
            'public' => Project::ACCESS_PRIVATE,
        ]);
        $_GET['project'] = $this->private_project2->Name;
        $response = $this->actingAs($this->admin_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => $this->private_project2->Name,
            'public' => Project::ACCESS_PRIVATE,
        ]);

        // Verify that admin sees all four projects on viewProjects.php
        // the order of the projects returned is not deterministic, which makes testing the array structure challenging
        $_GET['project'] = '';
        $_SERVER['SERVER_NAME'] = '';
        $_GET['allprojects'] = 1;
        $response = $this->actingAs($this->admin_user)->get('/api/v1/viewProjects.php');
        $response->assertJsonPath('nprojects', 4);
        $response->assertJsonCount(4, 'projects');
        $response->assertJsonFragment(['name' => $this->private_project1->Name]);
        $response->assertJsonFragment(['name' => $this->private_project2->Name]);
        $response->assertJsonFragment(['name' => $this->protected_project->Name]);
        $response->assertJsonFragment(['name' => $this->public_project->Name]);

        // Test the v2 API
        $response = $this->actingAs($this->admin_user)->get('/api/v2/projects')->assertOk();
        $response->assertJsonFragment(['name' => $this->public_project->Name]);
        $response->assertJsonFragment(['name' => $this->protected_project->Name]);
        $response->assertJsonFragment(['name' => $this->private_project1->Name]);
        $response->assertJsonFragment(['name' => $this->private_project2->Name]);

        $this->actingAs($this->admin_user)->get('/api/v2/projects/' . $this->public_project->Id)
            ->assertOk()
            ->assertJsonFragment(['name' => $this->public_project->Name]);
        $this->actingAs($this->admin_user)->get('/api/v2/projects/' . $this->protected_project->Id)
            ->assertOk()
            ->assertJsonFragment(['name' => $this->protected_project->Name]);
        $this->actingAs($this->admin_user)->get('/api/v2/projects/' . $this->private_project1->Id)
            ->assertOk()
            ->assertJsonFragment(['name' => $this->private_project1->Name]);
        $this->actingAs($this->admin_user)->get('/api/v2/projects/' . $this->private_project2->Id)
            ->assertOk()
            ->assertJsonFragment(['name' => $this->private_project2->Name]);
    }
}
