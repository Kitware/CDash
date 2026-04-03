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
