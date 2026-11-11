<?php

namespace Tests\Browser\Pages;

use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;

/**
 * The display settings menu and the extra information which can be shown for each build.
 */
class ProjectBuildsPageSettingsTest extends ProjectBuildsPageTestCase
{
    private function openSettings(Browser $browser): Browser
    {
        return $browser->click('@settings-menu-toggle')
            ->waitFor('@settings-menu');
    }

    /**
     * Which of the two color palette stylesheets the page uses.
     */
    private function colorStylesheet(Browser $browser): string
    {
        $stylesheets = array_map(
            function (RemoteWebElement $link): string {
                $href = $link->getDomProperty('href');
                return is_string($href) ? basename((string) parse_url($href, PHP_URL_PATH)) : '';
            },
            $browser->driver->findElements(WebDriverBy::cssSelector('link[rel="stylesheet"]')),
        );
        $palettes = array_values(array_intersect($stylesheets, ['cdash.css', 'colorblind.css']));
        self::assertCount(1, $palettes);
        return $palettes[0];
    }

    public function testAdvancedViewShowsTimingColumns(): void
    {
        $this->project->pinnedTestMeasurements()->create(['name' => 'Processors', 'position' => 1]);
        $this->createBuild([
            'updateid' => $this->createUpdate()->id,
            'configureerrors' => 0,
            'configurewarnings' => 0,
            'configureduration' => 65,
            'builderrors' => 0,
            'buildwarnings' => 0,
            'buildduration' => 120,
            'testnotrun' => 0,
            'testfailed' => 0,
            'testpassed' => 1,
            'testduration' => 30,
        ]);
        $timing_columns = ['update-time', 'configure-time', 'build-time', 'test-time', 'proc-time'];

        $this->browse(function (Browser $browser) use ($timing_columns): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly');
            foreach ($timing_columns as $column) {
                $browser->assertNotPresent("@build-group-Nightly @header-{$column}")
                    ->assertNotPresent("@build-group-Nightly @build-row @{$column}");
            }

            $this->openSettings($browser)
                ->assertSeeIn('@advanced-view-toggle', 'Advanced View')
                ->waitForReload(fn (Browser $browser) => $browser->click('@advanced-view-toggle'))
                ->waitFor('@build-group-Nightly');
            foreach ($timing_columns as $column) {
                $browser->assertPresent("@build-group-Nightly @header-{$column}")
                    ->assertPresent("@build-group-Nightly @build-row @{$column}");
            }
            self::assertSame('1m 5s', $browser->text('@build-group-Nightly @build-row @configure-time'));
            self::assertSame('2m', $browser->text('@build-group-Nightly @build-row @build-time'));

            // The setting is remembered for each project.
            self::assertSame('1', $browser->plainCookie("cdash_{$this->project->name}_advancedview"));
            $browser->refresh()
                ->waitFor('@build-group-Nightly @header-build-time');

            $this->openSettings($browser)
                ->assertSeeIn('@advanced-view-toggle', 'Simple View')
                ->waitForReload(fn (Browser $browser) => $browser->click('@advanced-view-toggle'))
                ->waitFor('@build-group-Nightly');
            foreach ($timing_columns as $column) {
                $browser->assertNotPresent("@build-group-Nightly @header-{$column}");
            }
        });
    }

    public function testAdvancedViewShowsParentBuildDetails(): void
    {
        $parent = $this->createBuild([
            'name' => 'parent',
            'stamp' => '20250115-1200-Nightly',
            'osname' => 'Linux',
            'generator' => 'ctest-4.0.0',
        ]);
        // All of the SubProjects were built at once, so they share their timing.
        foreach (['first', 'second'] as $name) {
            $this->createChildBuild($parent, $name . '-' . Str::uuid()->toString(), 0, [
                'generator' => 'ctest-4.0.0',
                'builderrors' => 0,
                'buildwarnings' => 0,
                'buildduration' => 90,
            ]);
        }

        $this->browse(function (Browser $browser) use ($parent): void {
            $browser->visit($this->indexUrl("&parentid={$parent->id}", null))
                ->waitFor('@parent-build-info')
                ->assertNotPresent('@parent-stamp')
                ->assertNotPresent('@parent-build-time');

            $this->openSettings($browser)
                ->waitForReload(fn (Browser $browser) => $browser->click('@advanced-view-toggle'))
                ->waitFor('@parent-build-info')
                ->assertSeeIn('@parent-stamp', '20250115-1200-Nightly')
                ->assertSeeIn('@parent-os-name', 'Linux')
                ->assertSeeIn('@parent-generator', 'ctest-4.0.0')
                ->assertSeeIn('@parent-build-time', '1m 30s');
        });
    }

    public function testSwitchesColorPalette(): void
    {
        $this->createBuild(['builderrors' => 0, 'buildwarnings' => 0]);

        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly');
            self::assertSame('cdash.css', $this->colorStylesheet($browser));

            $this->openSettings($browser)
                ->assertSeeIn('@colorblind-toggle', 'Colorblind palette')
                ->waitForReload(fn (Browser $browser) => $browser->click('@colorblind-toggle'))
                ->waitFor('@build-group-Nightly');
            self::assertSame('colorblind.css', $this->colorStylesheet($browser));
            self::assertSame('1', $browser->plainCookie('colorblind'));

            $this->openSettings($browser)
                ->assertSeeIn('@colorblind-toggle', 'Classic palette')
                ->waitForReload(fn (Browser $browser) => $browser->click('@colorblind-toggle'))
                ->waitFor('@build-group-Nightly');
            self::assertSame('cdash.css', $this->colorStylesheet($browser));
            self::assertSame('0', $browser->plainCookie('colorblind'));
        });
    }

    public function testRefreshesAutomatically(): void
    {
        $this->createBuild();

        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly');

            $this->openSettings($browser)
                ->assertSeeIn('@auto-refresh-toggle', 'Auto-refresh')
                ->waitForReload(fn (Browser $browser) => $browser->click('@auto-refresh-toggle'))
                ->waitFor('@build-group-Nightly');
            self::assertSame('true', $browser->plainCookie('cdash_refresh'));

            // The page reloads itself every few seconds.
            $browser->waitForReload(null, 10)
                ->waitFor('@build-group-Nightly');

            $this->openSettings($browser)
                ->assertSeeIn('@auto-refresh-toggle', 'Stop auto-refresh')
                ->waitForReload(fn (Browser $browser) => $browser->click('@auto-refresh-toggle'))
                ->waitFor('@build-group-Nightly');
            self::assertNotSame('true', $browser->plainCookie('cdash_refresh'));
            $this->openSettings($browser)
                ->assertSeeIn('@auto-refresh-toggle', 'Auto-refresh');
        });
    }

    public function testShowsAndHidesFilters(): void
    {
        $this->createBuild();

        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly')
                ->assertMissing('@filters');

            $this->openSettings($browser)
                ->assertSeeIn('@show-filters-toggle', 'Show Filters')
                ->click('@show-filters-toggle')
                ->waitFor('@filters');

            $this->openSettings($browser)
                ->assertSeeIn('@show-filters-toggle', 'Hide Filters')
                ->click('@show-filters-toggle')
                ->waitUntilMissing('@filters');

            // When the URL asks for the filters to be shown, hiding them updates the URL.
            $browser->visit($this->indexUrl('&filtercount=0&showfilters=1'))
                ->waitFor('@filters');
            $this->openSettings($browser)
                ->assertSeeIn('@show-filters-toggle', 'Hide Filters')
                ->waitForReload(fn (Browser $browser) => $browser->click('@show-filters-toggle'))
                ->waitFor('@build-group-Nightly')
                ->assertMissing('@filters');
            self::assertStringContainsString('&filtercount=0&showfilters=0', $browser->driver->getCurrentURL());
        });
    }

    public function testShowsHowLongBuildsHaveBeenFailing(): void
    {
        $site = $this->createSite();
        $this->createBuild([
            'name' => 'flaky',
            'siteid' => $site->id,
            'builderrors' => 0,
            'testfailed' => 0,
            'starttime' => '2025-01-12 12:00:00',
        ]);
        $this->createBuild([
            'name' => 'flaky',
            'siteid' => $site->id,
            'builderrors' => 2,
            'buildwarnings' => 0,
            'testnotrun' => 0,
            'testfailed' => 1,
            'testpassed' => 0,
        ]);

        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly')
                ->assertMissing('@build-problems')
                ->click('@build-group-Nightly @build-problems-toggle')
                ->waitFor('@build-problems')
                ->assertSeeIn('@build-failing-since', 'Build has been failing since 2025-01-12')
                ->assertSeeIn('@build-failing-since', '(3 days)')
                ->assertSeeIn('@tests-failing-since', 'Tests have been failing since 2025-01-12')
                ->assertSeeIn('@tests-failing-since', '(3 days)');
            self::assertStringContainsString(
                "index.php?project={$this->project->name}&date=2025-01-12",
                $this->href($browser, '@build-failing-since a'),
            );

            $browser->click('@build-group-Nightly @build-problems-toggle')
                ->waitUntilMissing('@build-problems');
        });
    }

    public function testShowsWhenExpectedBuildsLastSubmitted(): void
    {
        $site = $this->createSite();
        $this->createBuild(['name' => 'missing', 'siteid' => $site->id, 'starttime' => '2025-01-13 12:00:00']);
        $this->buildGroup('Nightly')->rules()->create([
            'buildtype' => 'Nightly',
            'buildname' => 'missing',
            'siteid' => $site->id,
            'expected' => 1,
            'starttime' => Carbon::create(1980),
        ]);
        $this->buildGroup('Experimental')->rules()->create([
            'buildtype' => 'Experimental',
            'buildname' => 'never',
            'siteid' => $site->id,
            'expected' => 1,
            'starttime' => Carbon::create(1980),
        ]);

        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Experimental')
                ->assertMissing('@expected-build-info')
                ->click('@build-group-Nightly @expected-build-info-toggle')
                ->waitFor('@build-group-Nightly @expected-build-info')
                ->assertSeeIn('@build-group-Nightly @expected-build-info', 'This build has not submitted since Jan 13, 2025 (2 days)')
                ->click('@build-group-Experimental @expected-build-info-toggle')
                ->waitFor('@build-group-Experimental @expected-build-info')
                ->assertSeeIn('@build-group-Experimental @expected-build-info', 'This build has never submitted.');
            self::assertStringContainsString(
                "index.php?project={$this->project->name}&date=2025-01-13",
                $this->href($browser, '@build-group-Nightly @expected-build-info a'),
            );

            $browser->click('@build-group-Nightly @expected-build-info-toggle')
                ->waitUntilMissing('@build-group-Nightly @expected-build-info');
        });
    }
}
