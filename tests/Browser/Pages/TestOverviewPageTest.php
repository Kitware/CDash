<?php

namespace Tests\Browser\Pages;

use App\Models\Build;
use App\Models\Project;
use App\Models\Site;
use App\Models\Test;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverKeys;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;

/**
 * The tests the page summarizes, the columns it shows for them, sorting and filtering them, and its links.
 * The filters panel is shared with other pages, so the filter tests cover the filters this page offers and
 * the tests they select, rather than the panel itself.
 */
class TestOverviewPageTest extends BrowserTestCase
{
    use CreatesProjects;

    private Project $project;
    private Site $site;

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
                // The page saves its sort order and page size in cookies, and Dusk reuses the browser for each
                // test in a class.
                $browser->driver->manage()->deleteAllCookies();
            });
        } finally {
            // Deleting the project deletes its builds, their tests and its SubProjects.
            $this->project->delete();
            $this->site->delete();
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
     * loading them, and lists them again when they're sorted or paged.
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

    public function testSummarizesTheTestsWhichFailedDuringTheDay(): void
    {
        $first = $this->createBuild();
        $second = $this->createBuild();
        $third = $this->createBuild(['type' => 'Continuous']);
        $first->tests()->create(['testname' => 'broken', 'status' => Test::FAILED, 'time' => 90]);
        $first->tests()->create(['testname' => 'slow', 'status' => Test::FAILED, 'details' => 'Timeout', 'time' => 100]);
        $second->tests()->create(['testname' => 'slow', 'status' => Test::FAILED, 'time' => 30]);
        $first->tests()->create(['testname' => 'flaky', 'status' => Test::FAILED, 'time' => 0.5]);
        $second->tests()->create(['testname' => 'flaky', 'status' => Test::PASSED, 'time' => 0.25]);
        $third->tests()->create(['testname' => 'flaky', 'status' => Test::PASSED, 'time' => 0.25]);
        // Runs which didn't run aren't counted.
        $first->tests()->create(['testname' => 'hangs', 'status' => Test::FAILED, 'details' => 'Timeout', 'time' => 5]);
        $second->tests()->create(['testname' => 'hangs', 'status' => Test::NOTRUN]);
        $first->tests()->create(['testname' => 'never-run', 'status' => Test::NOTRUN]);
        // Tests which didn't fail aren't listed.
        $first->tests()->create(['testname' => 'stable', 'status' => Test::PASSED]);
        // Neither are runs in Experimental builds, or on other days.
        $experimental = $this->createBuild(['type' => 'Experimental']);
        $experimental->tests()->create(['testname' => 'flaky', 'status' => Test::FAILED]);
        $experimental->tests()->create(['testname' => 'experimental', 'status' => Test::FAILED]);
        $this->createBuild(['starttime' => '2025-01-14 12:00:00'])->tests()->create(['testname' => 'broken', 'status' => Test::PASSED]);
        $this->createBuild(['starttime' => '2025-01-16 12:00:00'])->tests()->create(['testname' => 'tomorrow', 'status' => Test::FAILED]);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15");

            // The tests which fail most often are listed first.
            $this->assertTestNames($browser, ['broken', 'slow', 'flaky', 'hangs']);
            self::assertSame('4 tests failed during this time period.', $browser->text('@test-summary'));
            self::assertSame(['100%', '50%', '33.33%', '0%'], $this->texts($browser, '@test-row @failure'));
            self::assertSame(['0%', '50%', '0%', '100%'], $this->texts($browser, '@test-row @timeout'));
            self::assertSame(['1', '2', '3', '1'], $this->texts($browser, '@test-row @total-runs'));
            // The longest time of each test is shown.
            self::assertSame(['1m 30s', '1m 40s', '500ms', '5s'], $this->texts($browser, '@test-row @time'));

            // Each test links to its runs.
            foreach (['broken', 'slow', 'flaky', 'hangs'] as $n => $name) {
                self::assertStringEndsWith(
                    "queryTests.php?project={$this->project->name}&date=2025-01-15&filtercount=1&showfilters=1&field1=testname&compare1=61&value1={$name}",
                    (string) $browser->attribute('[data-test="test-row"]:nth-of-type(' . ($n + 1) . ') [data-test="test-name"] a', 'href'),
                );
            }

            // The project has no SubProjects, and there are too few tests to page through.
            $browser->assertNotPresent('@header-subproject')
                ->assertNotPresent('@test-row @subproject')
                ->assertNotPresent('@pagination')
                ->assertNotPresent('@items-per-page');
        });
    }

    public function testIncludesPassingTestsWhenRequested(): void
    {
        $build = $this->createBuild();
        $build->tests()->create(['testname' => 'failing', 'status' => Test::FAILED]);
        $build->tests()->create(['testname' => 'passing', 'status' => Test::PASSED]);
        $build->tests()->create(['testname' => 'not-run', 'status' => Test::NOTRUN]);
        $this->createBuild(['starttime' => '2025-01-14 12:00:00'])->tests()->create(['testname' => 'passing', 'status' => Test::PASSED]);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-14")
                ->waitForTextIn('@test-summary', 'No tests failed during this time period.')
                ->assertNotPresent('@tests-table')
                ->assertNotChecked('@show-passed');

            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15");
            $this->assertTestNames($browser, ['failing']);
            self::assertSame('1 test failed during this time period.', $browser->text('@test-summary'));

            // Tests which didn't run are never listed.
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15&showpassed=1");
            $this->assertTestNames($browser, ['failing', 'passing']);
            self::assertSame('2 tests ran during this time period.', $browser->text('@test-summary'));
            self::assertSame(['100%', '0%'], $this->texts($browser, '@test-row @failure'));
            $browser->assertChecked('@show-passed');
        });
    }

    public function testShowsTheSubProjectOfEachTest(): void
    {
        $parent = $this->createBuild();
        foreach (['core' => Test::FAILED, 'gui' => Test::PASSED] as $name => $status) {
            $subproject = $this->project->subprojects()->create(['name' => $name, 'groupid' => -1]);
            $this->createBuild(['name' => $parent->name, 'parentid' => $parent->id, 'subprojectid' => $subproject->id])
                ->tests()
                ->create(['testname' => "{$name}-test", 'status' => $status]);
        }

        $this->browse(function (Browser $browser): void {
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15&showpassed=1");
            $this->assertTestNames($browser, ['core-test', 'gui-test']);
            $browser->assertPresent('@header-subproject');
            self::assertSame(['core', 'gui'], $this->texts($browser, '@test-row @subproject'));
        });
    }

    public function testListsTheTestsOfARangeOfDays(): void
    {
        $this->createBuild(['starttime' => '2025-01-12 12:00:00'])->tests()->create(['testname' => 'earlier', 'status' => Test::FAILED]);
        $this->createBuild(['starttime' => '2025-01-13 12:00:00'])->tests()->create(['testname' => 'flaky', 'status' => Test::FAILED]);
        $second_day = $this->createBuild(['starttime' => '2025-01-14 12:00:00']);
        $second_day->tests()->create(['testname' => 'flaky', 'status' => Test::PASSED]);
        $second_day->tests()->create(['testname' => 'passing', 'status' => Test::PASSED]);
        $this->createBuild(['starttime' => '2025-01-15 12:00:00'])->tests()->create(['testname' => 'later', 'status' => Test::FAILED]);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/testOverview.php?project={$this->project->name}&begin=2025-01-13&end=2025-01-14");
            $this->assertTestNames($browser, ['flaky']);
            self::assertSame(['50%'], $this->texts($browser, '@test-row @failure'));
            self::assertSame(['2'], $this->texts($browser, '@test-row @total-runs'));

            // Updating the selection keeps the range.
            $browser->waitForTextIn('@timeline-chart', 'Failing Tests')
                ->check('@show-passed')
                ->waitForReload(fn (Browser $browser) => $browser->click('@timeline-update'));
            self::assertStringEndsWith(
                "/testOverview.php?project={$this->project->name}&begin=2025-01-13&end=2025-01-14&showpassed=1",
                $browser->driver->getCurrentURL(),
            );
            $this->assertTestNames($browser, ['flaky', 'passing']);
        });
    }

    public function testShowsTheTestsOfABuildGroup(): void
    {
        $nightly_group = $this->project->buildgroups()->where('name', 'Nightly')->firstOrFail();
        $continuous_group = $this->project->buildgroups()->where('name', 'Continuous')->firstOrFail();
        $experimental_group = $this->project->buildgroups()->where('name', 'Experimental')->firstOrFail();

        $nightly = $this->createBuild();
        $nightly->buildGroups()->attach($nightly_group);
        $nightly->tests()->create(['testname' => 'nightly', 'status' => Test::FAILED]);
        $nightly->tests()->create(['testname' => 'moved', 'status' => Test::PASSED]);
        // Build group rules can move builds to other groups, so the default selection excludes Experimental
        // builds by their type rather than their group.
        $moved = $this->createBuild(['type' => 'Experimental']);
        $moved->buildGroups()->attach($nightly_group);
        $moved->tests()->create(['testname' => 'moved', 'status' => Test::FAILED]);
        $first_continuous = $this->createBuild(['type' => 'Continuous']);
        $first_continuous->buildGroups()->attach($continuous_group);
        $first_continuous->tests()->create(['testname' => 'continuous', 'status' => Test::FAILED]);
        $first_continuous->tests()->create(['testname' => 'continuous-passing', 'status' => Test::PASSED]);
        $second_continuous = $this->createBuild(['type' => 'Continuous']);
        $second_continuous->buildGroups()->attach($continuous_group);
        $second_continuous->tests()->create(['testname' => 'continuous', 'status' => Test::PASSED]);
        $experimental = $this->createBuild(['type' => 'Experimental']);
        $experimental->buildGroups()->attach($experimental_group);
        $experimental->tests()->create(['testname' => 'experimental', 'status' => Test::FAILED]);

        $this->browse(function (Browser $browser) use ($nightly_group, $continuous_group): void {
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15");
            $this->assertTestNames($browser, ['nightly', 'continuous']);
            $browser->assertSelected('@group-select', '0');
            self::assertSame(
                ['Non-Experimental Builds', 'Nightly', 'Continuous', 'Experimental'],
                $this->texts($browser, '@group-select option'),
            );

            // The selection is applied by updating the timeline.
            $browser->waitForTextIn('@timeline-chart', 'Failing Tests')
                ->select('@group-select', (string) $continuous_group->id)
                ->check('@show-passed')
                ->waitForReload(fn (Browser $browser) => $browser->click('@timeline-update'));
            self::assertStringEndsWith(
                "/testOverview.php?project={$this->project->name}&date=2025-01-15&group={$continuous_group->id}&showpassed=1",
                $browser->driver->getCurrentURL(),
            );
            $this->assertTestNames($browser, ['continuous', 'continuous-passing']);
            $browser->assertSelected('@group-select', (string) $continuous_group->id)
                ->assertChecked('@show-passed');

            $browser->waitForTextIn('@timeline-chart', 'Failing Tests')
                ->select('@group-select', (string) $nightly_group->id)
                ->uncheck('@show-passed')
                ->waitForReload(fn (Browser $browser) => $browser->click('@timeline-update'));
            self::assertStringEndsWith(
                "/testOverview.php?project={$this->project->name}&date=2025-01-15&group={$nightly_group->id}",
                $browser->driver->getCurrentURL(),
            );
            $this->assertTestNames($browser, ['nightly', 'moved']);
        });
    }

    /**
     * @return array<string,array{string,array<string>,array<string>}>
     */
    public static function columns(): array
    {
        return [
            'subproject' => ['@header-subproject', ['alpha', 'gamma', 'beta', 'delta'], ['delta', 'beta', 'gamma', 'alpha']],
            'test name' => ['@header-test-name', ['gamma', 'delta', 'beta', 'alpha'], ['alpha', 'beta', 'delta', 'gamma']],
            // Percentages and times are compared as numbers, not text.
            'timeout' => ['@header-timeout', ['gamma', 'delta', 'alpha', 'beta'], ['beta', 'alpha', 'delta', 'gamma']],
            'total runs' => ['@header-total-runs', ['alpha', 'delta', 'gamma', 'beta'], ['beta', 'gamma', 'delta', 'alpha']],
            'time' => ['@header-time', ['gamma', 'alpha', 'delta', 'beta'], ['beta', 'delta', 'alpha', 'gamma']],
        ];
    }

    /**
     * @param array<string> $descending
     * @param array<string> $ascending
     */
    #[DataProvider('columns')]
    public function testSortsByColumn(string $header, array $descending, array $ascending): void
    {
        // Four tests, which each column sorts in a different order.  None of the descending orders is the
        // order the tests are listed in at first.  Each test runs once in each of its SubProject's builds.
        $tests = [
            'alpha' => ['sub-d', 30, ['failed', 'timeout', 'passed', 'passed']],
            'beta' => ['sub-b', 1, ['failed']],
            'gamma' => ['sub-c', 100, ['timeout', 'timeout']],
            'delta' => ['sub-a', 9, ['failed', 'timeout', 'passed']],
        ];
        foreach ($tests as $name => [$subproject_name, $time, $runs]) {
            $subproject = $this->project->subprojects()->create(['name' => $subproject_name, 'groupid' => -1]);
            foreach ($runs as $run) {
                $this->createBuild(['subprojectid' => $subproject->id])->tests()->create([
                    'testname' => $name,
                    'status' => $run === 'passed' ? Test::PASSED : Test::FAILED,
                    'details' => $run === 'timeout' ? 'Timeout' : '',
                    'time' => $time,
                ]);
            }
        }

        $this->browse(function (Browser $browser) use ($header, $descending, $ascending): void {
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15");
            $this->assertTestNames($browser, ['beta', 'delta', 'alpha', 'gamma']);
            $browser->assertPresent("{$header} [data-test=\"sort-none\"]");

            // Columns are sorted in descending order first.
            $browser->click($header);
            $this->assertTestNames($browser, $descending);
            $browser->assertPresent("{$header} [data-test=\"sort-descending\"]")
                ->assertPresent('@header-failure [data-test="sort-none"]');

            $browser->click($header);
            $this->assertTestNames($browser, $ascending);
            $browser->assertPresent("{$header} [data-test=\"sort-ascending\"]");
        });
    }

    public function testSortsByFailureByDefault(): void
    {
        $first = $this->createBuild();
        $first->tests()->create(['testname' => 'always', 'status' => Test::FAILED]);
        $first->tests()->create(['testname' => 'sometimes', 'status' => Test::FAILED]);
        $first->tests()->create(['testname' => 'never', 'status' => Test::PASSED]);
        $this->createBuild()->tests()->create(['testname' => 'sometimes', 'status' => Test::PASSED]);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15&showpassed=1");
            $this->assertTestNames($browser, ['always', 'sometimes', 'never']);
            $browser->assertPresent('@header-failure [data-test="sort-descending"]');

            // So clicking the column sorts it in ascending order first.
            $browser->click('@header-failure');
            $this->assertTestNames($browser, ['never', 'sometimes', 'always']);
            $browser->assertPresent('@header-failure [data-test="sort-ascending"]');

            $browser->click('@header-failure');
            $this->assertTestNames($browser, ['always', 'sometimes', 'never']);
            $browser->assertPresent('@header-failure [data-test="sort-descending"]');
        });
    }

    public function testShiftClickSortsByAnotherColumn(): void
    {
        // a-test fails in its only run, b-test in one of two and c-test in one of three, so they're listed in
        // that order at first.
        $first = $this->createBuild();
        $first->tests()->create(['testname' => 'a-test', 'status' => Test::FAILED, 'time' => 1]);
        $first->tests()->create(['testname' => 'b-test', 'status' => Test::FAILED, 'time' => 2]);
        $first->tests()->create(['testname' => 'c-test', 'status' => Test::FAILED, 'time' => 1]);
        $second = $this->createBuild();
        $second->tests()->create(['testname' => 'b-test', 'status' => Test::PASSED, 'time' => 2]);
        $second->tests()->create(['testname' => 'c-test', 'status' => Test::PASSED, 'time' => 1]);
        $this->createBuild()->tests()->create(['testname' => 'c-test', 'status' => Test::PASSED, 'time' => 1]);

        $this->browse(function (Browser $browser): void {
            $shift_click_test_name = fn () => (new WebDriverActions($browser->driver))
                ->keyDown(null, WebDriverKeys::SHIFT)
                ->click($browser->element('@header-test-name'))
                ->keyUp(null, WebDriverKeys::SHIFT)
                ->perform();

            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15");
            $this->assertTestNames($browser, ['a-test', 'b-test', 'c-test']);

            $browser->click('@header-time');
            $this->assertTestNames($browser, ['b-test', 'a-test', 'c-test']);

            // Shift-clicking a column sorts by it as well as the other columns.
            $shift_click_test_name();
            $this->assertTestNames($browser, ['b-test', 'c-test', 'a-test']);
            $browser->assertPresent('@header-time [data-test="sort-descending"]')
                ->assertPresent('@header-test-name [data-test="sort-descending"]');

            $shift_click_test_name();
            $this->assertTestNames($browser, ['b-test', 'a-test', 'c-test']);
            $browser->assertPresent('@header-test-name [data-test="sort-ascending"]');

            // Clicking a column without shift sorts by it alone.
            $browser->click('@header-test-name');
            $this->assertTestNames($browser, ['c-test', 'b-test', 'a-test']);
            $browser->assertPresent('@header-time [data-test="sort-none"]');
        });
    }

    public function testRemembersTheOrder(): void
    {
        $first = $this->createBuild();
        $first->tests()->create(['testname' => 'fast', 'status' => Test::FAILED, 'time' => 1]);
        $first->tests()->create(['testname' => 'slow', 'status' => Test::FAILED, 'time' => 2]);
        $this->createBuild()->tests()->create(['testname' => 'slow', 'status' => Test::PASSED, 'time' => 2]);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15");
            $this->assertTestNames($browser, ['fast', 'slow']);

            $browser->click('@header-time');
            $this->assertTestNames($browser, ['slow', 'fast']);
            self::assertSame('-time', $browser->plainCookie('cdash_test_overview_sort'));

            $browser->refresh();
            $this->assertTestNames($browser, ['slow', 'fast']);
            $browser->assertPresent('@header-time [data-test="sort-descending"]');

            // A sort parameter takes precedence.
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15&sort=totalruns,-name");
            $this->assertTestNames($browser, ['fast', 'slow']);
            $browser->assertPresent('@header-total-runs [data-test="sort-ascending"]')
                ->assertPresent('@header-test-name [data-test="sort-descending"]')
                ->assertPresent('@header-time [data-test="sort-none"]');
        });
    }

    public function testPagesThroughManyTests(): void
    {
        $build = $this->createBuild();
        $names = [];
        for ($i = 1; $i <= 55; $i++) {
            $name = sprintf('test-%02d', $i);
            $names[] = $name;
            $build->tests()->create(['testname' => $name, 'status' => Test::FAILED]);
        }

        $this->browse(function (Browser $browser) use ($names): void {
            // The tests fail equally often, so sort them by name.
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15&sort=name");

            // 50 tests are shown per page by default.
            $this->assertTestNames($browser, array_slice($names, 0, 50));
            self::assertSame('55 tests failed during this time period.', $browser->text('@test-summary'));

            $browser->within('@pagination', fn (Browser $pagination) => $pagination->clickLink('2'));
            $this->assertTestNames($browser, array_slice($names, 50));

            $browser->within('@pagination', fn (Browser $pagination) => $pagination->clickLink('First'));
            $this->assertTestNames($browser, array_slice($names, 0, 50));

            $browser->select('@items-per-page', '10');
            $this->assertTestNames($browser, array_slice($names, 0, 10));

            $browser->within('@pagination', fn (Browser $pagination) => $pagination->clickLink('Last'));
            $this->assertTestNames($browser, array_slice($names, 50));

            // The number of tests per page is remembered.
            self::assertSame('10', $browser->plainCookie('testOverview_num_per_page'));
            $browser->refresh();
            $this->assertTestNames($browser, array_slice($names, 0, 10));

            $browser->select('@items-per-page', '-1');
            $this->assertTestNames($browser, $names);
        });
    }

    public function testShowsAndHidesFilters(): void
    {
        $this->createBuild()->tests()->create(['testname' => 'filtered', 'status' => Test::FAILED]);

        $this->browse(function (Browser $browser): void {
            // Without a showfilters parameter, the filters are hidden and toggled without reloading the page.
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15");
            $this->assertTestNames($browser, ['filtered']);
            $browser->assertMissing('@filters')
                ->click('@show-filters-toggle')
                ->waitFor('@filters')
                ->click('@show-filters-toggle')
                ->waitUntilMissing('@filters')
                ->assertQueryStringMissing('showfilters');

            // Otherwise, the parameter is changed.
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15&filtercount=0&showfilters=1")
                ->waitFor('@filters')
                ->waitForReload(fn (Browser $browser) => $browser->click('@show-filters-toggle'))
                ->assertQueryStringHas('showfilters', '0');
            $this->assertTestNames($browser, ['filtered']);
            $browser->assertMissing('@filters');
        });
    }

    public function testShowsDefaultFilterWhenRequested(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15&showfilters=1")
                ->waitFor('@filters @filter');

            self::assertCount(1, $browser->elements('@filters @filter'));
            self::assertSame('buildname', $browser->value('@filters @filter-field'));
            self::assertSame('63', $browser->value('@filters @filter-compare'));
            self::assertSame('', $browser->value('@filters @filter-value'));
        });
    }

    public function testOffersTheFieldsOfTests(): void
    {
        $this->createBuild(['name' => 'linux'])->tests()->create(['testname' => 'kept', 'status' => Test::FAILED]);
        $this->createBuild(['name' => 'windows'])->tests()->create(['testname' => 'removed', 'status' => Test::FAILED]);
        $this->createBuild(['name' => 'windows'])->tests()->create(['testname' => 'removed', 'status' => Test::PASSED]);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15&showfilters=1")
                ->waitFor('@filters @filter');
            $this->assertTestNames($browser, ['kept', 'removed']);
            self::assertSame(['Build Name', 'SubProject', 'Test Name'], $this->texts($browser, '@filters @filter-field option'));

            $browser->type('@filters @filter-value', 'linux')
                ->waitForReload(fn (Browser $browser) => $browser->click('@filters-apply'))
                ->assertQueryStringHas('field1', 'buildname')
                ->assertQueryStringHas('compare1', '63')
                ->assertQueryStringHas('value1', 'linux');
            $this->assertTestNames($browser, ['kept']);
        });
    }

    /**
     * @return array<string,array{string,string,string,array<string,string>}>
     */
    public static function filters(): array
    {
        return [
            'build name contains' => ['buildname', '63', 'linux', ['core-unit' => '100%', 'core-lint' => '50%']],
            // Only the runs in the selected builds are counted.
            'build name is' => ['buildname', '61', 'windows-1', ['gui-unit' => '100%']],
            'build name does not contain' => ['buildname', '64', 'linux', ['gui-unit' => '33.33%']],
            'subproject is' => ['subproject', '61', 'gui', ['gui-unit' => '33.33%']],
            'subproject is not' => ['subproject', '62', 'gui', ['core-unit' => '100%', 'core-lint' => '50%']],
            'test name contains' => ['testname', '63', 'unit', ['core-unit' => '100%', 'gui-unit' => '33.33%']],
            'test name ends with' => ['testname', '66', 'lint', ['core-lint' => '50%']],
        ];
    }

    /**
     * @param array<string,string> $expected the failure rate of each test, by name
     */
    #[DataProvider('filters')]
    public function testFiltersTests(string $field, string $compare, string $value, array $expected): void
    {
        $runs = [
            'core' => [
                'linux-1' => ['core-unit' => Test::FAILED, 'core-lint' => Test::FAILED],
                'linux-2' => ['core-lint' => Test::PASSED],
            ],
            'gui' => [
                'windows-1' => ['gui-unit' => Test::FAILED],
                'windows-2' => ['gui-unit' => Test::PASSED],
                'windows-3' => ['gui-unit' => Test::PASSED],
            ],
        ];
        foreach ($runs as $subproject_name => $builds) {
            $subproject = $this->project->subprojects()->create(['name' => $subproject_name, 'groupid' => -1]);
            foreach ($builds as $build_name => $tests) {
                $build = $this->createBuild(['name' => $build_name, 'subprojectid' => $subproject->id]);
                foreach ($tests as $test_name => $status) {
                    $build->tests()->create(['testname' => $test_name, 'status' => $status]);
                }
            }
        }

        $this->browse(function (Browser $browser) use ($field, $compare, $value, $expected): void {
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15"
                . "&filtercount=1&showfilters=1&field1={$field}&compare1={$compare}&value1=" . rawurlencode($value));
            $this->assertTestNames($browser, array_keys($expected));
            self::assertSame(array_values($expected), $this->texts($browser, '@test-row @failure'));
        });
    }

    public function testFiltersSelectARangeOfDays(): void
    {
        $this->createBuild(['starttime' => '2025-01-13 12:00:00'])->tests()->create(['testname' => 'earlier', 'status' => Test::FAILED]);
        $this->createBuild()->tests()->create(['testname' => 'later', 'status' => Test::FAILED]);
        $this->createBuild()->tests()->create(['testname' => 'later', 'status' => Test::PASSED]);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15&showfilters=1")
                ->waitFor('@filters @filter');
            $this->assertTestNames($browser, ['later']);
            self::assertSame('2025-01-15', $browser->value('@filter-begin'));
            self::assertSame('2025-01-15', $browser->value('@filter-end'));

            $browser->clear('@filter-begin')
                ->type('@filter-begin', '2025-01-13')
                ->waitForReload(fn (Browser $browser) => $browser->click('@filters-apply'))
                ->assertQueryStringHas('begin', '2025-01-13')
                ->assertQueryStringHas('end', '2025-01-15');
            $this->assertTestNames($browser, ['earlier', 'later']);
        });
    }

    public function testUpdatingTheTimelineKeepsFilters(): void
    {
        $group = $this->project->buildgroups()->where('name', 'Nightly')->firstOrFail();
        foreach (['linux' => 'kept', 'windows' => 'removed'] as $build_name => $test_name) {
            $build = $this->createBuild(['name' => $build_name]);
            $build->buildGroups()->attach($group);
            $build->tests()->create(['testname' => $test_name, 'status' => Test::FAILED]);
        }
        $filters = '&filtercount=1&showfilters=1&field1=buildname&compare1=63&value1=linux';

        $this->browse(function (Browser $browser) use ($group, $filters): void {
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15{$filters}");
            $this->assertTestNames($browser, ['kept']);

            $browser->waitForTextIn('@timeline-chart', 'Failing Tests')
                ->select('@group-select', (string) $group->id)
                ->waitForReload(fn (Browser $browser) => $browser->click('@timeline-update'));
            self::assertStringEndsWith(
                "/testOverview.php?project={$this->project->name}&date=2025-01-15&group={$group->id}{$filters}",
                $browser->driver->getCurrentURL(),
            );
            $this->assertTestNames($browser, ['kept']);
        });
    }

    public function testNavigationLinksKeepTheGroupAndFilters(): void
    {
        $group = $this->project->buildgroups()->where('name', 'Nightly')->firstOrFail();
        $filters = '&filtercount=1&showfilters=1&field1=testname&compare1=63&value1=x';

        $this->browse(function (Browser $browser) use ($group, $filters): void {
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15&group={$group->id}{$filters}")
                ->waitFor('@header-nav-previous');

            $url = "testOverview.php?project={$this->project->name}";
            self::assertStringEndsWith("{$url}&date=2025-01-14&group={$group->id}{$filters}", (string) $browser->attribute('@header-nav-previous', 'href'));
            self::assertStringEndsWith("{$url}&date=" . now()->toDateString() . "&group={$group->id}{$filters}", (string) $browser->attribute('@header-nav-latest', 'href'));
            self::assertStringEndsWith("{$url}&date=2025-01-16&group={$group->id}{$filters}", (string) $browser->attribute('@header-nav-next', 'href'));

            // There's no next day after today.
            $browser->visit("/testOverview.php?project={$this->project->name}&date=" . now()->toDateString())
                ->waitFor('[data-test="header-nav-previous"][href]')
                ->assertNotPresent('[data-test="header-nav-next"][href]');
        });
    }

    public function testCalendarChangesTheDay(): void
    {
        $group = $this->project->buildgroups()->where('name', 'Nightly')->firstOrFail();
        $filters = '&filtercount=1&showfilters=1&field1=testname&compare1=63&value1=x';

        $this->browse(function (Browser $browser) use ($group, $filters): void {
            // The date picker is a jQuery UI widget, which starts on the page's month.
            $browser->visit("/testOverview.php?project={$this->project->name}&date=2025-01-15&group={$group->id}{$filters}")
                ->waitFor('@calendar-toggle')
                ->click('@calendar-toggle')
                ->waitFor('@calendar')
                ->waitForReload(fn (Browser $browser) => $browser->within('@calendar', fn (Browser $calendar) => $calendar->clickLink('20')));

            self::assertStringEndsWith(
                "/testOverview.php?project={$this->project->name}&group={$group->id}&date=2025-01-20{$filters}",
                $browser->driver->getCurrentURL(),
            );
        });
    }
}
