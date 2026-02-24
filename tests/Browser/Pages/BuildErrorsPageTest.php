<?php

namespace Tests\Browser\Pages;

use App\Http\Submission\Traits\UpdatesSiteInformation;
use App\Models\Build;
use App\Models\BuildError;
use App\Models\Label;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Models\SubProject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;

class BuildErrorsPageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesSites;
    use UpdatesSiteInformation;

    private Project $project;

    private Site $site;

    private SubProject $subproject1;
    private SubProject $subproject2;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        $buildgroup1 = $this->project->buildgroups()->create([
            'description' => Str::uuid()->toString(),
        ]);

        $buildgroup2 = $this->project->buildgroups()->create([
            'description' => Str::uuid()->toString(),
        ]);

        $this->subproject1 = SubProject::create([
            'name' => Str::uuid()->toString(),
            'projectid' => $this->project->id,
            'groupid' => $buildgroup1->id,
        ]);

        $this->subproject2 = SubProject::create([
            'name' => Str::uuid()->toString(),
            'projectid' => $this->project->id,
            'groupid' => $buildgroup2->id,
        ]);

        $this->site = $this->makeSite();
        $this->updateSiteInfoIfChanged($this->site, new SiteInformation([]));
    }

    public function tearDown(): void
    {
        $this->project->delete();
        $this->site->delete();

        parent::tearDown();
    }

    public function testShowsBuildName(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit("/builds/{$build->id}/errors")
                ->waitForText($build->name)
                ->assertSee($build->name)
            ;
        });
    }

    public function testShowsMessageWhenNoErrorsOrWarnings(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit("/builds/{$build->id}/errors")
                ->waitForText('No errors or warnings for this build.')
                ->assertSee('No errors or warnings for this build.')
            ;
        });
    }

    public function testDoesntShowMessageWhenErrorsPresent(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $build->buildErrors()->save(BuildError::factory()->make());
        $build->buildErrors()->save(BuildError::factory()->make());

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit("/builds/{$build->id}/errors")
                ->waitForText('2 ERRORS')
                ->assertDontSee('No errors or warnings for this build.')
            ;
        });
    }

    public function testShowsMessageWhenNoNewErrorsOrWarnings(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit("/builds/{$build->id}/errors?onlydeltap")
                ->waitForText('No new errors or warnings for this build.')
                ->assertSee('No new errors or warnings for this build.')
            ;
        });
    }

    public function testShowsMessageWhenNoFixedErrorsOrWarnings(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit("/builds/{$build->id}/errors?onlydeltan")
                ->waitForText('No fixed errors or warnings for this build.')
                ->assertSee('No fixed errors or warnings for this build.')
            ;
        });
    }

    public function testShowsErrorsForSingleBuildWithSingleError(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildError $buildError1 */
        $buildError1 = $build->buildErrors()->save(BuildError::factory()->make());

        $this->browse(function (Browser $browser) use ($buildError1, $build): void {
            $browser->visit("/builds/{$build->id}/errors")
                ->waitForText('1 ERROR')
                ->assertSee($buildError1->stdoutput)
                ->assertSee($buildError1->stderror)
            ;
        });
    }

    public function testShowsErrorsForSingleBuildWithMultipleErrors(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildError $buildError1 */
        $buildError1 = $build->buildErrors()->save(BuildError::factory()->make());
        /** @var BuildError $buildError2 */
        $buildError2 = $build->buildErrors()->save(BuildError::factory()->make());

        $this->browse(function (Browser $browser) use ($buildError2, $buildError1, $build): void {
            $browser->visit("/builds/{$build->id}/errors")
                ->waitForText('2 ERRORS')
                ->assertSee($buildError1->stdoutput)
                ->assertSee($buildError1->stderror)
                ->assertSee($buildError2->stdoutput)
                ->assertSee($buildError2->stderror)
            ;
        });
    }

    public function testShowsWarningsForSingleBuildWithSingleWarning(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildError $buildError1 */
        $buildError1 = $build->buildErrors()->save(BuildError::factory()->make([
            'type' => 1,
        ]));

        $this->browse(function (Browser $browser) use ($buildError1, $build): void {
            $browser->visit("/builds/{$build->id}/errors")
                ->waitForText('1 WARNING')
                ->assertSee($buildError1->stdoutput)
                ->assertSee($buildError1->stderror)
            ;
        });
    }

    public function testShowsWarningsForSingleBuildWithMultipleWarnings(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildError $buildError1 */
        $buildError1 = $build->buildErrors()->save(BuildError::factory()->make([
            'type' => 1,
        ]));
        /** @var BuildError $buildError2 */
        $buildError2 = $build->buildErrors()->save(BuildError::factory()->make([
            'type' => 1,
        ]));

        $this->browse(function (Browser $browser) use ($buildError2, $buildError1, $build): void {
            $browser->visit("/builds/{$build->id}/errors")
                ->waitForText('2 WARNINGS')
                ->assertSee($buildError1->stdoutput)
                ->assertSee($buildError1->stderror)
                ->assertSee($buildError2->stdoutput)
                ->assertSee($buildError2->stderror)
            ;
        });
    }

    public function testCollapsesLongStdout(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildError $buildError1 */
        $buildError1 = $build->buildErrors()->save(BuildError::factory()->make([
            'type' => 1,
        ]));

        $this->browse(function (Browser $browser) use ($buildError1, $build): void {
            $browser->visit("/builds/{$build->id}/errors")
                ->waitForText('1 WARNING')
                ->assertSee($buildError1->stdoutput)
            ;

            $buildError1->stdoutput .= "a\na\na\na\na\na\na\na\na\na\na\na\na\na\na\na\na\na\na\na\na\na\na\na";
            $buildError1->save();

            $browser->visit("/builds/{$build->id}/errors")
                ->waitForText('1 WARNING')
                ->assertDontSee($buildError1->stdoutput)
                ->click('@stdout')
                ->assertSee($buildError1->stdoutput)
            ;
        });
    }

    public function testShowsListOfSubProjects(): void
    {
        /** @var Build $parent_build */
        $parent_build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Build $child_build_1 */
        $child_build_1 = $parent_build->children()->create([
            'projectid' => $this->project->id,
            'subprojectid' => $this->subproject1->id,
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Build $child_build_2 */
        $child_build_2 = $parent_build->children()->create([
            'projectid' => $this->project->id,
            'subprojectid' => $this->subproject2->id,
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildError $buildError1 */
        $buildError1 = $child_build_1->buildErrors()->save(BuildError::factory()->make());
        /** @var BuildError $buildError2 */
        $buildError2 = $child_build_2->buildErrors()->save(BuildError::factory()->make());

        $this->browse(function (Browser $browser) use ($buildError1, $buildError2, $parent_build): void {
            $browser->visit("/builds/{$parent_build->id}/errors")
                ->waitForText($this->subproject1->name)
                ->waitForText($this->subproject2->name)
                ->assertDontSee($buildError1->stdoutput)
                ->assertDontSee($buildError2->stdoutput)
                ->click('@collapse-' . $this->subproject1->id)
                ->assertSee($buildError1->stdoutput)
                ->assertDontSee($buildError2->stdoutput)
            ;
        });
    }

    public function testWarningsAndErrorsBySubproject(): void
    {
        /** @var Build $parent_build */
        $parent_build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Build $child_build_1 */
        $child_build_1 = $parent_build->children()->create([
            'projectid' => $this->project->id,
            'subprojectid' => $this->subproject1->id,
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'builderrors' => 1,
            'buildwarnings' => 5,
        ]);

        /** @var Build $child_build_2 */
        $child_build_2 = $parent_build->children()->create([
            'projectid' => $this->project->id,
            'subprojectid' => $this->subproject2->id,
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($parent_build): void {
            $browser->visit("/builds/{$parent_build->id}/errors")
                ->waitForText($this->subproject1->name)
                ->waitForText($this->subproject2->name)
                ->assertSeeIn('@errors-' . $this->subproject1->id, '1')
                ->assertSeeIn('@warnings-' . $this->subproject1->id, '5')
            ;
        });
    }

    public function testShowsLabelsIfPresent(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildError $buildError1 */
        $buildError1 = $build->buildErrors()->save(BuildError::factory()->make());

        /** @var Label $label1 */
        $label1 = $buildError1->labels()->create([
            'text' => Str::uuid()->toString(),
        ]);
        /** @var Label $label2 */
        $label2 = $buildError1->labels()->create([
            'text' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($label2, $label1, $build): void {
            $browser->visit("/builds/{$build->id}/errors")
                ->waitForText($label1->text)
                ->waitForText($label2->text)
                ->assertSee($label1->text)
                ->assertSee($label2->text)
            ;
        });
    }

    public function testShowsNewAndFixedWarnings(): void
    {
        $buildName = Str::uuid()->toString();

        /** @var Build $previous_build */
        $previous_build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'type' => 'Nightly',
            'starttime' => Carbon::now()->subDay(),
        ]);

        /** @var Build $current_build */
        $current_build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'type' => 'Nightly',
            'starttime' => Carbon::now(),
        ]);

        $buildError1 = BuildError::factory()->make([
            'type' => 1,
        ]);
        $buildError2 = BuildError::factory()->make([
            'type' => 1,
        ]);
        $buildError3 = BuildError::factory()->make([
            'type' => 1,
        ]);

        $previous_build->buildErrors()->create($buildError1->toArray());
        $previous_build->buildErrors()->create($buildError2->toArray());
        $current_build->buildErrors()->create($buildError2->toArray());
        $current_build->buildErrors()->create($buildError3->toArray());

        $this->browse(function (Browser $browser) use ($buildError1, $buildError2, $buildError3, $current_build): void {
            $browser->visit("/builds/{$current_build->id}/errors")
                ->waitForText('2 WARNINGS')
                ->assertSee($buildError2->stdoutput)
                ->assertSee($buildError3->stdoutput)
                ->assertDontSee($buildError1->stdoutput)
            ;

            $browser->visit("/builds/{$current_build->id}/errors?onlydeltap")
                ->waitForText('1 NEW WARNING')
                ->assertSee($buildError3->stdoutput)
                ->assertDontSee($buildError2->stdoutput)
                ->assertDontSee($buildError1->stdoutput)
            ;

            $browser->visit("/builds/{$current_build->id}/errors?onlydeltan")
                ->waitForText('1 FIXED WARNING')
                ->assertSee($buildError1->stdoutput)
                ->assertDontSee($buildError2->stdoutput)
                ->assertDontSee($buildError3->stdoutput)
            ;
        });
    }

    public function testShowsNewAndFixedErrors(): void
    {
        $buildName = Str::uuid()->toString();

        /** @var Build $previous_build */
        $previous_build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'type' => 'Nightly',
            'starttime' => Carbon::now()->subDay(),
        ]);

        /** @var Build $current_build */
        $current_build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'type' => 'Nightly',
            'starttime' => Carbon::now(),
        ]);

        $buildError1 = BuildError::factory()->make([
            'type' => 0,
        ]);
        $buildError2 = BuildError::factory()->make([
            'type' => 0,
        ]);
        $buildError3 = BuildError::factory()->make([
            'type' => 0,
        ]);

        $previous_build->buildErrors()->create($buildError1->toArray());
        $previous_build->buildErrors()->create($buildError2->toArray());
        $current_build->buildErrors()->create($buildError2->toArray());
        $current_build->buildErrors()->create($buildError3->toArray());

        $this->browse(function (Browser $browser) use ($buildError1, $buildError2, $buildError3, $current_build): void {
            $browser->visit("/builds/{$current_build->id}/errors")
                ->waitForText('2 ERRORS')
                ->assertSee($buildError2->stdoutput)
                ->assertSee($buildError3->stdoutput)
                ->assertDontSee($buildError1->stdoutput)
            ;

            $browser->visit("/builds/{$current_build->id}/errors?onlydeltap")
                ->waitForText('1 NEW ERROR')
                ->assertSee($buildError3->stdoutput)
                ->assertDontSee($buildError2->stdoutput)
                ->assertDontSee($buildError1->stdoutput)
            ;

            $browser->visit("/builds/{$current_build->id}/errors?onlydeltan")
                ->waitForText('1 FIXED ERROR')
                ->assertSee($buildError1->stdoutput)
                ->assertDontSee($buildError2->stdoutput)
                ->assertDontSee($buildError3->stdoutput)
            ;
        });
    }

    public function testShowsNewAndFixedWarningsBySubproject(): void
    {
        $buildName = Str::uuid()->toString();
        $currentStartTime = Carbon::now();
        $previousStartTime = Carbon::now()->subDay();

        /** @var Build $previous_parent_build */
        $previous_parent_build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'type' => 'Nightly',
            'starttime' => $previousStartTime,
        ]);

        /** @var Build $previous_child_build_1 */
        $previous_child_build_1 = $previous_parent_build->children()->create([
            'projectid' => $this->project->id,
            'subprojectid' => $this->subproject1->id,
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'builderrors' => 1,
            'buildwarnings' => 5,
            'type' => 'Nightly',
            'starttime' => $previousStartTime,
        ]);

        /** @var Build $previous_child_build_2 */
        $previous_child_build_2 = $previous_parent_build->children()->create([
            'projectid' => $this->project->id,
            'subprojectid' => $this->subproject2->id,
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'type' => 'Nightly',
            'starttime' => $previousStartTime,
        ]);

        /** @var Build $current_parent_build */
        $current_parent_build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'type' => 'Nightly',
            'starttime' => $currentStartTime,
        ]);

        /** @var Build $current_child_build_1 */
        $current_child_build_1 = $current_parent_build->children()->create([
            'projectid' => $this->project->id,
            'subprojectid' => $this->subproject1->id,
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'builderrors' => 1,
            'buildwarnings' => 5,
            'type' => 'Nightly',
            'starttime' => $currentStartTime,
        ]);

        /** @var Build $current_child_build_2 */
        $current_child_build_2 = $current_parent_build->children()->create([
            'projectid' => $this->project->id,
            'subprojectid' => $this->subproject2->id,
            'siteid' => $this->site->id,
            'name' => $buildName,
            'uuid' => Str::uuid()->toString(),
            'type' => 'Nightly',
            'starttime' => $currentStartTime,
        ]);

        $buildError1 = BuildError::factory()->make([
            'type' => 1,
        ]);
        $buildError2 = BuildError::factory()->make([
            'type' => 1,
        ]);
        $buildError3 = BuildError::factory()->make([
            'type' => 1,
        ]);
        $buildError4 = BuildError::factory()->make([
            'type' => 1,
        ]);

        $previous_child_build_1->buildErrors()->create($buildError1->toArray());
        $previous_child_build_1->buildErrors()->create($buildError2->toArray());
        $previous_child_build_2->buildErrors()->create($buildError3->toArray());
        $current_child_build_1->buildErrors()->create($buildError2->toArray());
        $current_child_build_1->buildErrors()->create($buildError4->toArray());
        $current_child_build_2->buildErrors()->create($buildError3->toArray());

        $this->browse(function (Browser $browser) use (
            $buildError1,
            $buildError2,
            $buildError3,
            $buildError4,
            $current_parent_build,
        ): void {
            $browser->visit("/builds/{$current_parent_build->id}/errors")
                ->waitForText($this->subproject1->name)
                ->click('@collapse-' . $this->subproject1->id)
                ->with('@collapse-' . $this->subproject1->id, function (Browser $browser) use (
                    $buildError1,
                    $buildError2,
                    $buildError3,
                    $buildError4,
                ): void {
                    $browser->waitForText('2 WARNINGS')
                        ->assertDontSee($buildError1->stdoutput)
                        ->assertSee($buildError2->stdoutput)
                        ->assertDontSee($buildError3->stdoutput)
                        ->assertSee($buildError4->stdoutput)
                    ;
                })
                ->click('@collapse-' . $this->subproject2->id)
                ->with('@collapse-' . $this->subproject2->id, function (Browser $browser) use (
                    $buildError1,
                    $buildError2,
                    $buildError3,
                    $buildError4,
                ): void {
                    $browser->waitForText('1 WARNING')
                        ->assertDontSee($buildError1->stdoutput)
                        ->assertDontSee($buildError2->stdoutput)
                        ->assertSee($buildError3->stdoutput)
                        ->assertDontSee($buildError4->stdoutput)
                    ;
                })
            ;

            $browser->visit("/builds/{$current_parent_build->id}/errors?onlydeltap")
                ->waitForText($this->subproject1->name)
                ->click('@collapse-' . $this->subproject1->id)
                ->with('@collapse-' . $this->subproject1->id, function (Browser $browser) use (
                    $buildError1,
                    $buildError2,
                    $buildError3,
                    $buildError4,
                ): void {
                    $browser->waitForText('1 NEW WARNING')
                        ->assertDontSee($buildError1->stdoutput)
                        ->assertDontSee($buildError2->stdoutput)
                        ->assertDontSee($buildError3->stdoutput)
                        ->assertSee($buildError4->stdoutput)
                    ;
                })
                ->click('@collapse-' . $this->subproject2->id)
                ->with('@collapse-' . $this->subproject2->id, function (Browser $browser) use (
                    $buildError1,
                    $buildError2,
                    $buildError3,
                    $buildError4,
                ): void {
                    $browser->waitForText('No new errors or warnings for this build.')
                        ->assertDontSee($buildError1->stdoutput)
                        ->assertDontSee($buildError2->stdoutput)
                        ->assertDontSee($buildError3->stdoutput)
                        ->assertDontSee($buildError4->stdoutput)
                    ;
                })
            ;

            $browser->visit("/builds/{$current_parent_build->id}/errors?onlydeltan")
                ->waitForText($this->subproject1->name)
                ->click('@collapse-' . $this->subproject1->id)
                ->with('@collapse-' . $this->subproject1->id, function (Browser $browser) use (
                    $buildError1,
                    $buildError2,
                    $buildError3,
                    $buildError4,
                ): void {
                    $browser->waitForText('1 FIXED WARNING')
                        ->assertSee($buildError1->stdoutput)
                        ->assertDontSee($buildError2->stdoutput)
                        ->assertDontSee($buildError3->stdoutput)
                        ->assertDontSee($buildError4->stdoutput)
                    ;
                })
                ->click('@collapse-' . $this->subproject2->id)
                ->with('@collapse-' . $this->subproject2->id, function (Browser $browser): void {
                    $browser->waitForText('No fixed errors or warnings for this build.');
                })
            ;
        });
    }
}
