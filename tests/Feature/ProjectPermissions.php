<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;

use App\Models\User;
use CDash\Model\Project;
use CDash\ServiceContainer;
use CDash\System;
use Tests\TestCase;

class ProjectPermissions extends TestCase
{
    protected function setUp() : void
    {
        parent::setUp();
        $this->public_project = null;
        $this->protected_project = null;
        $this->private_project1 = null;
        $this->private_project2 = null;
        $this->normal_user = null;
        $this->admin_user = null;

        $container = ServiceContainer::container();
        $this->mock_system = $this->getMockBuilder(System::class)
            ->disableOriginalConstructor()
            ->setMethods(['system_exit'])
            ->getMock();
        $container->set(System::class, $this->mock_system);
    }

    protected function tearDown() : void
    {
        if ($this->public_project) {
            $this->public_project->Delete();
        }
        if ($this->protected_project) {
            $this->protected_project->Delete();
        }
        if ($this->private_project1) {
            $this->private_project1->Delete();
        }
        if ($this->private_project2) {
            $this->private_project2->Delete();
        }
        if ($this->normal_user) {
            $this->normal_user->delete();
        }
        if ($this->admin_user) {
            $this->admin_user->delete();
        }
        parent::tearDown();
    }

    /**
     * Feature test for project permissions (private/protected/public)
     *
     * @return void
     */
    public function testProjectPermissions()
    {
        \URL::forceRootUrl('http://localhost');

        // Create some projects.
        $this->public_project = new Project();
        $this->public_project->Name = 'PublicProject';
        $this->public_project->Public = Project::ACCESS_PUBLIC;
        $this->public_project->Save();
        $this->public_project->InitialSetup();

        $this->protected_project = new Project();
        $this->protected_project->Name = 'ProtectedProject';
        $this->protected_project->Public = Project::ACCESS_PROTECTED;
        $this->protected_project->Save();
        $this->protected_project->InitialSetup();

        $this->private_project1 = new Project();
        $this->private_project1->Name = 'PrivateProject1';
        $this->private_project1->Public = Project::ACCESS_PRIVATE;
        $this->private_project1->Save();
        $this->private_project1->InitialSetup();

        $this->private_project2 = new Project();
        $this->private_project2->Name = 'PrivateProject2';
        $this->private_project2->Public = Project::ACCESS_PRIVATE;
        $this->private_project2->Save();
        $this->private_project2->InitialSetup();

        // Verify that we can access the public project.
        $_GET['project'] = 'PublicProject';
        $response = $this->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => 'PublicProject',
            'public' => Project::ACCESS_PUBLIC
        ]);

        // Verify that viewProjects.php only lists the public project.
        $_GET['project'] = '';
        $_SERVER['SERVER_NAME'] = '';
        $_GET['allprojects'] = 1;
        $response = $this->get('/api/v1/viewProjects.php');
        $response->assertJson([
            'nprojects' => 1,
            'projects' => [
                ['name' => 'PublicProject'],
            ],
        ]);

        // Verify that we cannot access the protected project or the private projects.
        $_GET['project'] = 'ProtectedProject';
        $response = $this->get('/api/v1/index.php');
        $response->assertJson(['requirelogin' => 1]);
        $_GET['project'] = 'PrivateProject1';
        $response = $this->get('/api/v1/index.php');
        $response->assertJson(['requirelogin' => 1]);
        $_GET['project'] = 'PrivateProject2';
        $response = $this->get('/api/v1/index.php');
        $response->assertJson(['requirelogin' => 1]);

        // Create a non-administrator user.
        $this->normal_user = new User();
        $this->normal_user->firstname = 'Jane';
        $this->normal_user->lastname = 'Smith';
        $this->normal_user->email = 'jane@smith';
        $this->normal_user->password = '12345';
        $this->normal_user->institution = 'me';
        $this->normal_user->admin = false;
        $this->normal_user->save();
        $this->assertDatabaseHas('user', ['email' => 'jane@smith']);

        // Verify that we can still access the public project when logged in
        // as this user.
        $_GET['project'] = 'PublicProject';
        $response = $this->actingAs($this->normal_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => 'PublicProject',
            'public' => Project::ACCESS_PUBLIC
        ]);

        // Verify that we can access the protected project when logged in
        // as this user.
        $_GET['project'] = 'ProtectedProject';
        $response = $this->actingAs($this->normal_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => 'ProtectedProject',
            'public' => Project::ACCESS_PROTECTED
        ]);

        // Add the user to PrivateProject1.
        \DB::table('user2project')->insert([
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
        $_GET['project'] = 'PrivateProject1';
        $response = $this->actingAs($this->normal_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => 'PrivateProject1',
            'public' => Project::ACCESS_PRIVATE
        ]);

        // Verify that she cannot access PrivateProject2.
        $_GET['project'] = 'PrivateProject2';
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
                ['name' => 'PrivateProject1'],
                ['name' => 'ProtectedProject'],
                ['name' => 'PublicProject'],
            ],
        ]);

        // Make an admin user.
        $this->admin_user = new User();
        $this->admin_user->firstname = 'Admin';
        $this->admin_user->lastname = 'User';
        $this->admin_user->email = 'admin@user';
        $this->admin_user->password = '45678';
        $this->admin_user->institution = 'me';
        $this->admin_user->admin = true;
        $this->admin_user->save();
        $this->assertDatabaseHas('user', ['email' => 'admin@user', 'admin' => '1']);

        // Verify that they can access all 4 projects.
        $_GET['project'] = 'PublicProject';
        $response = $this->actingAs($this->admin_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => 'PublicProject',
            'public' => Project::ACCESS_PUBLIC
        ]);
        $_GET['project'] = 'ProtectedProject';
        $response = $this->actingAs($this->admin_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => 'ProtectedProject',
            'public' => Project::ACCESS_PROTECTED
        ]);
        $_GET['project'] = 'PrivateProject1';
        $response = $this->actingAs($this->admin_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => 'PrivateProject1',
            'public' => Project::ACCESS_PRIVATE
        ]);
        $_GET['project'] = 'PrivateProject2';
        $response = $this->actingAs($this->admin_user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => 'PrivateProject2',
            'public' => Project::ACCESS_PRIVATE
        ]);

        // Verify that admin sees all four projects on viewProjects.php
        $_GET['project'] = '';
        $_SERVER['SERVER_NAME'] = '';
        $_GET['allprojects'] = 1;
        $response = $this->actingAs($this->admin_user)->get('/api/v1/viewProjects.php');
        $response->assertJson([
            'nprojects' => 4,
            'projects' => [
                ['name' => 'PrivateProject1'],
                ['name' => 'PrivateProject2'],
                ['name' => 'ProtectedProject'],
                ['name' => 'PublicProject'],
            ],
        ]);
    }
}
