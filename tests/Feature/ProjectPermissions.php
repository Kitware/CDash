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
        $this->project = null;
        $this->user = null;

        $container = ServiceContainer::container();
        $this->mock_system = $this->getMockBuilder(System::class)
            ->disableOriginalConstructor()
            ->setMethods(['system_exit'])
            ->getMock();
        $container->set(System::class, $this->mock_system);
    }

    protected function tearDown() : void
    {
        if ($this->project) {
            $this->project->Delete();
        }
        if ($this->user) {
            $this->user->delete();
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

        // Create a public project.
        $this->project = new Project();
        $this->project->Name = 'TestPermissions';
        $this->project->Public = Project::ACCESS_PUBLIC;
        $this->project->Save();
        $this->project->InitialSetup();

        // Verify that we can access this project.
        $_GET['project'] = 'TestPermissions';
        $response = $this->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => 'TestPermissions',
            'public' => Project::ACCESS_PUBLIC
        ]);

        // Make the project protected.
        $this->project->Public = Project::ACCESS_PROTECTED;
        $this->project->Save();

        // Verify that we cannot access it anymore.
        $response = $this->get('/api/v1/index.php');
        $response->assertJson(['requirelogin' => 1]);

        // Create a user.
        $this->user = new User();
        $this->user->firstname = 'Jane';
        $this->user->lastname = 'Smith';
        $this->user->email = 'jane@smith';
        $this->user->password = '12345';
        $this->user->institution = 'me';
        $this->user->admin = false;
        $this->user->save();
        $this->assertDatabaseHas('user', ['email' => 'jane@smith']);

        // Verify that we can access the protected project when logged in
        // as this user.
        $response = $this->actingAs($this->user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => 'TestPermissions',
            'public' => Project::ACCESS_PROTECTED
        ]);

        // Make the project private.
        $this->project->Public = Project::ACCESS_PRIVATE;
        $this->project->Save();

        // Verify that we cannot access it anymore.
        $response = $this->actingAs($this->user)->get('/api/v1/index.php');
        $response->assertJson(['error' => 'You do not have permission to access this page.']);

        // Add the user to the project.
        \DB::table('user2project')->insert([
            'userid' => $this->user->id,
            'projectid' => $this->project->Id,
            'role' => 0,
            'cvslogin' => '',
            'emailtype' => 0,
            'emailcategory' => 0,
            'emailsuccess' => 0,
            'emailmissingsites' => 0,
        ]);

        // Verify that we can now access the private project.
        $response = $this->actingAs($this->user)->get('/api/v1/index.php');
        $response->assertJson([
            'projectname' => 'TestPermissions',
            'public' => Project::ACCESS_PRIVATE
        ]);
    }
}
