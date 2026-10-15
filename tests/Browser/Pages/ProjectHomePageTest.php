<?php

namespace Tests\Browser\Pages;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;

class ProjectHomePageTest extends BrowserTestCase
{
    use CreatesProjects;

    private Project $project;

    private ?Site $site = null;

    /**
     * @var array<User>
     */
    private array $users = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    public function tearDown(): void
    {
        $this->project->delete();

        $this->site?->delete();
        $this->site = null;

        foreach ($this->users as $user) {
            $user->delete();
        }
        $this->users = [];

        parent::tearDown();
    }

    private function createUser(bool $admin = false, string $firstname = 'Jane', string $lastname = 'Doe'): User
    {
        $factory = $admin ? User::factory()->adminUser() : User::factory();
        $user = $factory->create([
            'firstname' => $firstname,
            'lastname' => $lastname,
        ]);
        $this->users[] = $user;

        return $user;
    }

    private function addUserToProject(ProjectRole $role, string $firstname = 'Jane', string $lastname = 'Doe'): User
    {
        $user = $this->createUser(false, $firstname, $lastname);

        $this->project->users()->attach($user->id, [
            'emailtype' => 0,
            'emailcategory' => 0,
            'emailsuccess' => true,
            'emailmissingsites' => true,
            'role' => $role,
        ]);

        return $user;
    }

    private function createBuild(Carbon $time): void
    {
        $this->site ??= Site::factory()->create();

        $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
            'starttime' => $time,
            'endtime' => $time,
            'submittime' => $time,
        ]);
    }

    /**
     * Log in as a project administrator and open the resources editor.
     */
    private function openResourcesEditor(Browser $browser, User $admin): Browser
    {
        return $browser->loginAs($admin)
            ->visit("/projects/{$this->project->id}")
            ->waitFor('@edit-resources-button')
            ->click('@edit-resources-button')
            ->waitFor('[data-test="edit-resources-dialog"][open]');
    }

    public function testShowsProjectInformation(): void
    {
        $this->project->update([
            'description' => 'A test project description',
            'cvsurl' => 'https://github.com/Kitware/CDash',
            'cvsviewertype' => 'github',
            'homeurl' => 'https://example.com',
            'documentationurl' => 'https://example.com/docs',
            'bugtrackerurl' => 'https://example.com/issues',
            'bugtrackernewissueurl' => 'https://example.com/issues/new',
            'testingdataurl' => 'https://example.com/data',
        ]);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/projects/{$this->project->id}")
                ->whenAvailable('@project-home-page', function (Browser $browser): void {
                    $browser->assertSeeIn('@project-name', $this->project->name)
                        ->assertSeeIn('@project-visibility', 'Public')
                        ->assertSeeIn('@project-description', 'A test project description')
                        ->assertAttribute('@repository-link', 'href', 'https://github.com/Kitware/CDash')
                        ->assertPresent('[data-test="repository-link"] svg[data-icon="github"]')
                        ->assertAttribute('@website-link', 'href', 'https://example.com')
                        ->assertAttribute('@documentation-link', 'href', 'https://example.com/docs')
                        ->assertAttribute('@bug-tracker-link', 'href', 'https://example.com/issues')
                        ->assertAttribute('@new-issue-link', 'href', 'https://example.com/issues/new')
                        ->assertAttribute('@test-data-link', 'href', 'https://example.com/data')
                        ->assertMissing('@no-resources-message')
                        ->assertMissing('@edit-resources-button');
                });

            $resources = $browser->script("return [...document.querySelectorAll('[data-test=\"resources-section\"] a')].map(a => a.textContent.trim());")[0];
            self::assertSame(['Repository', 'Website', 'Documentation', 'Bug tracker', 'Report an issue', 'Test data'], $resources);
        });
    }

    public function testRepositoryIconMatchesRepositoryType(): void
    {
        $this->project->update([
            'cvsurl' => 'https://gitlab.kitware.com/cmake/cmake',
            'cvsviewertype' => 'gitlab',
        ]);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/projects/{$this->project->id}")
                ->waitFor('@repository-link')
                ->assertPresent('[data-test="repository-link"] svg[data-icon="gitlab"]');

            $this->project->update(['cvsviewertype' => null]);

            $browser->visit("/projects/{$this->project->id}")
                ->waitFor('@repository-link')
                ->assertPresent('[data-test="repository-link"] svg[data-icon="code-branch"]');
        });
    }

    public function testEmptyProject(): void
    {
        $siteAdmin = $this->createUser(true);

        $this->browse(function (Browser $browser) use ($siteAdmin): void {
            $browser->visit("/projects/{$this->project->id}")
                ->whenAvailable('@project-home-page', function (Browser $browser): void {
                    $browser->assertMissing('@project-description')
                        ->assertMissing('@administrators-section')
                        ->assertSeeIn('@resources-section', 'No resources have been added to this project.')
                        ->assertMissing('@add-resources-button');
                });

            // Users who can edit the project can add resources from here.
            $browser->loginAs($siteAdmin)
                ->visit("/projects/{$this->project->id}")
                ->waitFor('@add-resources-button')
                ->click('@add-resources-button')
                ->waitFor('[data-test="edit-resources-dialog"][open]');
        });
    }

    public function testShowsOnlyAdministrators(): void
    {
        $this->addUserToProject(ProjectRole::ADMINISTRATOR, 'Alice', 'Admin');
        $this->addUserToProject(ProjectRole::USER, 'Bob', 'Basic');

        $this->browse(function (Browser $browser): void {
            $browser->visit("/projects/{$this->project->id}")
                ->whenAvailable('@administrators-section', function (Browser $browser): void {
                    $browser->assertSee('Alice Admin')
                        ->assertDontSee('Bob Basic');
                });
        });
    }

    public function testShowsRecentBuildCounts(): void
    {
        $this->createBuild(Carbon::now()->subMinutes(90));
        $this->createBuild(Carbon::now()->subDays(3));
        $this->createBuild(Carbon::now()->subDays(10));

        $this->browse(function (Browser $browser): void {
            $browser->visit("/projects/{$this->project->id}")
                ->whenAvailable('@dashboard-card', function (Browser $browser): void {
                    $browser->assertSeeIn('@builds-last-day', '1')
                        ->assertSeeIn('@builds-last-week', '2')
                        ->assertSeeIn('@last-submission', '1 hour ago')
                        ->assertMissing('@no-builds-message');
                });
        });
    }

    public function testDashboardLinkOpensMostRecentTestingDayWithBuilds(): void
    {
        // Project names may contain spaces and plus signs, which must be encoded in the link.
        $this->project->update([
            'name' => 'Home Page+' . Str::uuid()->toString(),
            // With an evening nightly time, builds started after it belong to the next testing day.
            'nightlytime' => '21:00:00',
        ]);
        $this->createBuild(Carbon::parse('2020-01-15 22:00:00'));
        $this->createBuild(Carbon::parse('2020-01-10 12:00:00'));

        $this->browse(function (Browser $browser): void {
            $browser->visit("/projects/{$this->project->id}")
                ->whenAvailable('@project-home-page', function (Browser $browser): void {
                    $browser->assertAttributeContains('@view-dashboard-button', 'href', 'index.php?project=' . rawurlencode($this->project->name) . '&date=2020-01-16')
                        ->assertSeeIn('@testing-day-start', 'Testing day starts at 21:00 UTC')
                        // Submissions older than a month show a date instead of a relative time.
                        ->assertSeeIn('@last-submission', '2020');
                });
        });
    }

    public function testNoBuilds(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit("/projects/{$this->project->id}")
                ->whenAvailable('@project-home-page', function (Browser $browser): void {
                    $browser->assertSeeIn('@no-builds-message', 'No builds have been submitted to this project yet.')
                        ->assertMissing('@builds-last-day')
                        ->assertMissing('@last-submission')
                        ->assertAttributeDoesntContain('@view-dashboard-button', 'href', 'date=');
                });
        });
    }

    public function testSettingsButtonOnlyVisibleToAdministrators(): void
    {
        $admin = $this->addUserToProject(ProjectRole::ADMINISTRATOR);
        $user = $this->addUserToProject(ProjectRole::USER);
        $siteAdmin = $this->createUser(true);

        $this->browse(function (Browser $browser) use ($admin, $user, $siteAdmin): void {
            $browser->visit("/projects/{$this->project->id}")
                ->whenAvailable('@project-home-page', function (Browser $browser): void {
                    $browser->assertMissing('@settings-button');
                });

            $browser->loginAs($user)
                ->visit("/projects/{$this->project->id}")
                ->whenAvailable('@project-home-page', function (Browser $browser): void {
                    $browser->assertMissing('@settings-button')
                        ->assertMissing('@edit-resources-button');
                });

            $browser->loginAs($admin)
                ->visit("/projects/{$this->project->id}")
                ->whenAvailable('@project-home-page', function (Browser $browser): void {
                    $browser->assertAttributeContains('@settings-button', 'href', "/projects/{$this->project->id}/settings")
                        ->assertVisible('@edit-resources-button');
                });

            $browser->loginAs($siteAdmin)
                ->visit("/projects/{$this->project->id}")
                ->whenAvailable('@project-home-page', function (Browser $browser): void {
                    $browser->assertVisible('@settings-button')
                        ->assertVisible('@edit-resources-button');
                });
        });
    }

    public function testShows404WhenProjectDoesNotExist(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/projects/123456789')
                ->assertSee('404')
                ->assertMissing('@project-home-page');
        });
    }

    public function testProtectedProjectRequiresLogin(): void
    {
        $this->project->update(['public' => Project::ACCESS_PROTECTED]);
        $user = $this->createUser();

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->visit("/projects/{$this->project->id}")
                ->assertSee('404')
                ->assertMissing('@project-home-page');

            $browser->loginAs($user)
                ->visit("/projects/{$this->project->id}")
                ->waitFor('@project-home-page')
                ->assertSeeIn('@project-visibility', 'Protected');
        });
    }

    public function testPrivateProjectOnlyVisibleToMembersAndAdministrators(): void
    {
        $this->project->update(['public' => Project::ACCESS_PRIVATE]);
        $nonMember = $this->createUser();
        $member = $this->addUserToProject(ProjectRole::USER);
        $siteAdmin = $this->createUser(true);

        $this->browse(function (Browser $browser) use ($nonMember, $member, $siteAdmin): void {
            $browser->visit("/projects/{$this->project->id}")
                ->assertSee('404')
                ->assertMissing('@project-home-page');

            $browser->loginAs($nonMember)
                ->visit("/projects/{$this->project->id}")
                ->assertSee('404')
                ->assertMissing('@project-home-page');

            $browser->loginAs($member)
                ->visit("/projects/{$this->project->id}")
                ->waitFor('@project-home-page')
                ->assertSeeIn('@project-visibility', 'Private');

            $browser->loginAs($siteAdmin)
                ->visit("/projects/{$this->project->id}")
                ->waitFor('@project-home-page');
        });
    }

    public function testHeaderLinksToProjectHomePage(): void
    {
        $this->project->update(['homeurl' => 'https://example.com']);

        $this->browse(function (Browser $browser): void {
            // The header is shared by all project pages, so check it on a page other than the home page.
            $browser->visit("/projects/{$this->project->id}/sites")
                ->waitFor('#headerlogo');

            $homeUrl = $browser->attribute('#headerlogo a', 'href');
            self::assertIsString($homeUrl);
            self::assertStringEndsWith("/projects/{$this->project->id}", $homeUrl);

            $menuLinks = $browser->script("return [...document.querySelectorAll('#navigation a')].map(a => [a.textContent.trim(), a.getAttribute('href')]);")[0];
            self::assertContains(['Home', $homeUrl], $menuLinks);
            self::assertContains(['Website', 'https://example.com'], $menuLinks);
        });
    }

    public function testEditResources(): void
    {
        $this->project->update([
            'homeurl' => 'https://example.com',
            'cvsurl' => 'https://gitlab.kitware.com/cmake/cmake',
        ]);
        $admin = $this->addUserToProject(ProjectRole::ADMINISTRATOR);

        $this->browse(function (Browser $browser) use ($admin): void {
            $this->openResourcesEditor($browser, $admin)
                ->within('@edit-resources-dialog', function (Browser $browser): void {
                    $browser->assertInputValue('@home-url-input', 'https://example.com')
                        ->assertInputValue('@vcs-url-input', 'https://gitlab.kitware.com/cmake/cmake')
                        ->assertInputValue('@documentation-url-input', '')
                        ->type('@documentation-url-input', 'https://example.com/docs')
                        ->select('@vcs-viewer-input', 'GITLAB')
                        // Clear with key presses, since WebDriver's clear() doesn't trigger Vue's input listener.
                        ->keys('@home-url-input', ['{control}', 'a'], '{backspace}')
                        ->click('@edit-resources-save-button');
                })
                ->waitUntilMissing('[data-test="edit-resources-dialog"][open]')
                // The page shows the new resources without reloading.
                ->waitFor('@documentation-link')
                ->assertAttribute('@documentation-link', 'href', 'https://example.com/docs')
                ->assertMissing('@website-link')
                ->assertPresent('[data-test="repository-link"] svg[data-icon="gitlab"]');
        });

        $this->project->refresh();
        self::assertSame('https://example.com/docs', $this->project->documentationurl);
        self::assertNull($this->project->homeurl);
        self::assertSame('gitlab', $this->project->cvsviewertype);
        self::assertSame('https://gitlab.kitware.com/cmake/cmake', $this->project->cvsurl);
    }

    public function testEditResourcesRejectsIncompleteUrls(): void
    {
        $admin = $this->addUserToProject(ProjectRole::ADMINISTRATOR);

        $this->browse(function (Browser $browser) use ($admin): void {
            $this->openResourcesEditor($browser, $admin)
                ->within('@edit-resources-dialog', function (Browser $browser): void {
                    $browser->type('@home-url-input', 'example.com')
                        ->type('@test-data-url-input', 'https://example.com/test data')
                        ->click('@edit-resources-save-button')
                        ->waitForText('Enter a full URL, such as https://example.com.')
                        ->assertFocused('@home-url-input')
                        ->assertAttributeContains('@home-url-input', 'class', 'tw-input-error')
                        ->assertAttributeContains('@test-data-url-input', 'class', 'tw-input-error')
                        // Editing a field clears its error.
                        ->type('@home-url-input', '/')
                        ->assertAttributeDoesntContain('@home-url-input', 'class', 'tw-input-error')
                        ->assertAttributeContains('@test-data-url-input', 'class', 'tw-input-error');
                })
                ->assertPresent('[data-test="edit-resources-dialog"][open]');
        });

        $this->project->refresh();
        self::assertNull($this->project->homeurl);
        self::assertNull($this->project->testingdataurl);
    }

    public function testEditResourcesExplainsUrlsRejectedByTheServer(): void
    {
        $admin = $this->addUserToProject(ProjectRole::ADMINISTRATOR);

        $this->browse(function (Browser $browser) use ($admin): void {
            $this->openResourcesEditor($browser, $admin)
                ->within('@edit-resources-dialog', function (Browser $browser): void {
                    // This looks like a URL, but the server doesn't accept the misspelled scheme.
                    $browser->type('@home-url-input', 'htps://example.com')
                        ->click('@edit-resources-save-button')
                        ->waitFor('@edit-resources-error')
                        ->assertSeeIn('@edit-resources-error', 'Check that each URL is complete, such as https://example.com, and try again.')
                        ->assertDontSeeIn('@edit-resources-error', 'Internal server error')
                        ->assertInputValue('@home-url-input', 'htps://example.com');
                })
                ->assertPresent('[data-test="edit-resources-dialog"][open]');
        });

        self::assertNull($this->project->refresh()->homeurl);
    }

    public function testEditResourcesExplainsMissingPermission(): void
    {
        $admin = $this->addUserToProject(ProjectRole::ADMINISTRATOR);

        $this->browse(function (Browser $browser) use ($admin): void {
            $this->openResourcesEditor($browser, $admin);

            // Simulate the user losing access while the page is open.
            $this->project->users()->updateExistingPivot($admin->id, ['role' => ProjectRole::USER]);

            $browser->within('@edit-resources-dialog', function (Browser $browser): void {
                $browser->type('@home-url-input', 'https://example.com')
                    ->click('@edit-resources-save-button')
                    ->waitFor('@edit-resources-error')
                    ->assertSeeIn('@edit-resources-error', 'You do not have permission to edit this project.');
            });
        });

        self::assertNull($this->project->refresh()->homeurl);
    }

    public function testEditResourcesDiscardsChangesWhenCancelled(): void
    {
        $this->project->update(['homeurl' => 'https://example.com']);
        $admin = $this->addUserToProject(ProjectRole::ADMINISTRATOR);

        $this->browse(function (Browser $browser) use ($admin): void {
            $this->openResourcesEditor($browser, $admin)
                ->within('@edit-resources-dialog', function (Browser $browser): void {
                    $browser->type('@documentation-url-input', 'not a url')
                        ->click('@edit-resources-save-button')
                        ->waitForText('Enter a full URL, such as https://example.com.')
                        ->click('@edit-resources-cancel-button');
                })
                ->waitUntilMissing('[data-test="edit-resources-dialog"][open]')
                ->click('@edit-resources-button')
                ->waitFor('[data-test="edit-resources-dialog"][open]')
                ->within('@edit-resources-dialog', function (Browser $browser): void {
                    $browser->assertInputValue('@home-url-input', 'https://example.com')
                        ->assertInputValue('@documentation-url-input', '')
                        ->assertDontSee('Enter a full URL, such as https://example.com.');
                });
        });

        self::assertNull($this->project->refresh()->documentationurl);
    }
}
