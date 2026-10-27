<?php

namespace Tests\Browser\Pages;

use App\Enums\ProjectRole;
use App\Models\Build;
use App\Models\BuildGroup;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\ProjectService;
use App\Utils\TestingDay;
use CDash\Model\Project as LegacyProject;
use Facebook\WebDriver\Interactions\WebDriverActions;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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
        Storage::deleteDirectory("logos/{$this->project->id}");

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

    /**
     * @param array<string,mixed> $attributes
     */
    private function createBuild(Carbon $time, array $attributes = []): Build
    {
        $this->site ??= Site::factory()->create();

        return $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'siteid' => $this->site->id,
            'starttime' => $time,
            'endtime' => $time,
            'submittime' => $time,
            ...$attributes,
        ]);
    }

    private function buildGroup(string $name): BuildGroup
    {
        return $this->project->buildgroups()->where('name', $name)->firstOrFail();
    }

    /**
     * Click near the right edge of a histogram, which is the current testing day's column.
     */
    private function clickRightmostBar(Browser $browser, string $selector): void
    {
        $chart = $browser->element($selector);
        self::assertNotNull($chart);

        // Offsets are relative to the center of the chart.
        (new WebDriverActions($browser->driver))
            ->moveToElement($chart, intdiv($chart->getSize()->getWidth(), 2) - 6, 0)
            ->click()
            ->perform();
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

    /**
     * Log in as a project administrator without contact information, and open the contact information editor.
     */
    private function openContactInfoEditor(Browser $browser, User $admin): Browser
    {
        return $browser->loginAs($admin)
            ->visit("/projects/{$this->project->id}")
            ->waitFor('@add-contact-button')
            ->click('@add-contact-button')
            ->waitFor('[data-test="edit-contact-dialog"][open]');
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
            'licenseurl' => 'https://example.com/license',
            'securitypolicyurl' => 'https://example.com/security',
            'testingdataurl' => 'https://example.com/data',
            'contact_info' => 'maintainers@example.com',
        ]);
        $this->addUserToProject(ProjectRole::ADMINISTRATOR);

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
                        ->assertAttribute('@license-link', 'href', 'https://example.com/license')
                        ->assertPresent('[data-test="license-link"] svg[data-icon="scale-balanced"]')
                        ->assertAttribute('@security-policy-link', 'href', 'https://example.com/security')
                        ->assertPresent('[data-test="security-policy-link"] svg[data-icon="shield-halved"]')
                        ->assertAttribute('@test-data-link', 'href', 'https://example.com/data')
                        ->assertMissing('@no-resources-message')
                        ->assertMissing('@edit-resources-button')
                        ->assertSeeIn('@contact-info', 'maintainers@example.com')
                        ->assertPresent('[data-test="resources-section"] + [data-test="contact-section"] + [data-test="administrators-section"]')
                        ->assertMissing('@edit-contact-button');
                });

            $resources = array_map(fn ($link): string => $link->getText(), $browser->elements('@resources-section a'));
            self::assertSame(['Repository', 'Website', 'Documentation', 'Issue Tracker', 'Report an Issue', 'License', 'Security Policy', 'Test Data'], $resources);
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

        // Each user has their own browser.  Otherwise, responses to requests which the previous user's page
        // is still loading, such as the histograms, could restore that user's session after logging in.
        $this->browse(function (Browser $guestBrowser, Browser $siteAdminBrowser) use ($siteAdmin): void {
            $guestBrowser->visit("/projects/{$this->project->id}")
                ->whenAvailable('@project-home-page', function (Browser $browser): void {
                    $browser->assertMissing('@project-description')
                        ->assertMissing('@administrators-section')
                        ->assertMissing('@contact-section')
                        ->assertSeeIn('@resources-section', 'No resources have been added to this project.')
                        ->assertMissing('@add-resources-button');
                });

            // Users who can edit the project can add resources and contact information from here.
            $siteAdminBrowser->loginAs($siteAdmin)
                ->visit("/projects/{$this->project->id}")
                ->waitFor('@add-resources-button')
                ->assertSeeIn('@contact-section', 'No contact information has been added to this project.')
                ->assertPresent('@add-contact-button')
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

    public function testActivityChartShowsPassingAndFailingBuilds(): void
    {
        // Not run tests don't make a build fail.
        $this->createBuild(Carbon::now()->subMinutes(90), ['configureerrors' => 0, 'builderrors' => 0, 'testfailed' => 0, 'testnotrun' => 2]);
        $this->createBuild(Carbon::now()->subDays(2));
        $this->createBuild(Carbon::now()->subDays(3), ['configureerrors' => 1]);
        $this->createBuild(Carbon::now()->subDays(4), ['builderrors' => 1]);
        $this->createBuild(Carbon::now()->subDays(5), ['testfailed' => 1]);
        // Builds before the 30 day window aren't shown.
        $this->createBuild(Carbon::now()->subDays(40), ['testfailed' => 1]);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/projects/{$this->project->id}")
                ->whenAvailable('@dashboard-card', function (Browser $browser): void {
                    $browser->waitFor('@build-histogram')
                        ->assertAttribute('@build-histogram', 'data-test-passed-count', '2')
                        ->assertAttribute('@build-histogram', 'data-test-failed-count', '3')
                        ->assertSeeIn('@build-histogram-legend', 'Last 30 days')
                        ->assertSeeIn('@build-histogram-legend', 'Passed')
                        ->assertSeeIn('@build-histogram-legend', 'Failed');
                });
        });
    }

    public function testShowsActiveBuildGroups(): void
    {
        $nightly = $this->buildGroup('Nightly');
        $nightly->builds()->attach([
            $this->createBuild(Carbon::now()->subMinutes(90))->id,
            $this->createBuild(Carbon::now()->subDays(3), ['testfailed' => 1])->id,
        ]);
        $this->buildGroup('Continuous')->builds()->attach($this->createBuild(Carbon::now()->subDays(40)));

        // Expired build groups aren't shown.
        BuildGroup::factory()->for($this->project)->create([
            'name' => 'Expired',
            'endtime' => Carbon::now()->subDay(),
        ])->builds()->attach($this->createBuild(Carbon::now()->subMinutes(30)));

        $this->browse(function (Browser $browser): void {
            $browser->visit("/projects/{$this->project->id}")
                ->waitFor('@build-groups-section')
                ->assertMissing('[data-test="build-group-card"]:nth-child(4)')
                ->assertDontSeeIn('@build-groups-section', 'Expired');

            // The histograms load separately from the rest of each card.
            $browser->within('[data-test="build-group-card"]:nth-child(1)', function (Browser $browser): void {
                $browser->waitFor('@build-histogram')
                    ->assertSeeIn('@build-group-name', 'Nightly')
                    ->assertSeeIn('@build-group-description', 'Nightly builds')
                    ->assertSeeIn('@group-builds-last-day', '1')
                    ->assertSeeIn('@group-builds-last-week', '2')
                    ->assertSeeIn('@group-last-submission', '1 hour ago')
                    ->assertAttribute('@build-histogram', 'data-test-passed-count', '1')
                    ->assertAttribute('@build-histogram', 'data-test-failed-count', '1');
            })->within('[data-test="build-group-card"]:nth-child(2)', function (Browser $browser): void {
                // Only builds in the last 30 days are searched for the last submission.
                $browser->waitFor('@build-histogram-empty')
                    ->assertSeeIn('@build-group-name', 'Continuous')
                    ->assertSeeIn('@group-builds-last-day', '0')
                    ->assertSeeIn('@group-builds-last-week', '0')
                    ->assertSeeIn('@group-last-submission', 'More than 30 days ago')
                    ->assertSeeIn('@build-histogram-empty', 'No builds in the last 30 days');
            })->within('[data-test="build-group-card"]:nth-child(3)', function (Browser $browser): void {
                $browser->waitFor('@build-histogram-empty')
                    ->assertSeeIn('@build-group-name', 'Experimental')
                    ->assertSeeIn('@group-builds-last-day', '0')
                    ->assertSeeIn('@group-last-submission', 'More than 30 days ago');
            });
        });
    }

    public function testClickingABarOpensItsTestingDay(): void
    {
        // Testing days start two hours ago, so the builds one and three hours ago are on different testing days.
        $this->project->update([
            'nightlytime' => Carbon::now('UTC')->subHours(2)->format('H:i:s') . ' UTC',
        ]);
        $this->buildGroup('Nightly')->builds()->attach([
            $this->createBuild(Carbon::now()->subHour())->id,
            $this->createBuild(Carbon::now()->subHours(3))->id,
        ]);

        $legacyProject = new LegacyProject();
        $legacyProject->Id = $this->project->id;
        $currentTestingDay = TestingDay::get($legacyProject, Carbon::now('UTC')->toDateTimeString());

        $this->browse(function (Browser $browser) use ($currentTestingDay): void {
            $browser->visit("/projects/{$this->project->id}")
                ->whenAvailable('@dashboard-card', function (Browser $browser): void {
                    $browser->waitFor('@build-histogram')
                        ->assertAttribute('@build-histogram', 'data-test-passed-count', '2');
                });

            $this->clickRightmostBar($browser, '[data-test="dashboard-card"] [data-test="build-histogram"]');
            $browser->waitForLocation('/index.php')
                ->assertQueryStringHas('project', $this->project->name)
                ->assertQueryStringHas('date', $currentTestingDay);

            $groupChart = '[data-test="build-group-card"]:nth-child(1) [data-test="build-histogram"]';
            $browser->visit("/projects/{$this->project->id}")
                ->waitFor($groupChart);
            $this->clickRightmostBar($browser, $groupChart);
            $browser->waitForLocation('/viewBuildGroup.php')
                ->assertQueryStringHas('buildgroup', 'Nightly')
                ->assertQueryStringHas('date', $currentTestingDay);
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
        // So can build group names.
        BuildGroup::factory()->for($this->project)->create(['name' => 'Release Candidates+']);

        $this->browse(function (Browser $browser): void {
            $dashboardUrl = 'index.php?project=' . rawurlencode($this->project->name) . '&date=2020-01-16';

            $browser->visit("/projects/{$this->project->id}")
                ->whenAvailable('@project-home-page', function (Browser $browser) use ($dashboardUrl): void {
                    $browser->assertAttributeContains('@view-dashboard-button', 'href', $dashboardUrl)
                        ->assertAttributeContains('@activity-dashboard-link', 'href', $dashboardUrl)
                        ->assertSeeIn('@testing-day-start', 'Testing day starts at 21:00 UTC')
                        // Submissions older than a month show a date instead of a relative time.
                        ->assertSeeIn('@last-submission', '2020');
                })
                ->waitFor('@build-groups-section');

            // Build groups link to their page on the dashboard for the same testing day.
            $projectParameter = 'project=' . rawurlencode($this->project->name);
            foreach (['Nightly', 'Continuous', 'Experimental', 'Release%20Candidates%2B'] as $position => $groupParameter) {
                $browser->within('[data-test="build-group-card"]:nth-child(' . ($position + 1) . ')', function (Browser $browser) use ($projectParameter, $groupParameter): void {
                    $browser->assertAttributeContains('@build-group-link', 'href', "/viewBuildGroup.php?{$projectParameter}&buildgroup={$groupParameter}&date=2020-01-16");
                });
            }
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
                        ->assertAttributeDoesntContain('@view-dashboard-button', 'href', 'date=')
                        ->assertAttributeDoesntContain('@activity-dashboard-link', 'href', 'date=');
                })
                ->waitFor('@build-groups-section')
                ->assertAttributeDoesntContain('@build-group-link', 'href', 'date=');
        });
    }

    public function testSettingsButtonOnlyVisibleToAdministrators(): void
    {
        $admin = $this->addUserToProject(ProjectRole::ADMINISTRATOR);
        $user = $this->addUserToProject(ProjectRole::USER);
        $siteAdmin = $this->createUser(true);

        // Each user has their own browser.  Otherwise, responses to requests which the previous user's page
        // is still loading, such as the histograms, could restore that user's session after logging in.
        $this->browse(function (Browser $guestBrowser, Browser $userBrowser, Browser $adminBrowser, Browser $siteAdminBrowser) use ($admin, $user, $siteAdmin): void {
            $guestBrowser->visit("/projects/{$this->project->id}")
                ->whenAvailable('@project-home-page', function (Browser $browser): void {
                    $browser->assertMissing('@settings-button');
                });

            $userBrowser->loginAs($user)
                ->visit("/projects/{$this->project->id}")
                ->whenAvailable('@project-home-page', function (Browser $browser): void {
                    $browser->assertMissing('@settings-button')
                        ->assertMissing('@edit-resources-button');
                });

            $adminBrowser->loginAs($admin)
                ->visit("/projects/{$this->project->id}")
                ->whenAvailable('@project-home-page', function (Browser $browser): void {
                    $browser->assertAttributeContains('@settings-button', 'href', "/projects/{$this->project->id}/settings")
                        ->assertVisible('@edit-resources-button');
                });

            $siteAdminBrowser->loginAs($siteAdmin)
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

        $this->browse(function (Browser $browser, Browser $siteAdminBrowser) use ($nonMember, $member, $siteAdmin): void {
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

            // The site administrator has their own browser.  Otherwise, responses to requests which the member's
            // page is still loading, such as the histograms, could restore the member's session after logging in.
            $siteAdminBrowser->loginAs($siteAdmin)
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

            // The links are in hidden dropdown menus, so getText() would return an empty string.
            $menuLinks = [];
            foreach ($browser->elements('#navigation a') as $link) {
                $text = $link->getDomProperty('textContent');
                self::assertIsString($text);
                $menuLinks[] = [trim($text), $link->getAttribute('href')];
            }
            self::assertContains(['Home', $homeUrl], $menuLinks);
            self::assertContains(['Website', 'https://example.com'], $menuLinks);
        });
    }

    /**
     * @throws FileNotFoundException
     */
    public function testShowsProjectLogo(): void
    {
        ProjectService::setLogo($this->project, UploadedFile::fake()->image('logo.png', 40, 40));
        $logoPath = "/projects/{$this->project->id}/logo?v=";

        $this->browse(function (Browser $browser) use ($logoPath): void {
            $logoSelector = "img[alt=\"{$this->project->name} logo\"]";
            $browser->visit("/projects/{$this->project->id}")
                ->waitFor($logoSelector)
                ->assertAttributeContains($logoSelector, 'src', $logoPath)
                ->waitUntil("document.querySelector('{$logoSelector}').naturalWidth === 40");

            // The header is shared by all project pages, so check it on a page other than the home page.
            $browser->visit("/projects/{$this->project->id}/sites")
                ->waitFor('#projectlogo')
                ->assertAttributeContains('#projectlogo', 'src', $logoPath)
                ->waitUntil("document.querySelector('#projectlogo').naturalWidth === 40");
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
                        ->assertInputValue('@license-url-input', '')
                        ->type('@license-url-input', 'https://example.com/license')
                        ->assertInputValue('@security-policy-url-input', '')
                        ->type('@security-policy-url-input', 'https://example.com/security')
                        ->select('@vcs-viewer-input', 'GITLAB')
                        // Clear with key presses, since WebDriver's clear() doesn't trigger Vue's input listener.
                        ->keys('@home-url-input', ['{control}', 'a'], '{backspace}')
                        ->click('@edit-resources-save-button');
                })
                ->waitUntilMissing('[data-test="edit-resources-dialog"][open]')
                // The page shows the new resources without reloading.
                ->waitFor('@documentation-link')
                ->assertAttribute('@documentation-link', 'href', 'https://example.com/docs')
                ->assertAttribute('@license-link', 'href', 'https://example.com/license')
                ->assertAttribute('@security-policy-link', 'href', 'https://example.com/security')
                ->assertMissing('@website-link')
                ->assertPresent('[data-test="repository-link"] svg[data-icon="gitlab"]');
        });

        $this->project->refresh();
        self::assertSame('https://example.com/docs', $this->project->documentationurl);
        self::assertSame('https://example.com/license', $this->project->licenseurl);
        self::assertSame('https://example.com/security', $this->project->securitypolicyurl);
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

    public function testEditContactInfo(): void
    {
        $admin = $this->addUserToProject(ProjectRole::ADMINISTRATOR);

        $this->browse(function (Browser $browser) use ($admin): void {
            $this->openContactInfoEditor($browser, $admin)
                ->within('@edit-contact-dialog', function (Browser $browser): void {
                    $browser->assertInputValue('@contact-info-input', '')
                        ->type('@contact-info-input', 'maintainers@example.com')
                        ->click('@edit-contact-save-button');
                })
                ->waitUntilMissing('[data-test="edit-contact-dialog"][open]')
                // The page shows the new contact information without reloading.
                ->waitFor('@contact-info')
                ->assertSeeIn('@contact-info', 'maintainers@example.com');

            self::assertSame('maintainers@example.com', $this->project->refresh()->contact_info);

            // An empty field removes the contact information.
            $browser->click('@edit-contact-button')
                ->waitFor('[data-test="edit-contact-dialog"][open]')
                ->within('@edit-contact-dialog', function (Browser $browser): void {
                    $browser->assertInputValue('@contact-info-input', 'maintainers@example.com')
                        ->keys('@contact-info-input', ['{control}', 'a'], '{backspace}')
                        ->click('@edit-contact-save-button');
                })
                ->waitUntilMissing('[data-test="edit-contact-dialog"][open]')
                ->waitFor('@no-contact-message');
        });

        self::assertNull($this->project->refresh()->contact_info);
    }

    public function testEditContactInfoShowsRemainingCharactersNearTheLimit(): void
    {
        $admin = $this->addUserToProject(ProjectRole::ADMINISTRATOR);

        $this->browse(function (Browser $browser) use ($admin): void {
            $this->openContactInfoEditor($browser, $admin)
                ->within('@edit-contact-dialog', function (Browser $browser): void {
                    $browser->type('@contact-info-input', str_repeat('a', 80))
                        ->assertMissing('@contact-info-input-remaining-characters')
                        ->append('@contact-info-input', 'a')
                        ->assertSeeIn('@contact-info-input-remaining-characters', '19 characters remaining')
                        ->append('@contact-info-input', str_repeat('a', 18))
                        ->assertSeeIn('@contact-info-input-remaining-characters', '1 character remaining')
                        // The field stops at 100 characters.
                        ->append('@contact-info-input', 'aa')
                        ->assertInputValue('@contact-info-input', str_repeat('a', 100))
                        ->assertSeeIn('@contact-info-input-remaining-characters', '0 characters remaining')
                        // Deleting characters updates the count, and hides it again further from the limit.
                        ->keys('@contact-info-input', '{backspace}', '{backspace}', '{backspace}')
                        ->assertSeeIn('@contact-info-input-remaining-characters', '3 characters remaining')
                        ->keys('@contact-info-input', ['{control}', 'a'], '{backspace}')
                        ->assertMissing('@contact-info-input-remaining-characters');
                });
        });
    }

    public function testEditContactInfoExplainsMissingPermission(): void
    {
        $admin = $this->addUserToProject(ProjectRole::ADMINISTRATOR);

        $this->browse(function (Browser $browser) use ($admin): void {
            $this->openContactInfoEditor($browser, $admin);

            // Simulate the user losing access while the page is open.
            $this->project->users()->updateExistingPivot($admin->id, ['role' => ProjectRole::USER]);

            $browser->within('@edit-contact-dialog', function (Browser $browser): void {
                $browser->type('@contact-info-input', 'maintainers@example.com')
                    ->click('@edit-contact-save-button')
                    ->waitFor('@edit-contact-error')
                    ->assertSeeIn('@edit-contact-error', 'You do not have permission to edit this project.');
            });
        });

        self::assertNull($this->project->refresh()->contact_info);
    }
}
