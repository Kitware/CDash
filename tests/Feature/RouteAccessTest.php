<?php

namespace Tests\Feature;

use CDash\Model\Project;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use LogicException;
use Mockery\Exception\InvalidCountException;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;
use Tests\TestCase;

class RouteAccessTest extends TestCase
{
    use CreatesUsers;
    use CreatesProjects;

    private User $normal_user;
    private User $admin_user;
    private Project $public_project;

    protected function setUp(): void
    {
        parent::setUp();

        URL::forceRootUrl('http://localhost');

        $this->normal_user = $this->makeNormalUser();
        $this->assertDatabaseHas('user', ['email' => 'jane@smith']);

        $this->admin_user = $this->makeAdminUser();
        $this->assertDatabaseHas('user', ['email' => 'admin@user', 'admin' => '1']);

        $this->public_project = $this->makePublicProject();
    }

    /**
     * @throws LogicException
     * @throws InvalidCountException
     */
    protected function tearDown(): void
    {
        $this->normal_user->delete();
        $this->admin_user->delete();
        $this->public_project->Delete();

        parent::tearDown();
    }

    // A list of all routes which require the user to be logged in
    private function protectedRoutes(): array
    {
        return [
            ['/user.php'],
            ['/editUser.php'],
            ['/subscribeProject.php'],
            ['/manageProjectRoles.php'],
            ['/manageCoverage.php'],
            ['/manageCoverage.php'],
            ['/manageBanner.php'],
        ];
    }

    // A list of admin-only routes
    private function adminRoutes(): array
    {
        return [
            ['/upgrade.php'],
            ['/import.php'],
            ['/importBackup.php'],
            ['/manageBackup.php'],
            ['/gitinfo.php'],
            ['/removeBuilds.php'],
            ['/siteStatistics.php'],
            ['/manageUsers.php'],
            ['/monitor'],
            ['/ajax/findusers.php'],
        ];
    }

    /**
     * @dataProvider adminRoutes
     */
    public function testAdminRoutes(string $route): void
    {
        $this->get($route)->assertRedirect('login');
        $this->actingAs($this->normal_user)->get($route)->assertForbidden()->assertSeeText('You must be an administrator to access this page.');
        $this->actingAs($this->admin_user)->get($route)->assertDontSeeText('You must be an administrator to access this page.');
    }

    /**
     * @dataProvider protectedRoutes
     */
    public function testProtectedRoutes(string $route): void
    {
        $this->get($route)->assertRedirect('login');
        // A hack to make sure we weren't redirected to the login page.
        $this->actingAs($this->normal_user)->get($route)->assertDontSeeText('Forgot your password?');
        $this->actingAs($this->admin_user)->get($route)->assertDontSeeText('Forgot your password?');
    }
}
