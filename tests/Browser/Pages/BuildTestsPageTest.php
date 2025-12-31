<?php

namespace Tests\Browser\Pages;

use App\Http\Submission\Traits\UpdatesSiteInformation;
use App\Models\Build;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Models\SubProject;
use App\Models\Test;
use App\Models\TestOutput;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;

class BuildTestsPageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesSites;
    use UpdatesSiteInformation;

    private Project $project;

    private TestOutput $testOutput;

    private Site $site;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        $this->testOutput = TestOutput::create([
            'path' => 'a',
            'command' => 'b',
            'output' => 'c',
        ]);

        $this->site = $this->makeSite();
        $this->updateSiteInfoIfChanged($this->site, new SiteInformation([]));
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->project->delete();
        $this->testOutput->delete();
        $this->site->delete();
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
            $browser->visit("/builds/{$build->id}/tests")
                ->waitForText($build->name)
                ->assertSee($build->name)
            ;
        });
    }

    public function testFiltersByParentAndChildBuildTests(): void
    {
        /** @var SubProject $subproject1 */
        $subproject1 = $this->project->subprojects()->create([
            'groupid' => -1,
            'name' => Str::uuid()->toString(),
        ]);

        /** @var SubProject $subproject2 */
        $subproject2 = $this->project->subprojects()->create([
            'groupid' => -1,
            'name' => Str::uuid()->toString(),
        ]);

        /** @var Build $parent_build */
        $parent_build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Test $parent_build_test */
        $parent_build_test = $parent_build->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'failed',
            'outputid' => $this->testOutput->id,
        ]);

        /** @var Test $child_build_1_test */
        $child_build_1_test = $parent_build->children()->create([
            'projectid' => $this->project->id,
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'subprojectid' => $subproject1->id,
        ])->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'failed',
            'outputid' => $this->testOutput->id,
        ]);

        /** @var Test $child_build_2_test */
        $child_build_2_test = $parent_build->children()->create([
            'projectid' => $this->project->id,
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'subprojectid' => $subproject2->id,
        ])->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'passed',
            'outputid' => $this->testOutput->id,
        ]);

        $this->browse(function (Browser $browser) use ($parent_build_test, $child_build_2_test, $child_build_1_test, $parent_build): void {
            $browser->visit("/builds/{$parent_build->id}/tests")
                ->waitFor('@tests-table')
                ->assertSeeIn('@tests-table', $parent_build_test->testname)
                ->assertSeeIn('@tests-table', $child_build_1_test->testname)
                ->assertSeeIn('@tests-table', $child_build_2_test->testname)
            ;

            $browser->visit("/builds/{$parent_build->id}/tests?filters=%7B%22all%22%3A%5B%7B%22eq%22%3A%7B%22name%22%3A%22{$parent_build_test->testname}%22%7D%7D%5D%7D")
                ->waitFor('@tests-table')
                ->assertSeeIn('@tests-table', $parent_build_test->testname)
                ->assertDontSeeIn('@tests-table', $child_build_1_test->testname)
                ->assertDontSeeIn('@tests-table', $child_build_2_test->testname)
            ;

            $browser->visit("/builds/{$parent_build->id}/tests?filters=%7B%22all%22%3A%5B%7B%22eq%22%3A%7B%22name%22%3A%22{$child_build_1_test->testname}%22%7D%7D%5D%7D")
                ->waitFor('@tests-table')
                ->assertDontSeeIn('@tests-table', $parent_build_test->testname)
                ->assertSeeIn('@tests-table', $child_build_1_test->testname)
                ->assertDontSeeIn('@tests-table', $child_build_2_test->testname)
            ;

            $browser->visit("/builds/{$parent_build->id}/tests?filters=%7B%22all%22%3A%5B%7B%22eq%22%3A%7B%22name%22%3A%22{$child_build_2_test->testname}%22%7D%7D%5D%7D")
                ->waitFor('@tests-table')
                ->assertDontSeeIn('@tests-table', $parent_build_test->testname)
                ->assertDontSeeIn('@tests-table', $child_build_1_test->testname)
                ->assertSeeIn('@tests-table', $child_build_2_test->testname)
            ;
        });
    }

    public function testHidesSubProjectColumnWhenNoChildBuilds(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Test $test */
        $test = $build->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'failed',
            'outputid' => $this->testOutput->id,
        ]);

        $this->browse(function (Browser $browser) use ($test, $build): void {
            $browser->visit("/builds/{$build->id}/tests")
                ->waitFor('@tests-table')
                ->assertDontSeeIn('@tests-table', 'SubProject')
                ->assertSeeIn('@tests-table', $test->testname)
            ;
        });
    }

    public function testShowsSubProjectColumnWhenHasChildBuilds(): void
    {
        /** @var SubProject $subproject */
        $subproject = $this->project->subprojects()->create([
            'groupid' => -1,
            'name' => Str::uuid()->toString(),
        ]);

        /** @var Build $parent_build */
        $parent_build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Test $child_build_test */
        $child_build_test = $parent_build->children()->create([
            'projectid' => $this->project->id,
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'subprojectid' => $subproject->id,
        ])->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'failed',
            'outputid' => $this->testOutput->id,
        ]);

        $this->browse(function (Browser $browser) use ($child_build_test, $parent_build): void {
            $browser->visit("/builds/{$parent_build->id}/tests")
                ->waitFor('@tests-table')
                ->assertSeeIn('@tests-table', 'SubProject')
                ->assertSeeIn('@tests-table', $child_build_test->testname)
            ;
        });
    }

    public function testConfigurableTimeStatusColumn(): void
    {
        // This should be the default...
        $this->project->showtesttime = false;
        $this->project->save();

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Test $test */
        $test = $build->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'passed',
            'timestatus' => 5,
            'outputid' => $this->testOutput->id,
        ]);

        // Check that the time status column is hidden when not configured to show it
        $this->browse(function (Browser $browser) use ($test, $build): void {
            $browser->visit("/builds/{$build->id}/tests")
                ->waitFor('@tests-table')
                ->assertDontSeeIn('@tests-table', 'Time Status')
                ->assertDontSeeIn('@tests-table', 'Failed')
                ->assertSeeIn('@tests-table', $test->testname)
            ;
        });

        // Check that failed time status displays properly
        $this->project->showtesttime = true;
        $this->project->save();
        $this->browse(function (Browser $browser) use ($test, $build): void {
            $browser->visit("/builds/{$build->id}/tests")
                ->waitFor('@tests-table')
                ->assertSeeIn('@tests-table', 'Time Status')
                ->assertSeeIn('@tests-table', 'Failed')
                ->assertSeeIn('@tests-table', $test->testname)
            ;
        });

        // Check that passing time status displays properly
        $test->timestatus = 0;
        $test->status = 'failed';
        $test->save();
        $this->browse(function (Browser $browser) use ($test, $build): void {
            $browser->visit("/builds/{$build->id}/tests")
                ->waitFor('@tests-table')
                ->assertSeeIn('@tests-table', 'Time Status')
                ->assertSeeIn('@tests-table', 'Passed')
                ->assertSeeIn('@tests-table', $test->testname)
            ;
        });
    }

    public function testHistoryColumn(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'starttime' => '2025-01-01 11:22:33',
        ]);

        /** @var Test $test */
        $test = $build->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'failed',
            'outputid' => $this->testOutput->id,
        ]);

        $this->browse(function (Browser $browser) use ($test, $build): void {
            $browser->visit("/builds/{$build->id}/tests")
                ->waitFor('@tests-table')
                ->assertSeeIn('@tests-table', 'History')
                ->assertSeeIn('@tests-table', $test->testname)
                ->clickLink('History')
                ->assertUrlIs(url('queryTests.php'))
                ->assertQueryStringHas('project', $this->project->name)
                ->assertQueryStringHas('filtercount', '1')
                ->assertQueryStringHas('showfilters', '1')
                ->assertQueryStringHas('field1', 'testname')
                ->assertQueryStringHas('compare1', '61')
                ->assertQueryStringHas('value1', $test->testname)
                ->assertQueryStringHas('date', '2025-01-01')
            ;
        });
    }

    public function testMeasurementColumns(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Test $test */
        $test = $build->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'failed',
            'outputid' => $this->testOutput->id,
        ]);

        $measurement1 = $test->testMeasurements()->create([
            'name' => Str::uuid()->toString(),
            'type' => 'text/string',
            'value' => Str::uuid()->toString(),
        ]);

        $measurement2 = $test->testMeasurements()->create([
            'name' => Str::uuid()->toString(),
            'type' => 'text/string',
            'value' => Str::uuid()->toString(),
        ]);

        $this->project->measurements()->create([
            'name' => $measurement1->name,
            'position' => 1,
        ]);

        $this->browse(function (Browser $browser) use ($build, $measurement1, $measurement2): void {
            $browser->visit("/builds/{$build->id}/tests")
                ->waitFor('@tests-table')
                ->assertSeeIn('@tests-table', $measurement1->name)
                ->assertSeeIn('@tests-table', $measurement1->value)
                ->assertDontSeeIn('@tests-table', $measurement2->name)
                ->assertDontSeeIn('@tests-table', $measurement2->value)
            ;
        });
    }

    public function testMeasurementColumnOrder(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $measurement1 = $this->project->measurements()->create([
            'name' => Str::uuid()->toString(),
            'position' => 1,
        ]);

        $measurement2 = $this->project->measurements()->create([
            'name' => Str::uuid()->toString(),
            'position' => 2,
        ]);

        $measurement3 = $this->project->measurements()->create([
            'name' => Str::uuid()->toString(),
            'position' => 3,
        ]);

        $this->browse(function (Browser $browser) use ($build, $measurement1, $measurement2, $measurement3): void {
            $browser->visit("/builds/{$build->id}/tests")
                ->waitFor('@tests-table')
                ->assertSeeIn('@tests-table th:nth-child(3)', $measurement1->name)
                ->assertSeeIn('@tests-table th:nth-child(4)', $measurement2->name)
                ->assertSeeIn('@tests-table th:nth-child(5)', $measurement3->name)
            ;
        });
    }
}
