<?php

namespace Tests\Browser\Pages;

use App\Enums\TargetType;
use App\Http\Submission\Traits\UpdatesSiteInformation;
use App\Models\Build;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Models\Target;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;

class BuildTargetsPageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesSites;
    use UpdatesSiteInformation;

    private Project $project;

    private Build $build;

    private Site $site;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        $this->site = $this->makeSite();
        $this->updateSiteInfoIfChanged($this->site, new SiteInformation([]));

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);
        $this->build = $build;
    }

    public function tearDown(): void
    {
        $this->project->delete();
        $this->site->delete();

        parent::tearDown();
    }

    private function addTarget(TargetType $type = TargetType::UNKNOWN): Target
    {
        return $this->build->targets()->create([
            'name' => Str::uuid()->toString(),
            'type' => $type,
        ]);
    }

    public function testShowsBuildName(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/targets")
                ->waitForText($this->build->name)
                ->assertSee($this->build->name)
            ;
        });
    }

    public function testTargetName(): void
    {
        $target = $this->addTarget();

        $this->browse(function (Browser $browser) use ($target): void {
            $browser->visit("/builds/{$this->build->id}/targets")
                ->waitForText($target->name)
                ->assertSee($target->name)
            ;
        });
    }

    /**
     * We arbitrarily choose a random target type to verify that it gets rendered in a human-friendly format.
     */
    public function testTargetType(): void
    {
        $this->addTarget(TargetType::SHARED_LIBRARY);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/targets")
                ->waitFor('@targets-table')
                ->assertSee('Shared Library')
            ;
        });
    }

    public function testFilters(): void
    {
        $this->addTarget(TargetType::STATIC_LIBRARY);
        $this->addTarget(TargetType::SHARED_LIBRARY);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/targets")
                ->waitFor('@targets-table')
                ->assertSee('Shared Library')
                ->assertSee('Static Library')
            ;

            // Now, filter by targets of type SHARED_LIBRARY...
            $browser->visit("/builds/{$this->build->id}/targets?filters=%7B%22all%22%3A%5B%7B%22eq%22%3A%7B%22type%22%3A%22SHARED_LIBRARY%22%7D%7D%5D%7D")
                ->waitFor('@targets-table')
                ->assertSee('Shared Library')
                ->assertDontSee('Static Library')
            ;

            // Now, filter by targets of type STATIC_LIBRARY...
            $browser->visit("/builds/{$this->build->id}/targets?filters=%7B%22all%22%3A%5B%7B%22eq%22%3A%7B%22type%22%3A%22STATIC_LIBRARY%22%7D%7D%5D%7D")
                ->waitFor('@targets-table')
                ->assertSee('Static Library')
                ->assertDontSee('Shared Library')
            ;
        });
    }

    public function testTargetTablePagination(): void
    {
        $targets = collect();
        for ($i = 0; $i < 120; $i++) {
            $targets[] = $this->addTarget();
        }

        $targets = $targets->sortByDesc('name');

        self::assertCount(120, $targets);

        $this->browse(function (Browser $browser) use ($targets): void {
            $browser->visit("/builds/{$this->build->id}/targets")
                ->waitFor('@targets-table')
                // Wait for min and max names to ensure multiple pages of data have loaded properly.
                ->waitForTextIn('@targets-table', $targets->first()->name ?? '')
                ->waitForTextIn('@targets-table', $targets->last()->name ?? '')
                ->assertCount('@targets-table-row', 120)
            ;
        });
    }
}
