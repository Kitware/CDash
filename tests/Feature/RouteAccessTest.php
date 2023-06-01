<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\URL;
use App\Models\User;
use LogicException;
use Mockery\Exception\InvalidCountException;
use Tests\Traits\CreatesUsers;
use Tests\TestCase;

class RouteAccessTest extends TestCase
{
    use CreatesUsers;

    protected User $normal_user;

    protected User $admin_user;

    // A list of all routes which require the user to be logged in
    private const PROTECTED_ROUTES = [
        '/user.php',
        '/editUser.php',
        '/subscribeProject.php',
        '/manageProjectRoles.php',
        '/manageBanner.php',
        '/manageCoverage.php',
        '/manageCoverage.php',
    ];

    // A list of admin-only routes
    private const ADMIN_ROUTES = [
        '/upgrade.php',
        '/import.php',
        '/importBackup.php',
        '/manageBackup.php',
        '/gitinfo.php',
        '/removeBuilds.php',
        '/siteStatistics.php',
        '/manageUsers.php',
        '/monitor',
    ];

    protected function setUp() : void
    {
        parent::setUp();

        URL::forceRootUrl('http://localhost');

        $this->normal_user = $this->makeNormalUser();
        $this->assertDatabaseHas('user', ['email' => 'jane@smith']);

        $this->admin_user = $this->makeAdminUser();
        $this->assertDatabaseHas('user', ['email' => 'admin@user', 'admin' => '1']);
    }

    /**
     * @throws LogicException
     * @throws InvalidCountException
     */
    protected function tearDown() : void
    {
        $this->normal_user->delete();
        $this->admin_user->delete();

        parent::tearDown();
    }

    public function testAdminRoutes(): void
    {
        foreach (self::ADMIN_ROUTES as $route) {
            $this->get($route)->assertRedirect('/login');
        }

        foreach (self::ADMIN_ROUTES as $route) {
            $this->actingAs($this->normal_user)->get($route)->assertRedirect('/login');
        }

        foreach (self::ADMIN_ROUTES as $route) {
            $this->actingAs($this->admin_user)->get($route)->assertOk();
        }
    }

    public function testProtectedRoutes(): void
    {
        foreach (self::PROTECTED_ROUTES as $route) {
            $this->get($route)->assertRedirect('/login');
        }

        foreach (self::PROTECTED_ROUTES as $route) {
            $this->actingAs($this->normal_user)->get($route)->assertOk();
        }

        foreach (self::PROTECTED_ROUTES as $route) {
            $this->actingAs($this->admin_user)->get($route)->assertOk();
        }
    }
}
