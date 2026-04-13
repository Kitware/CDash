<?php

namespace Tests\Browser\Pages;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class ProjectSettingsPageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesUsers;

    private Project $project;
    private User $admin;
    /** @var array<Model> */
    private array $otherModels = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
        $this->admin = $this->makeAdminUser();
    }

    public function tearDown(): void
    {
        $this->project->delete();

        foreach ($this->otherModels as $model) {
            $model->delete();
        }
        $this->otherModels = [];

        parent::tearDown();
    }

    public function testProhibitsAccessByAnonymousUsers(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit("/projects/{$this->project->id}/settings")
                ->assertSee('403')
            ;
        });
    }

    public function testShows404WhenProjectDoesNotExist(): void
    {
        $this->actingAs($this->admin)->browse(function (Browser $browser): void {
            $browser->visit('/projects/123456789/settings')
                ->assertSee('404')
            ;
        });
    }

    public function testCannotChangeProjectNameToNameOfOtherProject(): void
    {
        $otherProject = $this->makePublicProject();
        $this->otherModels[] = $otherProject;

        $this->browse(function (Browser $browser) use ($otherProject): void {
            $originalProjectName = $this->project->name;

            $browser->loginAs($this->admin)
                ->visit("/projects/{$this->project->id}/settings")
                ->whenAvailable('@general-tab', function (Browser $browser) use ($otherProject): void {
                    $browser->assertValue('@name-input', $this->project->name)
                        ->clear('@name-input')
                        ->type('@name-input', $otherProject->name)
                        ->click('@save-button')
                        ->waitFor('@error-message')
                        ->assertSee('A project with this name already exists')
                    ;
                });

            self::assertSame($originalProjectName, $this->project->refresh()->name);
        });
    }

    /**
     * @return array{
     *     array{
     *         string, mixed, string, mixed,
     *     }
     * }
     */
    public static function canChangeGeneralTabFieldCases(): array
    {
        $name = Str::uuid()->toString();
        $description = Str::uuid()->toString();
        $banner = Str::uuid()->toString();
        $homeurl = fake()->url();
        $documentationurl = fake()->url();
        $testdataurl = fake()->url();
        $vcsurl = fake()->url();
        $cmakeprojectroot = Str::uuid()->toString();
        $bugtrackerurl = fake()->url();
        $bugtrackernewissueurl = fake()->url();
        $emailmaxitems = fake()->numberBetween(1, 100);
        $emailmaxchars = fake()->numberBetween(1, 100);
        $coveragethreshold = fake()->numberBetween(1, 100);
        $testtimemaxstatus = fake()->numberBetween(1, 100);
        $testtimestdthreshold = fake()->numberBetween(1, 100);

        return [
            ['@name-input', $name, 'name', $name, 'string'],
            ['@description-input', $description, 'description', $description, 'string'],
            ['@description-input', '', 'description', null, 'string'],
            ['@authenticated-submissions-input', true, 'authenticatesubmissions', true, 'checkbox'],
            ['@authenticated-submissions-input', false, 'authenticatesubmissions', false, 'checkbox'],
            ['visibility', 'PUBLIC', 'public', Project::ACCESS_PUBLIC, 'radio'],
            ['visibility', 'PROTECTED', 'public', Project::ACCESS_PROTECTED, 'radio'],
            ['visibility', 'PRIVATE', 'public', Project::ACCESS_PRIVATE, 'radio'],
            ['@banner-input', $banner, 'banner', $banner, 'string'],
            ['@banner-input', '', 'banner', null, 'string'],
            ['@display-labels-input', true, 'displaylabels', true, 'checkbox'],
            ['@display-labels-input', false, 'displaylabels', false, 'checkbox'],
            ['@nightly-time-input', '23:01:01', 'nightlytime', '23:01:01', 'string'],
            ['@autoremove-time-frame-input', 7, 'autoremovetimeframe', 7, 'string'],
            ['@autoremove-max-builds-input', 100, 'autoremovemaxbuilds', 100, 'string'],
            ['@file-upload-limit-input', 100, 'uploadquota', 107374182400, 'string'],
            ['@home-url-input', $homeurl, 'homeurl', $homeurl, 'string'],
            ['@home-url-input', '', 'homeurl', null, 'string'],
            ['@documentation-url-input', $documentationurl, 'documentationurl', $documentationurl, 'string'],
            ['@documentation-url-input', '', 'documentationurl', null, 'string'],
            ['@test-data-url-input', $testdataurl, 'testingdataurl', $testdataurl, 'string'],
            ['@test-data-url-input', '', 'testingdataurl', null, 'string'],
            ['@vcs-viewer-input', 'None', 'vcsviewer', null, 'select'],
            ['@vcs-viewer-input', 'GITHUB', 'cvsviewertype', 'github', 'select'],
            ['@vcs-viewer-input', 'GITLAB', 'cvsviewertype', 'gitlab', 'select'],
            ['@vcs-url-input', $vcsurl, 'cvsurl', $vcsurl, 'string'],
            ['@vcs-url-input', '', 'cvsurl', null, 'string'],
            ['@cmake-project-root-input', $cmakeprojectroot, 'cmakeprojectroot', $cmakeprojectroot, 'string'],
            ['@cmake-project-root-input', '', 'cmakeprojectroot', null, 'string'],
            ['@bug-tracker-input', 'None', 'bugtrackertype', null, 'select'],
            ['@bug-tracker-input', 'GITHUB', 'bugtrackertype', 'GitHub', 'select'],
            ['@bug-tracker-input', 'JIRA', 'bugtrackertype', 'JIRA', 'select'],
            ['@bug-tracker-url-input', $bugtrackerurl, 'bugtrackerurl', $bugtrackerurl, 'string'],
            ['@bug-tracker-url-input', '', 'bugtrackerurl', null, 'string'],
            ['@bug-tracker-new-issue-url-input', $bugtrackernewissueurl, 'bugtrackernewissueurl', $bugtrackernewissueurl, 'string'],
            ['@bug-tracker-new-issue-url-input', '', 'bugtrackernewissueurl', null, 'string'],
            ['@email-low-coverage-input', true, 'emaillowcoverage', true, 'checkbox'],
            ['@email-low-coverage-input', false, 'emaillowcoverage', false, 'checkbox'],
            ['@email-test-timing-changed-input', true, 'emailtesttimingchanged', true, 'checkbox'],
            ['@email-test-timing-changed-input', false, 'emailtesttimingchanged', false, 'checkbox'],
            ['@email-broken-submissions-input', true, 'emailbrokensubmission', true, 'checkbox'],
            ['@email-broken-submissions-input', false, 'emailbrokensubmission', false, 'checkbox'],
            ['@email-redundant-failures-input', true, 'emailredundantfailures', true, 'checkbox'],
            ['@email-redundant-failures-input', false, 'emailredundantfailures', false, 'checkbox'],
            ['@email-max-items-input', $emailmaxitems, 'emailmaxitems', $emailmaxitems, 'string'],
            ['@email-max-characters-input', $emailmaxchars, 'emailmaxchars', $emailmaxchars, 'string'],
            ['@coverage-threshold-input', $coveragethreshold, 'coveragethreshold', $coveragethreshold, 'string'],
            ['@show-coverage-code-input', true, 'showcoveragecode', true, 'checkbox'],
            ['@show-coverage-code-input', false, 'showcoveragecode', false, 'checkbox'],
            ['@enable-test-timing-input', true, 'showtesttime', true, 'checkbox'],
            ['@enable-test-timing-input', false, 'showtesttime', false, 'checkbox'],
            ['@time-status-failure-threshold-input', $testtimemaxstatus, 'testtimemaxstatus', $testtimemaxstatus, 'string'],
            ['@test-time-std-threshold-input', $testtimestdthreshold, 'testtimestdthreshold', $testtimestdthreshold, 'string'],
        ];
    }

    #[DataProvider('canChangeGeneralTabFieldCases')]
    public function testCanChangeGeneralTabField(
        string $testId,
        mixed $formValue,
        string $modelAttribute,
        mixed $modelValue,
        string $inputType,
    ): void {
        $this->project->showtesttime = true;
        $this->project->save();

        $this->browse(function (Browser $browser) use ($inputType, $formValue, $testId): void {
            $browser->loginAs($this->admin)
                ->visit("/projects/{$this->project->id}/settings")
                ->whenAvailable('@general-tab', function (Browser $browser) use ($inputType, $formValue, $testId): void {
                    if ($inputType === 'checkbox') {
                        $formValue ? $browser->check($testId) : $browser->uncheck($testId);
                    } elseif ($inputType === 'select') {
                        $browser->select($testId, $formValue);
                    } elseif ($inputType === 'radio') {
                        $browser->radio($testId, $formValue);
                    } else {
                        $browser->clear($testId)->type($testId, $formValue);
                    }

                    $browser->click('@save-button')
                        ->waitFor('@success-message');
                })
                ->refresh()
                ->whenAvailable('@general-tab', function (Browser $browser) use ($inputType, $formValue, $testId): void {
                    if ($inputType === 'checkbox') {
                        $formValue ? $browser->assertChecked($testId) : $browser->assertNotChecked($testId);
                    } elseif ($inputType === 'radio') {
                        $browser->assertRadioSelected($testId, $formValue);
                    } else {
                        $browser->assertValue($testId, $formValue);
                    }
                });
        });

        self::assertEquals($modelValue, $this->project->refresh()->getAttribute($modelAttribute));
    }

    public function testDisablesTestTimingFieldsWhenTestTimingDisabled(): void
    {
        $this->project->showtesttime = false;
        $this->project->save();

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->admin)
                ->visit("/projects/{$this->project->id}/settings")
                ->whenAvailable('@general-tab', function (Browser $browser): void {
                    $browser->assertNotChecked('@test-time-std-threshold-input')
                        ->assertDisabled('@time-status-failure-threshold-input')
                        ->assertDisabled('@test-time-std-threshold-input')
                        ->assertDisabled('@test-time-std-multiplier-input')
                        ->check('@enable-test-timing-input')
                        ->assertEnabled('@time-status-failure-threshold-input')
                        ->assertEnabled('@test-time-std-threshold-input')
                        ->assertEnabled('@test-time-std-multiplier-input')
                    ;
                });
        });
    }

    public function testShowsMessageWhenNoIntegrations(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->admin)
                ->visit("/projects/{$this->project->id}/settings")
                ->waitFor('@integrations-tab-link')
                ->click('@integrations-tab-link')
                ->whenAvailable('@integrations-tab', function (Browser $browser): void {
                    $browser->waitFor('@no-integrations-message')
                        ->assertVisible('@no-integrations-message');
                });
        });
    }

    public function testCanCreateAndDeleteIntegrations(): void
    {
        $this->browse(function (Browser $browser): void {
            $url = fake()->url();
            $username = Str::uuid()->toString();
            $password = Str::uuid()->toString();
            $branch = Str::uuid()->toString();

            $browser->loginAs($this->admin)
                ->visit("/projects/{$this->project->id}/settings")
                ->waitFor('@integrations-tab-link')
                ->click('@integrations-tab-link')
                ->whenAvailable('@integrations-tab', function (Browser $browser) use ($branch, $password, $username, $url): void {
                    $browser->assertButtonDisabled('@create-repository-button')
                        ->type('@repository-url-input', $url)
                        ->type('@repository-username-input', $username)
                        ->type('@repository-password-input', $password)
                        ->type('@repository-branch-input', $branch)
                        ->assertButtonEnabled('@create-repository-button')
                        ->click('@create-repository-button')
                        ->waitForText($url)
                        ->assertSee($username)
                        ->assertSee($branch)
                        ->assertDisabled('@create-repository-button')
                    ;
                })
                ->refresh()
                ->waitFor('@integrations-tab-link')
                ->click('@integrations-tab-link')
                ->whenAvailable('@integrations-tab', function (Browser $browser) use ($branch, $username, $url): void {
                    $browser->assertButtonDisabled('@create-repository-button')
                        ->waitForText($url)
                        ->assertSee($username)
                        ->assertSee($branch)
                        ->click('@delete-repository-button')
                        ->waitUntilMissing('@delete-repository-button')
                        ->assertDontSee($url)
                        ->assertDontSee($username)
                        ->assertDontSee($branch)
                    ;
                })
                ->refresh()
                ->waitFor('@integrations-tab-link')
                ->click('@integrations-tab-link')
                ->whenAvailable('@integrations-tab', function (Browser $browser) use ($branch, $username, $url): void {
                    $browser->assertButtonDisabled('@create-repository-button')
                        ->assertDontSee($url)
                        ->assertDontSee($username)
                        ->assertDontSee($branch)
                    ;
                });
        });
    }
}
