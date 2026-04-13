<?php

namespace Tests\Browser\Pages;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class ProfilePageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesUsers;

    /**
     * @var array<User>
     */
    private array $users = [];

    /**
     * @var array<Project>
     */
    private array $projects = [];

    public function tearDown(): void
    {
        foreach ($this->users as $user) {
            $user->delete();
        }
        $this->users = [];

        foreach ($this->projects as $project) {
            $project->delete();
        }
        $this->projects = [];

        parent::tearDown();
    }

    public function testIsProtectedByLogin(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/profile')
                ->assertUrlIs(config('app.url') . '/login');
        });
    }

    public function testCanChangeNameAndEmail(): void
    {
        $this->users['admin'] = $this->makeAdminUser(
            'admin',
            'admin',
            'admin@example.com',
            null,
            'testing',
        );

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/profile')
                ->assertInputValue('@fname-input', 'admin')
                ->assertInputValue('@lname-input', 'admin')
                ->assertInputValue('@email-input', 'admin@example.com')
                ->assertInputValue('@institution-input', 'testing')

                ->clear('@fname-input')
                ->type('@fname-input', 'first name here')
                ->clear('@lname-input')
                ->type('@lname-input', 'last name here')
                ->clear('@email-input')
                ->type('@email-input', 'emailtest@example.com')
                ->clear('@institution-input')
                ->type('@institution-input', 'Kitware (Test)')

                ->click('@update-profile-button')
                ->waitForTextIn('#app', 'Your profile has been updated');
        });

        $user = User::where('email', 'emailtest@example.com')->firstOrFail();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->logout()
                ->loginAs($user)
                ->visit('/profile')

                ->assertInputValue('@fname-input', 'first name here')
                ->clear('@fname-input')
                ->type('@fname-input', 'admin')
                ->assertInputValue('@lname-input', 'last name here')
                ->clear('@lname-input')
                ->type('@lname-input', 'admin')
                ->assertInputValue('@email-input', 'emailtest@example.com')
                ->clear('@email-input')
                ->type('@email-input', 'admin@example.com')
                ->assertInputValue('@institution-input', 'Kitware (Test)')
                ->clear('@institution-input')
                ->type('@institution-input', 'testing')

                ->click('@update-profile-button')
                ->waitForTextIn('#app', 'Your profile has been updated');
        });

        $user = User::where('email', 'admin@example.com')->firstOrFail();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->logout()
                ->loginAs($user)
                ->visit('/profile')
                ->assertInputValue('@fname-input', 'admin')
                ->assertInputValue('@lname-input', 'admin')
                ->assertInputValue('@email-input', 'admin@example.com')
                ->assertInputValue('@institution-input', 'testing');
        });
    }

    public function testIncorrectPasswordPreventsPasswordReset(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/profile')
                ->assertInputValue('@oldpasswd-input', '')
                ->assertInputValue('@passwd-input', '')
                ->assertInputValue('@passwd2-input', '')

                ->type('@oldpasswd-input', 'incorrect password')
                ->type('@passwd-input', 'new password')
                ->type('@passwd2-input', 'new password')
                ->click('@update-password-button')
                ->waitForTextIn('#app', 'Your old password is incorrect');
        });
    }

    public function testShortPasswordPreventsPasswordReset(): void
    {
        $password = Str::random(10);
        $this->users['admin'] = clone $this->makeAdminUser();
        $this->users['admin']->password = bcrypt($password);
        $this->users['admin']->save();

        $this->browse(function (Browser $browser) use ($password): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/profile')
                ->assertInputValue('@oldpasswd-input', '')
                ->assertInputValue('@passwd-input', '')
                ->assertInputValue('@passwd2-input', '')

                ->type('@oldpasswd-input', $password)
                ->type('@passwd-input', 'a')
                ->type('@passwd2-input', 'a')
                ->click('@update-password-button')
                ->waitForTextIn('#app', 'Password must be at least 5 characters');
        });
    }

    public function testCanChangePassword(): void
    {
        $password = Str::random(10);
        $this->users['admin'] = clone $this->makeAdminUser();
        $this->users['admin']->password = bcrypt($password);
        $this->users['admin']->save();

        $this->browse(function (Browser $browser) use ($password): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/profile')
                ->assertInputValue('@oldpasswd-input', '')
                ->assertInputValue('@passwd-input', '')
                ->assertInputValue('@passwd2-input', '')

                ->type('@oldpasswd-input', $password)
                ->type('@passwd-input', 'new password')
                ->type('@passwd2-input', 'new password')
                ->click('@update-password-button')
                ->waitForTextIn('#app', 'Your password has been updated');
        });

        $user = User::where('email', $this->users['admin']->email)->firstOrFail();
        $this->assertTrue(password_verify('new password', $user->password));

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->logout()
                ->loginAs($user)
                ->visit('/profile')
                ->assertInputValue('@oldpasswd-input', '')
                ->assertInputValue('@passwd-input', '')
                ->assertInputValue('@passwd2-input', '')

                ->type('@oldpasswd-input', 'new password')
                ->type('@passwd-input', '12345')
                ->type('@passwd2-input', '12345')
                ->click('@update-password-button')
                ->waitForTextIn('#app', 'Your password has been updated');
        });

        $user = User::where('email', $this->users['admin']->email)->firstOrFail();
        $this->assertTrue(password_verify('12345', $user->password));
    }

    public function testCreateFullAccessToken(): void
    {
        $this->users['admin'] = $this->makeAdminUser();
        $description = Str::uuid()->toString();

        $this->browse(function (Browser $browser) use ($description): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/profile')
                ->waitFor('@token-description-input')
                ->type('@token-description-input', $description)
                ->select('@token-scope-input', 'FULL_ACCESS')
                ->assertDisabled('@token-project-input')
                ->assertMissing('@new-token-error')
                ->assertMissing('@new-token-container')
                ->click('@create-token-button')
                ->waitForTextIn('@auth-tokens-table', $description)
                ->assertMissing('@new-token-error')
                ->assertVisible('@new-token-container')
                ->refresh()
                ->waitForTextIn('@auth-tokens-table', $description)
                ->waitForTextIn('@auth-tokens-table', 'Full Access')
                ->assertMissing('@new-token-error')
                ->assertMissing('@new-token-container')
            ;
        });
    }

    public function testCreateSubmitOnlyAllProjectsToken(): void
    {
        $this->users['admin'] = $this->makeAdminUser();
        $description = Str::uuid()->toString();

        $this->browse(function (Browser $browser) use ($description): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/profile')
                ->waitFor('@token-description-input')
                ->type('@token-description-input', $description)
                ->select('@token-scope-input', 'SUBMIT_ONLY')
                ->assertMissing('@new-token-error')
                ->assertMissing('@new-token-container')
                ->click('@create-token-button')
                ->waitForTextIn('@auth-tokens-table', $description)
                ->assertMissing('@new-token-error')
                ->assertVisible('@new-token-container')
                ->refresh()
                ->waitForTextIn('@auth-tokens-table', $description)
                ->waitForTextIn('@auth-tokens-table', 'Submit Only')
                ->assertMissing('@new-token-error')
                ->assertMissing('@new-token-container')
            ;
        });
    }

    public function testCreateProjectScopedSubmitOnlyToken(): void
    {
        $this->users['admin'] = $this->makeAdminUser();
        $this->projects['project'] = $this->makePublicProject();
        $this->projects['project']->users()->attach($this->users['admin'], ['role' => Project::PROJECT_USER]);
        $description = Str::uuid()->toString();

        $this->browse(function (Browser $browser) use ($description): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/profile')
                ->waitFor('@token-description-input')
                ->type('@token-description-input', $description)
                ->select('@token-scope-input', 'SUBMIT_ONLY')
                ->select('@token-project-input', (string) $this->projects['project']->id)
                ->assertMissing('@new-token-error')
                ->assertMissing('@new-token-container')
                ->click('@create-token-button')
                ->waitForTextIn('@auth-tokens-table', $description)
                ->assertMissing('@new-token-error')
                ->assertVisible('@new-token-container')
                ->refresh()
                ->waitForTextIn('@auth-tokens-table', $description)
                ->waitForTextIn('@auth-tokens-table', 'Submit Only')
                ->waitForTextIn('@auth-tokens-table', $this->projects['project']->name)
                ->assertMissing('@new-token-error')
                ->assertMissing('@new-token-container')
            ;
        });
    }

    public function testDeleteToken(): void
    {
        $this->users['admin'] = $this->makeAdminUser();
        $description = Str::uuid()->toString();

        $this->browse(function (Browser $browser) use ($description): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/profile')
                ->waitFor('@token-description-input')
                ->type('@token-description-input', $description)
                ->click('@create-token-button')
                ->waitForTextIn('@auth-tokens-table', $description)
                ->refresh()
                ->waitForTextIn('@auth-tokens-table', $description)
                ->assertDontSee('No authentication tokens to display.')
                ->click('@delete-token-button')
                ->waitForText('No authentication tokens to display.')
                ->refresh()
                ->waitForText('No authentication tokens to display.')
            ;
        });
    }
}
