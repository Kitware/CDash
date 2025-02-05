<?php

namespace Tests\Browser\Pages;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class RegistrationTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesUsers;

    /**
     * @var array<Project>
     */
    private array $projects = [];
    private string|bool $original = '';
    private string $path = '../../../../.env';

    /**
     * Stolen from https://laracasts.com/discuss/channels/testing/how-to-change-env-variable-config-in-dusk-test
     *
     * @param array<string,string> $variables
     */
    protected function override(array $variables = []): void
    {
        if (file_exists($this->path)) {
            // The environment variables to prepend
            $prepend = '';

            // Convert all new parameters to expected format
            foreach ($variables as $key => $value) {
                $prepend .= $key . '=' . $value . PHP_EOL;
            }

            // Grab original .env file contents
            $this->original = file_get_contents($this->path);
            // Write all to .env file for dusk test
            file_put_contents($this->path, $prepend . $this->original);
        }
    }

    public function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->projects as $project) {
            $project->delete();
        }
        $this->projects = [];

        // Reset the .env file
        file_put_contents($this->path, $this->original);
        parent::tearDown();
    }

    /**
     * @return array<int,array<int,bool|string>>
     */
    public static function PublicRegistrationChecks(): array
    {
        return [
            ['PUBLIC', true],
            ['PROJECT_ADMIN', false],
            ['ADMIN', false],
            ['DISABLED', false],
        ];
    }

    /**
     * @dataProvider PublicRegistrationChecks
     */
    public function testPublicRegistrationForm(string $envVariableValue, bool $shouldFind): void
    {
        $this->override(['USER_REGISTRATION_ACCESS_LEVEL_REQUIRED' => $envVariableValue]);
        $this->browse(function (Browser $browser) use ($shouldFind) {
            $browser->refresh()->visit('/projects}')
                ->whenAvailable('#topmenu', function (Browser $browser) use ($shouldFind) {
                    $shouldFind ? $browser->assertSee('Register') : $browser->assertDontSee('Register');
                });
        });
    }

    /**
     * @return array<int,array<int,bool|string>>
     */
    public static function ProjectManagerChecksAsAdministrator(): array
    {
        return [
            ['PUBLIC', true],
            ['PROJECT_ADMIN', true],
            ['ADMIN', true],
            ['DISABLED', false],
        ];
    }

    public function visitProjectManageUsersForm(string $envVariableValue, bool $shouldFind, User $user): void
    {
        $this->override(['USER_REGISTRATION_ACCESS_LEVEL_REQUIRED' => $envVariableValue]);
        $this->browse(function (Browser $browser) use ($shouldFind, $user) {
            $browser->loginAs((string) $user->id)
                ->visit("/manageProjectRoles.php?projectid={$this->projects['public1']->id}")
                ->refresh()
                ->whenAvailable(' #wizard', function (Browser $browser) use ($shouldFind) {
                    $shouldFind ? $browser->assertPresent('#fragment-3') : $browser->assertNotPresent('#fragment-3');
                });
        });
    }

    /**
     * @dataProvider ProjectManagerChecksAsAdministrator
     */
    public function testProjectManageUsersFormAsSiteAdministrator(string $envVariableValue, bool $shouldFind): void
    {
        $user = $this->makeAdminUser();
        $this->projects['public1'] = $this->makePublicProject();
        $this->projects['public1']->description = Str::uuid()->toString();
        $this->projects['public1']->save();

        $this->visitProjectManageUsersForm($envVariableValue, $shouldFind, $user);
    }

    /**
     * @return array<int,array<int,bool|string>>
     */
    public static function ProjectManagerChecksAsProjectAdministrator()
    {
        return [
            ['PUBLIC', true],
            ['PROJECT_ADMIN', true],
            ['ADMIN', false],
            ['DISABLED', false],
        ];
    }

    /**
     * @dataProvider ProjectManagerChecksAsProjectAdministrator
     */
    public function testProjectManageUsersFormAsProjectAdminstrator(string $envVariableValue, bool $shouldFind): void
    {
        $user = $this->makeNormalUser();
        $this->projects['public1'] = $this->makePublicProject();
        $this->projects['public1']->description = Str::uuid()->toString();
        $this->projects['public1']->save();
        // Add the user to project as admin.

        $this->projects['public1']->users()->attach($user->id, ['role' => Project::PROJECT_ADMIN]);
        $this->visitProjectManageUsersForm($envVariableValue, $shouldFind, $user);
    }

    /**
     * @return array<int,array<int,bool|string>>
     */
    public static function ManageUsersChecks(): array
    {
        return [
            ['PUBLIC', true],
            ['PROJECT_ADMIN', true],
            ['ADMIN', true],
            ['DISABLED', false],
        ];
    }

    public function visitManageUsersForm(string $envVariableValue, bool $shouldFind, User $user): void
    {
        $this->override(['USER_REGISTRATION_ACCESS_LEVEL_REQUIRED' => $envVariableValue]);
        $this->browse(function (Browser $browser) use ($shouldFind, $user) {
            $browser->loginAs((string) $user->id)
                ->visit('/manageUsers.php')
                ->refresh()
                ->whenAvailable('', function (Browser $browser) use ($shouldFind) {
                    $shouldFind ? $browser->assertSee('Add new user') : $browser->assertDontSee('Add new user');
                });
        });
    }

    /**
     * @dataProvider ProjectManagerChecksAsAdministrator
     */
    public function testManageUsersForm(string $envVariableValue, bool $shouldFind): void
    {
        $user = $this->makeAdminUser();
        $this->visitManageUsersForm($envVariableValue, $shouldFind, $user);
    }
}
