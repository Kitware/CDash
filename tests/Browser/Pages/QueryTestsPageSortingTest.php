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
use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverKeys;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;

/**
 * Sorting the tests by clicking the column headers.
 *
 * The page is going to be rewritten, so these tests only interact with it through data-test attributes,
 * URLs, cookies and the database.  Avoid selectors which depend on the page's markup or framework.
 */
class QueryTestsPageSortingTest extends BrowserTestCase
{
    use CreatesProjects;

    private Project $project;
    private Site $site;

    /**
     * Records which aren't deleted along with the project.
     *
     * @var array<Label|Site>
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
                // The page saves its sort order in a cookie, and Dusk reuses the browser for each test in a class.
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
     * Wait for the page to list the tests with these names, in this order.  The page lists the tests after
     * loading them, and lists them again when they're sorted.
     *
     * @param array<string> $expected
     */
    private function assertTestNames(Browser $browser, array $expected): void
    {
        $actual = null;
        try {
            $browser->waitUsing(null, 100, function () use ($browser, $expected, &$actual): bool {
                $actual = array_map(fn (RemoteWebElement $element): string => trim($element->getText()), $browser->elements('@test-row @test-name'));
                return $actual === $expected;
            });
        } catch (TimeoutException) {
            // Fall through to the assertion below, which reports the difference.
        }
        self::assertSame($expected, $actual);
    }

    /**
     * @return array<string,array{string,array<string>,array<string>}>
     */
    public static function columns(): array
    {
        return [
            'site' => ['@header-site', ['delta', 'alpha', 'beta', 'gamma'], ['gamma', 'beta', 'alpha', 'delta']],
            'build name' => ['@header-build-name', ['beta', 'delta', 'alpha', 'gamma'], ['gamma', 'alpha', 'delta', 'beta']],
            'test name' => ['@header-test-name', ['gamma', 'delta', 'beta', 'alpha'], ['alpha', 'beta', 'delta', 'gamma']],
            // Tests with the same status keep their order, so the ascending order is the order they're listed in at first.
            'status' => ['@header-status', ['alpha', 'delta', 'gamma', 'beta'], ['beta', 'gamma', 'alpha', 'delta']],
            // Times are compared as numbers, not text.
            'time' => ['@header-time', ['delta', 'gamma', 'alpha', 'beta'], ['beta', 'alpha', 'gamma', 'delta']],
            'details' => ['@header-details', ['alpha', 'gamma', 'beta', 'delta'], ['delta', 'beta', 'gamma', 'alpha']],
            'labels' => ['@header-labels', ['gamma', 'delta', 'alpha', 'beta'], ['beta', 'alpha', 'delta', 'gamma']],
            'build time' => ['@header-build-time', ['gamma', 'beta', 'delta', 'alpha'], ['alpha', 'delta', 'beta', 'gamma']],
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
        // order the tests are listed in at first.
        $tests = [
            'alpha' => ['site-3', 'build-2', Test::PASSED, 9, 'details-4', 'label-2', '10:00:00'],
            'beta' => ['site-2', 'build-4', Test::FAILED, 1, 'details-2', 'label-1', '12:00:00'],
            'gamma' => ['site-1', 'build-1', Test::NOTRUN, 30, 'details-3', 'label-4', '13:00:00'],
            'delta' => ['site-4', 'build-3', Test::PASSED, 100, 'details-1', 'label-3', '11:00:00'],
        ];
        foreach ($tests as $name => [$site_name, $build_name, $status, $time, $details, $label_text, $starttime]) {
            $this->cleanup[] = $site = Site::factory()->create(['name' => "{$site_name}-" . Str::uuid()->toString()]);
            $this->cleanup[] = $label = Label::factory()->create(['text' => "{$label_text}-" . Str::uuid()->toString()]);
            $this->createBuild(['name' => $build_name, 'siteid' => $site->id, 'starttime' => "2025-01-15 {$starttime}"])
                ->tests()
                ->create(['testname' => $name, 'status' => $status, 'time' => $time, 'details' => $details])
                ->labels()
                ->attach($label);
        }

        $this->browse(function (Browser $browser) use ($header, $descending, $ascending): void {
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15");
            // Failed tests are listed first, then tests which didn't run, then passing tests.
            $this->assertTestNames($browser, ['beta', 'gamma', 'alpha', 'delta']);
            $browser->assertPresent("{$header} [data-test=\"sort-none\"]");

            // Columns are sorted in descending order first.
            $browser->click($header);
            $this->assertTestNames($browser, $descending);
            $browser->assertPresent("{$header} [data-test=\"sort-descending\"]");

            $browser->click($header);
            $this->assertTestNames($browser, $ascending);
            $browser->assertPresent("{$header} [data-test=\"sort-ascending\"]");
        });
    }

    public function testShiftClickSortsByAnotherColumn(): void
    {
        $build = $this->createBuild();
        $build->tests()->create(['testname' => 'a-passed', 'status' => Test::PASSED, 'time' => 1]);
        $build->tests()->create(['testname' => 'b-passed', 'status' => Test::PASSED, 'time' => 2]);
        $build->tests()->create(['testname' => 'c-passed', 'status' => Test::PASSED, 'time' => 1]);

        $this->browse(function (Browser $browser): void {
            $shift_click_test_name = fn () => (new WebDriverActions($browser->driver))
                ->keyDown(null, WebDriverKeys::SHIFT)
                ->click($browser->element('@header-test-name'))
                ->keyUp(null, WebDriverKeys::SHIFT)
                ->perform();

            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15");
            $this->assertTestNames($browser, ['a-passed', 'b-passed', 'c-passed']);

            $browser->click('@header-time');
            $this->assertTestNames($browser, ['b-passed', 'a-passed', 'c-passed']);

            // Shift-clicking a column sorts by it as well as the other columns.
            $shift_click_test_name();
            $this->assertTestNames($browser, ['b-passed', 'c-passed', 'a-passed']);
            $browser->assertPresent('@header-time [data-test="sort-descending"]')
                ->assertPresent('@header-test-name [data-test="sort-descending"]');

            $shift_click_test_name();
            $this->assertTestNames($browser, ['b-passed', 'a-passed', 'c-passed']);
            $browser->assertPresent('@header-test-name [data-test="sort-ascending"]');

            // Clicking a column without shift sorts by it alone.
            $browser->click('@header-test-name');
            $this->assertTestNames($browser, ['c-passed', 'b-passed', 'a-passed']);
            $browser->assertPresent('@header-time [data-test="sort-none"]');
        });
    }

    public function testRemembersTheOrder(): void
    {
        $build = $this->createBuild();
        $build->tests()->create(['testname' => 'fast', 'status' => Test::PASSED, 'time' => 1]);
        $build->tests()->create(['testname' => 'slow', 'status' => Test::PASSED, 'time' => 2]);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15");
            $this->assertTestNames($browser, ['fast', 'slow']);

            $browser->click('@header-time');
            $this->assertTestNames($browser, ['slow', 'fast']);
            self::assertSame('-time', $browser->plainCookie('cdash_query_tests_sort'));

            $browser->refresh();
            $this->assertTestNames($browser, ['slow', 'fast']);
            $browser->assertPresent('@header-time [data-test="sort-descending"]');
        });
    }

    public function testSortsByPinnedMeasurement(): void
    {
        // Color is the second measurement column.
        PinnedTestMeasurement::factory()->for($this->project)->create(['name' => 'Host', 'position' => 1]);
        PinnedTestMeasurement::factory()->for($this->project)->create(['name' => 'Color', 'position' => 2]);
        $build = $this->createBuild();
        foreach (['first' => 'green', 'second' => 'red', 'third' => 'blue'] as $name => $color) {
            $test = $build->tests()->create(['testname' => $name, 'status' => Test::PASSED]);
            TestMeasurement::factory()->for($test)->create(['name' => 'Color', 'value' => $color]);
        }

        $this->browse(function (Browser $browser): void {
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15");
            $this->assertTestNames($browser, ['first', 'second', 'third']);

            $browser->click('@header-measurement-1');
            $this->assertTestNames($browser, ['second', 'first', 'third']);
            $browser->assertPresent('@header-measurement-1 [data-test="sort-descending"]')
                ->assertPresent('@header-measurement-0 [data-test="sort-none"]');

            $browser->click('@header-measurement-1');
            $this->assertTestNames($browser, ['third', 'first', 'second']);
            $browser->assertPresent('@header-measurement-1 [data-test="sort-ascending"]');

            // The order is remembered.
            $browser->refresh();
            $this->assertTestNames($browser, ['third', 'first', 'second']);
        });
    }
}
