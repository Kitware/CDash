<?php

namespace Tests\Browser\Pages;

use App\Models\Build;
use App\Models\Label;
use App\Models\PinnedTestMeasurement;
use App\Models\Project;
use App\Models\Site;
use App\Models\Test;
use App\Models\TestMeasurement;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;

/**
 * The tests the page lists, the columns it shows for them, and its links.
 */
class QueryTestsPageTest extends BrowserTestCase
{
    use CreatesProjects;

    private Project $project;
    private Site $site;

    /**
     * Records which aren't deleted along with the project.
     *
     * @var array<Label>
     */
    private array $cleanup = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
        $this->site = Site::factory()->create();
    }

    public function tearDown(): void
    {
        try {
            $this->browse(function (Browser $browser): void {
                // The page saves its page size in a cookie, and Dusk reuses the browser for each test in a class.
                $browser->driver->manage()->deleteAllCookies();
            });
        } finally {
            // Deleting the project deletes its builds and their tests.
            $this->project->delete();
            $this->site->delete();
            foreach ($this->cleanup as $model) {
                $model->delete();
            }
        }

        parent::tearDown();
    }

    /**
     * Create a build at the test's site, by default at noon on 2025-01-15.
     *
     * @param array<string,mixed> $attributes
     */
    private function createBuild(array $attributes = []): Build
    {
        /** @var Build $build */
        $build = $this->project->builds()->forceCreate(array_merge([
            'siteid' => $this->site->id,
            'name' => 'build-' . Str::uuid()->toString(),
            'type' => 'Nightly',
            'uuid' => Str::uuid()->toString(),
            'starttime' => '2025-01-15 12:00:00',
        ], $attributes));
        return $build;
    }

    /**
     * @return array<string>
     */
    private function texts(Browser $browser, string $selector): array
    {
        return array_map(fn (RemoteWebElement $element): string => trim($element->getText()), $browser->elements($selector));
    }

    /**
     * Wait for the page to list the tests with these names, in this order.  The page lists the tests after
     * loading them, and lists them again when they're paged.
     *
     * @param array<string> $expected
     */
    private function assertTestNames(Browser $browser, array $expected): void
    {
        $actual = null;
        try {
            $browser->waitUsing(null, 100, function () use ($browser, $expected, &$actual): bool {
                $actual = $this->texts($browser, '@test-row @test-name');
                return $actual === $expected;
            });
        } catch (TimeoutException) {
            // Fall through to the assertion below, which reports the difference.
        }
        self::assertSame($expected, $actual);
    }

    public function testListsTheTestsOfTheDay(): void
    {
        $build = $this->createBuild(['name' => 'linux-gcc']);
        $failed = $build->tests()->create([
            'testname' => 'unit',
            'status' => Test::FAILED,
            'details' => 'Completed (Failed)',
            'time' => 90,
        ]);
        $this->cleanup[] = $beta = Label::factory()->create(['text' => 'beta-' . Str::uuid()->toString()]);
        $this->cleanup[] = $alpha = Label::factory()->create(['text' => 'alpha-' . Str::uuid()->toString()]);
        $failed->labels()->attach([$beta->id, $alpha->id]);
        $not_run = $build->tests()->create(['testname' => 'style', 'status' => Test::NOTRUN, 'details' => 'Required Files Missing']);
        $passed = $build->tests()->create(['testname' => 'install', 'status' => Test::PASSED, 'details' => 'Completed', 'time' => 0.5]);
        // Tests of builds on other days aren't listed.
        $this->createBuild(['starttime' => '2025-01-14 12:00:00'])->tests()->create(['testname' => 'yesterday', 'status' => Test::PASSED]);
        $this->createBuild(['starttime' => '2025-01-16 12:00:00'])->tests()->create(['testname' => 'tomorrow', 'status' => Test::PASSED]);

        $this->browse(function (Browser $browser) use ($build, $failed, $not_run, $passed, $alpha, $beta): void {
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15");

            // Failed tests are listed first, then tests which didn't run, then passing tests.
            $this->assertTestNames($browser, ['unit', 'style', 'install']);
            self::assertSame('3', $browser->text('@test-count'));
            self::assertSame('Query Tests: 3 matches', $browser->text('@heading'));
            self::assertSame(['Failed', 'Not Run', 'Passed'], $this->texts($browser, '@test-row @status'));
            self::assertSame(['1m 30s', '0s', '500ms'], $this->texts($browser, '@test-row @time'));
            self::assertSame(['Completed (Failed)', 'Required Files Missing', 'Completed'], $this->texts($browser, '@test-row @details'));
            self::assertSame(["{$alpha->text}, {$beta->text}", '', ''], $this->texts($browser, '@test-row @labels'));
            self::assertSame(array_fill(0, 3, '2025-01-15T12:00:00 UTC'), $this->texts($browser, '@test-row @build-time'));
            self::assertSame(array_fill(0, 3, $this->site->name), $this->texts($browser, '@test-row @site'));
            self::assertSame(array_fill(0, 3, 'linux-gcc'), $this->texts($browser, '@test-row @build-name'));

            foreach ([1 => [$failed, 'error'], 2 => [$not_run, 'warning'], 3 => [$passed, 'normal']] as $n => [$test, $class]) {
                $row = "[data-test=\"test-row\"]:nth-of-type({$n})";
                $browser->assertAttributeContains("{$row} [data-test=\"status\"]", 'class', $class);
                self::assertStringEndsWith("tests/{$test->id}", (string) $browser->attribute("{$row} [data-test=\"status\"] a", 'href'));
                self::assertStringEndsWith("sites/{$this->site->id}", (string) $browser->attribute("{$row} [data-test=\"site\"] a", 'href'));
                self::assertStringEndsWith("builds/{$build->id}", (string) $browser->attribute("{$row} [data-test=\"build-name\"] a", 'href'));
            }

            // The optional columns aren't shown, and there are too few tests to page through.
            $browser->assertNotPresent('@header-time-status')
                ->assertNotPresent('@test-row @time-status')
                ->assertNotPresent('@header-proc-time')
                ->assertNotPresent('@header-processors')
                ->assertNotPresent('[data-test^="header-measurement-"]')
                ->assertNotPresent('@header-matching-output')
                ->assertNotPresent('@pagination')
                ->assertNotPresent('@items-per-page');
        });
    }

    public function testLinksToTestResults(): void
    {
        $test = $this->createBuild()->tests()->create(['testname' => 'linked', 'status' => Test::PASSED]);

        $this->browse(function (Browser $browser) use ($test): void {
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15");
            $this->assertTestNames($browser, ['linked']);

            $browser->click('@test-row @status a')
                ->waitForLocation("/tests/{$test->id}")
                ->waitForText('linked');
        });
    }

    public function testShowsWhetherTestsTookTooLong(): void
    {
        $this->project->showtesttime = true;
        $this->project->testtimemaxstatus = 3;
        $this->project->save();
        $build = $this->createBuild();
        $fast = $build->tests()->create(['testname' => 'fast', 'status' => Test::PASSED, 'timestatus' => 2]);
        $slow = $build->tests()->create(['testname' => 'slow', 'status' => Test::PASSED, 'timestatus' => 3]);

        $this->browse(function (Browser $browser) use ($fast, $slow): void {
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15");
            $this->assertTestNames($browser, ['fast', 'slow']);

            $browser->assertPresent('@header-time-status');
            self::assertSame(['Passed', 'Failed'], $this->texts($browser, '@test-row @time-status'));
            foreach ([1 => [$fast, 'normal'], 2 => [$slow, 'error']] as $n => [$test, $class]) {
                $cell = "[data-test=\"test-row\"]:nth-of-type({$n}) [data-test=\"time-status\"]";
                $browser->assertAttributeContains($cell, 'class', $class);
                self::assertStringEndsWith("tests/{$test->id}", (string) $browser->attribute("{$cell} a", 'href'));
            }
        });
    }

    public function testShowsPinnedMeasurements(): void
    {
        // Pinning the Processors measurement adds Proc Time and Processors columns, rather than a measurement column.
        PinnedTestMeasurement::factory()->for($this->project)->create(['name' => 'Host', 'position' => 3]);
        PinnedTestMeasurement::factory()->for($this->project)->create(['name' => 'Processors', 'position' => 1]);
        PinnedTestMeasurement::factory()->for($this->project)->create(['name' => 'Peak Memory', 'position' => 2]);
        $build = $this->createBuild();
        $parallel = $build->tests()->create(['testname' => 'parallel', 'status' => Test::PASSED, 'time' => 10]);
        TestMeasurement::factory()->for($parallel)->create(['name' => 'Processors', 'type' => 'numeric/double', 'value' => '4']);
        TestMeasurement::factory()->for($parallel)->create(['name' => 'Peak Memory', 'value' => '512 MB']);
        TestMeasurement::factory()->for($parallel)->create(['name' => 'Host', 'value' => 'node-1']);
        TestMeasurement::factory()->for($parallel)->create(['name' => 'Unpinned']);
        $serial = $build->tests()->create(['testname' => 'serial', 'status' => Test::PASSED, 'time' => 5]);
        TestMeasurement::factory()->for($serial)->create(['name' => 'Peak Memory', 'value' => '256 MB']);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15");
            $this->assertTestNames($browser, ['parallel', 'serial']);

            // Tests without a Processors measurement used one processor.
            self::assertSame(['40s', '5s'], $this->texts($browser, '@test-row @proc-time'));
            self::assertSame(['4', ''], $this->texts($browser, '@test-row @processors'));

            // The other pinned measurements are shown in the order of their positions.
            self::assertSame(['Peak Memory', 'Host'], $this->texts($browser, '[data-test^="header-measurement-"]'));
            self::assertSame(['512 MB', 'node-1'], $this->texts($browser, '[data-test="test-row"]:nth-of-type(1) [data-test="measurement"]'));
            self::assertSame(['256 MB', ''], $this->texts($browser, '[data-test="test-row"]:nth-of-type(2) [data-test="measurement"]'));
        });
    }

    public function testListsTheTestsOfARangeOfDays(): void
    {
        foreach (['2025-01-13', '2025-01-14', '2025-01-15', '2025-01-16'] as $date) {
            $this->createBuild(['starttime' => "{$date} 12:00:00"])->tests()->create(['testname' => "test-{$date}", 'status' => Test::PASSED]);
        }

        $this->browse(function (Browser $browser): void {
            $browser->visit("/queryTests.php?project={$this->project->name}&begin=2025-01-13&end=2025-01-14");
            $this->assertTestNames($browser, ['test-2025-01-13', 'test-2025-01-14']);
        });
    }

    public function testListsTheTestsOfAParentBuildsChildren(): void
    {
        $parent = $this->createBuild();
        foreach (['first', 'second'] as $name) {
            $this->createBuild(['name' => $parent->name, 'parentid' => $parent->id])
                ->tests()
                ->create(['testname' => "{$name}-child", 'status' => Test::PASSED]);
        }
        $this->createBuild()->tests()->create(['testname' => 'other-build', 'status' => Test::PASSED]);

        $this->browse(function (Browser $browser) use ($parent): void {
            $browser->visit("/queryTests.php?project={$this->project->name}&parentid={$parent->id}");
            $this->assertTestNames($browser, ['first-child', 'second-child']);
        });
    }

    public function testNavigationLinksKeepFilters(): void
    {
        $this->createBuild()->tests()->create(['testname' => 'today', 'status' => Test::PASSED]);
        $filters = '&filtercount=1&showfilters=1&field1=testname&compare1=63&value1=today';

        $this->browse(function (Browser $browser) use ($filters): void {
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15{$filters}");
            $this->assertTestNames($browser, ['today']);

            $url = "queryTests.php?project={$this->project->name}";
            self::assertStringEndsWith("{$url}&date=2025-01-14&limit=0{$filters}", (string) $browser->attribute('@header-nav-previous', 'href'));
            self::assertStringEndsWith("{$url}&limit=0{$filters}", (string) $browser->attribute('@header-nav-latest', 'href'));
            self::assertStringEndsWith("{$url}&date=2025-01-16&limit=0{$filters}", (string) $browser->attribute('@header-nav-next', 'href'));
        });
    }

    public function testPagesThroughManyTests(): void
    {
        $build = $this->createBuild();
        $names = [];
        for ($i = 1; $i <= 55; $i++) {
            $name = sprintf('test-%02d', $i);
            $names[] = $name;
            $build->tests()->create(['testname' => $name, 'status' => Test::PASSED]);
        }

        $this->browse(function (Browser $browser) use ($names): void {
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15");

            // 50 tests are shown per page by default.
            $this->assertTestNames($browser, array_slice($names, 0, 50));
            self::assertSame('55', $browser->text('@test-count'));

            $browser->within('@pagination', fn (Browser $pagination) => $pagination->clickLink('2'));
            $this->assertTestNames($browser, array_slice($names, 50));

            $browser->within('@pagination', fn (Browser $pagination) => $pagination->clickLink('First'));
            $this->assertTestNames($browser, array_slice($names, 0, 50));

            $browser->select('@items-per-page', '25');
            $this->assertTestNames($browser, array_slice($names, 0, 25));

            $browser->within('@pagination', fn (Browser $pagination) => $pagination->clickLink('Last'));
            $this->assertTestNames($browser, array_slice($names, 50));

            // The number of tests per page is remembered.
            self::assertSame('25', $browser->plainCookie('queryTests_num_per_page'));
            $browser->refresh();
            $this->assertTestNames($browser, array_slice($names, 0, 25));

            $browser->select('@items-per-page', '-1');
            $this->assertTestNames($browser, $names);
        });
    }

    public function testShowsAndHidesFilters(): void
    {
        $this->createBuild()->tests()->create(['testname' => 'filtered', 'status' => Test::PASSED]);

        $this->browse(function (Browser $browser): void {
            // Without a showfilters parameter, the filters are hidden and toggled without reloading the page.
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15");
            $this->assertTestNames($browser, ['filtered']);
            $browser->assertMissing('@filters')
                ->click('@show-filters-toggle')
                ->waitFor('@filters')
                ->click('@show-filters-toggle')
                ->waitUntilMissing('@filters')
                ->assertQueryStringMissing('showfilters');

            // Otherwise, the parameter is changed.
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15&filtercount=0&showfilters=1")
                ->waitFor('@filters')
                ->waitForReload(fn (Browser $browser) => $browser->click('@show-filters-toggle'))
                ->assertQueryStringHas('showfilters', '0');
            $this->assertTestNames($browser, ['filtered']);
            $browser->assertMissing('@filters');
        });
    }

    public function testCalendarChangesTheDay(): void
    {
        $filters = '&filtercount=1&showfilters=1&field1=testname&compare1=63&value1=x';

        $this->browse(function (Browser $browser) use ($filters): void {
            // The date picker is a jQuery UI widget, which starts on the page's month.
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15{$filters}")
                ->waitFor('@calendar-toggle')
                ->click('@calendar-toggle')
                ->waitFor('@calendar')
                ->waitForReload(fn (Browser $browser) => $browser->within('@calendar', fn (Browser $calendar) => $calendar->clickLink('20')));

            self::assertStringEndsWith(
                "/queryTests.php?project={$this->project->name}&date=2025-01-20{$filters}",
                $browser->driver->getCurrentURL(),
            );
        });
    }
}
