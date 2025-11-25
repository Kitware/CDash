<?php

namespace Tests\Browser\Pages;

use App\Http\Submission\Traits\UpdatesSiteInformation;
use App\Models\Build;
use App\Models\BuildConfigure;
use App\Models\Configure;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Models\SubProject;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;

class BuildConfigurePageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesSites;
    use UpdatesSiteInformation;

    private Project $project;

    private Site $site;

    /** @var array<Configure> */
    private array $configures = [];

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
        parent::tearDown();

        $this->project->delete();
        $this->site->delete();

        foreach ($this->configures as $configure) {
            $configure->delete();
        }
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
            $browser->visit("/builds/{$build->id}/configure")
                ->waitForText($build->name)
                ->assertSee($build->name)
            ;
        });
    }

    public function testShowsMessageWhenNoConfigure(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit("/builds/{$build->id}/configure")
                ->waitForText('No configure found for this build.')
                ->assertSee('No configure found for this build.')
            ;
        });
    }

    public function testShowsSingleConfigure(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $configure = Configure::factory()->create();

        BuildConfigure::create([
            'buildid' => $build->id,
            'configureid' => $configure->id,
        ]);

        $this->browse(function (Browser $browser) use ($configure, $build): void {
            $browser->visit("/builds/{$build->id}/configure")
                ->waitForText('Configure Command')
                ->assertSee($configure->command)
                ->assertSee($configure->log)
                ->assertSee((string) $configure->status)
            ;
        });
    }

    public function testShowsSingleConfigureWhenSharedBetweenChildBuilds(): void
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

        $configure = Configure::factory()->create();

        BuildConfigure::create([
            'buildid' => $child_build_1->id,
            'configureid' => $configure->id,
        ]);

        BuildConfigure::create([
            'buildid' => $child_build_2->id,
            'configureid' => $configure->id,
        ]);

        $this->browse(function (Browser $browser) use ($configure, $parent_build): void {
            $browser->visit("/builds/{$parent_build->id}/configure")
                ->waitForText('Configure Command')
                ->assertSee($configure->command)
                ->assertSee($configure->log)
                ->assertSee((string) $configure->status)
                ->assertDontSee($this->subproject1->name)
                ->assertDontSee($this->subproject2->name)
            ;
        });
    }

    public function testShowsListOfConfiguresWhenNotSharedBetweenChildBuilds(): void
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

        $configure1 = Configure::factory()->create();
        $configure2 = Configure::factory()->create();

        BuildConfigure::create([
            'buildid' => $child_build_1->id,
            'configureid' => $configure1->id,
        ]);

        BuildConfigure::create([
            'buildid' => $child_build_2->id,
            'configureid' => $configure2->id,
        ]);

        $this->browse(function (Browser $browser) use ($configure1, $configure2, $parent_build): void {
            $browser->visit("/builds/{$parent_build->id}/configure")
                ->waitForText($this->subproject1->name)
                ->waitForText($this->subproject2->name)
                ->assertDontSee('Configure Command')
                ->assertDontSee($configure1->command)
                ->assertDontSee($configure2->command)
                ->assertDontSee($configure1->log)
                ->assertDontSee($configure2->log)
                ->click('@collapse-' . $this->subproject1->id)
                ->assertSee('Configure Command')
                ->assertSee($configure1->command)
                ->assertDontSee($configure2->command)
                ->assertSee($configure1->log)
                ->assertDontSee($configure2->log)
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
            'configureerrors' => 1,
            'configurewarnings' => 5,
        ]);

        /** @var Build $child_build_2 */
        $child_build_2 = $parent_build->children()->create([
            'projectid' => $this->project->id,
            'subprojectid' => $this->subproject2->id,
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $configure1 = Configure::factory()->create();
        $configure2 = Configure::factory()->create();

        BuildConfigure::create([
            'buildid' => $child_build_1->id,
            'configureid' => $configure1->id,
        ]);

        BuildConfigure::create([
            'buildid' => $child_build_2->id,
            'configureid' => $configure2->id,
        ]);

        $this->browse(function (Browser $browser) use ($parent_build): void {
            $browser->visit("/builds/{$parent_build->id}/configure")
                ->waitForText($this->subproject1->name)
                ->waitForText($this->subproject2->name)
                ->assertSeeIn('@errors-' . $this->subproject1->id, '1')
                ->assertSeeIn('@warnings-' . $this->subproject1->id, '5')
            ;
        });
    }
}
