<?php

namespace Tests\Browser\Pages;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class CreateProjectPageTest extends BrowserTestCase
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

    public function testReturns403WhenInsufficientPermissions(): void
    {
        $this->users['normal'] = $this->makeNormalUser();
        $this->users['admin'] = $this->makeAdminUser();

        $this->browse(function (Browser $browser): void {
            $browser->visit('/projects/new')
                ->assertSee('This action is unauthorized.')
            ;

            $browser->loginAs($this->users['normal'])
                ->visit('/projects/new')
                ->assertSee('This action is unauthorized.')
            ;

            $browser->loginAs($this->users['admin'])
                ->visit('/projects/new')
                ->assertDontSee('This action is unauthorized.')
            ;
        });
    }

    public function testShowsErrorWhenInvalidProjectNameProvided(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/projects/new')
                ->waitFor('@create-project-page')
                ->type('@project-name-input', 'invalid project name %')
                ->click('@create-project-button')
                ->waitFor('@project-name-validation-errors')
                ->assertSeeIn('@project-name-validation-errors', 'Project name may only contain letters, numbers, dashes, and underscores.')
            ;
        });
    }

    public function testCreateProjectSetDescription(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        $name = Str::uuid()->toString();
        $description = Str::uuid()->toString();

        $this->browse(function (Browser $browser) use ($description, $name): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/projects/new')
                ->waitFor('@create-project-page')
                ->type('@project-name-input', $name)
                ->type('@project-description-input', $description)
                ->click('@create-project-button')
                ->waitForReload()
            ;
        });

        $project = Project::where('name', $name)->firstOrFail();
        $this->projects[] = $project;

        self::assertSame($description, $project->description);
    }

    public function testCreateProjectSetAuthenticatedSubmissions(): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        $name = Str::uuid()->toString();

        $this->browse(function (Browser $browser) use ($name): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/projects/new')
                ->waitFor('@create-project-page')
                ->type('@project-name-input', $name)
                ->click('@project-authenticated-submissions-input')
                ->click('@create-project-button')
                ->waitForReload()
            ;
        });

        $project = Project::where('name', $name)->firstOrFail();
        $this->projects[] = $project;

        self::assertTrue($project->authenticatesubmissions);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function visibilities(): array
    {
        return [
            ['public', Project::ACCESS_PUBLIC],
            ['protected', Project::ACCESS_PROTECTED],
            ['private', Project::ACCESS_PRIVATE],
        ];
    }

    #[DataProvider('visibilities')]
    public function testCreateProjectSetVisibility(string $fieldname, int $role): void
    {
        $this->users['admin'] = $this->makeAdminUser();

        $name = Str::uuid()->toString();

        $this->browse(function (Browser $browser) use ($fieldname, $name): void {
            $browser->loginAs($this->users['admin'])
                ->visit('/projects/new')
                ->waitFor('@create-project-page')
                ->type('@project-name-input', $name)
                ->click('@project-visibility-' . $fieldname)
                ->click('@create-project-button')
                ->waitForReload()
            ;
        });

        $project = Project::where('name', $name)->firstOrFail();
        $this->projects[] = $project;

        self::assertSame($role, $project->public);
    }
}
