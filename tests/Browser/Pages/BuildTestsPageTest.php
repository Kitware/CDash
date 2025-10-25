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
}
