<?php

namespace Tests\Browser\Pages;

use App\Models\Build;
use App\Models\BuildUpdate;
use App\Models\Label;
use App\Models\Project;
use App\Models\Site;
use App\Models\Test;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;

/**
 * Filtering the tests.  The filters panel is shared with other pages, so these tests cover the filters this
 * page offers and the tests they select, rather than the panel itself.
 */
class QueryTestsPageFiltersTest extends BrowserTestCase
{
    use CreatesProjects;

    private Project $project;
    private Site $site;

    /**
     * Records which aren't deleted along with the project.
     *
     * @var array<BuildUpdate|Label|Site>
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
        // Deleting the project deletes its builds and their tests.
        $this->project->delete();
        $this->site->delete();
        foreach ($this->cleanup as $model) {
            $model->delete();
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
     * loading them.
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

    public function testShowsDefaultFilterWhenRequested(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15&showfilters=1")
                ->waitFor('@filters @filter');

            self::assertCount(1, $browser->elements('@filters @filter'));
            self::assertSame('testname', $browser->value('@filters @filter-field'));
            self::assertSame('63', $browser->value('@filters @filter-compare'));
            self::assertSame('', $browser->value('@filters @filter-value'));
        });
    }

    public function testOffersTheFieldsOfTests(): void
    {
        $build = $this->createBuild();
        $build->tests()->create(['testname' => 'kept', 'status' => Test::PASSED]);
        $build->tests()->create(['testname' => 'removed', 'status' => Test::PASSED]);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15&showfilters=1")
                ->waitFor('@filters @filter');
            $this->assertTestNames($browser, ['kept', 'removed']);
            self::assertSame([
                'Build Name',
                'Build Start Time',
                'Details',
                'Group',
                'Label',
                'Revision',
                'Site',
                'Status',
                'Test Name',
                'Test Output',
                'Time',
            ], array_map(fn (RemoteWebElement $option): string => trim($option->getText()), $browser->elements('@filters @filter-field option')));

            $browser->type('@filters @filter-value', 'kept')
                ->waitForReload(fn (Browser $browser) => $browser->click('@filters-apply'))
                ->assertQueryStringHas('field1', 'testname')
                ->assertQueryStringHas('compare1', '63')
                ->assertQueryStringHas('value1', 'kept');
            $this->assertTestNames($browser, ['kept']);
        });
    }

    /**
     * @return array<string,array{string,string,string,array<string>}>
     */
    public static function filters(): array
    {
        return [
            'build name contains' => ['buildname', '63', 'experimental', ['integration']],
            'details is' => ['details', '61', 'Completed', ['unit']],
            'group is' => ['groupname', '61', 'Experimental', ['integration']],
            'group is not' => ['groupname', '62', 'Experimental', ['compile', 'unit']],
            'label contains' => ['label', '63', 'label-x', ['compile']],
            'revision is' => ['revision', '61', 'abc123', ['compile', 'unit']],
            'site contains' => ['site', '63', 'beta-site', ['integration']],
            'status is not' => ['status', '62', 'passed', ['compile', 'integration']],
            'test name does not contain' => ['testname', '64', 'unit', ['compile', 'integration']],
            'time is' => ['time', '41', '0', ['integration', 'unit']],
            'time is greater than' => ['time', '43', '1', ['compile']],
        ];
    }

    /**
     * @param array<string> $expected
     */
    #[DataProvider('filters')]
    public function testFiltersTests(string $field, string $compare, string $value, array $expected): void
    {
        // compile and unit are in a Nightly build of revision abc123 at alpha-site.  integration is in an
        // Experimental build at beta-site.
        $this->cleanup[] = $alpha_site = Site::factory()->create(['name' => 'alpha-site-' . Str::uuid()->toString()]);
        $this->cleanup[] = $update = BuildUpdate::factory()->create(['revision' => 'abc123']);
        $nightly = $this->createBuild(['name' => 'nightly-build', 'siteid' => $alpha_site->id, 'updateid' => $update->id]);
        $nightly->buildGroups()->attach($this->project->buildgroups()->where('name', 'Nightly')->firstOrFail());
        $this->cleanup[] = $label_x = Label::factory()->create(['text' => 'label-x-' . Str::uuid()->toString()]);
        $nightly->tests()
            ->create(['testname' => 'compile', 'status' => Test::FAILED, 'details' => 'Completed (Failed)', 'time' => 5])
            ->labels()
            ->attach($label_x);
        $nightly->tests()->create(['testname' => 'unit', 'status' => Test::PASSED, 'details' => 'Completed']);

        $this->cleanup[] = $beta_site = Site::factory()->create(['name' => 'beta-site-' . Str::uuid()->toString()]);
        $experimental = $this->createBuild(['name' => 'experimental-build', 'type' => 'Experimental', 'siteid' => $beta_site->id]);
        $experimental->buildGroups()->attach($this->project->buildgroups()->where('name', 'Experimental')->firstOrFail());
        $this->cleanup[] = $label_y = Label::factory()->create(['text' => 'label-y-' . Str::uuid()->toString()]);
        $experimental->tests()
            ->create(['testname' => 'integration', 'status' => Test::NOTRUN, 'details' => 'Disabled'])
            ->labels()
            ->attach($label_y);

        $this->browse(function (Browser $browser) use ($field, $compare, $value, $expected): void {
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15"
                . "&filtercount=1&showfilters=1&field1={$field}&compare1={$compare}&value1=" . rawurlencode($value));
            $this->assertTestNames($browser, $expected);
            self::assertSame((string) count($expected), $browser->text('@test-count'));
        });
    }

    public function testFilteringByBuildStartTimeIncludesOtherDays(): void
    {
        foreach (['2025-01-13', '2025-01-15', '2025-01-17'] as $date) {
            $this->createBuild(['starttime' => "{$date} 12:00:00"])->tests()->create(['testname' => "test-{$date}", 'status' => Test::PASSED]);
        }
        $this->createBuild(['starttime' => now('UTC')->toDateTimeString()])->tests()->create(['testname' => 'test-today', 'status' => Test::PASSED]);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15"
                . '&filtercount=1&showfilters=1&field1=buildstarttime&compare1=83&value1=2025-01-14');
            $this->assertTestNames($browser, ['test-2025-01-15', 'test-2025-01-17', 'test-today']);

            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15"
                . '&filtercount=1&showfilters=1&field1=buildstarttime&compare1=84&value1=2025-01-14');
            $this->assertTestNames($browser, ['test-2025-01-13']);

            // Relative dates can be used too.
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15"
                . '&filtercount=1&showfilters=1&field1=buildstarttime&compare1=83&value1=yesterday');
            $this->assertTestNames($browser, ['test-today']);
        });
    }

    public function testLimitsTheNumberOfTests(): void
    {
        $build = $this->createBuild();
        foreach (['a', 'b', 'c'] as $name) {
            $build->tests()->create(['testname' => $name, 'status' => Test::PASSED]);
        }

        $this->browse(function (Browser $browser): void {
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15&limit=2");
            $this->assertTestNames($browser, ['a', 'b']);
        });
    }

    /**
     * @return array<string,array{string,string,array<string>}>
     */
    public static function outputFilters(): array
    {
        return [
            'exclude' => ['94', 'Segmentation fault', ['passed-output', 'timeout-output']],
            'include' => ['95', 'Segmentation fault', ['crash-output']],
            'exclude regex' => ['96', 'T.meout|Seg', ['passed-output']],
            'include regex' => ['97', 'T.meout', ['timeout-output']],
        ];
    }

    /**
     * @param array<string> $expected
     */
    #[DataProvider('outputFilters')]
    public function testFiltersByTestOutput(string $compare, string $value, array $expected): void
    {
        $build = $this->createBuild();
        $build->tests()->create(['testname' => 'crash-output', 'status' => Test::PASSED, 'output' => "Running...\nSegmentation fault (core dumped)\n"]);
        $build->tests()->create(['testname' => 'passed-output', 'status' => Test::PASSED, 'output' => "Running...\n100% tests passed\n"]);
        $build->tests()->create(['testname' => 'timeout-output', 'status' => Test::PASSED, 'output' => "Running...\nTimeout after 60s\n"]);

        $this->browse(function (Browser $browser) use ($compare, $value, $expected): void {
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15"
                . "&filtercount=1&showfilters=1&field1=testoutput&compare1={$compare}&value1=" . rawurlencode($value));
            $this->assertTestNames($browser, $expected);
        });
    }

    public function testKeepsTheFiltersWhenTheTestsCannotBeLoaded(): void
    {
        $this->createBuild(['starttime' => '2025-01-14 12:00:00'])->tests()->create(['testname' => 'crash', 'status' => Test::FAILED, 'output' => 'Segmentation fault']);
        $this->createBuild()->tests()->create(['testname' => 'later-crash', 'status' => Test::FAILED, 'output' => 'Segmentation fault']);

        $this->browse(function (Browser $browser): void {
            // The regular expression is invalid.
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-14"
                . '&filtercount=1&showfilters=1&field1=testoutput&compare1=97&value1=' . rawurlencode('Seg('))
                ->waitFor('@error-message')
                ->assertNotPresent('@tests-table')
                ->waitFor('@filters @filter');
            self::assertSame('Seg(', $browser->value('@filters @filter-value'));
            self::assertSame('2025-01-14', $browser->value('@filter-begin'));

            $browser->clear('@filters @filter-value')
                ->type('@filters @filter-value', 'Seg.')
                ->waitForReload(fn (Browser $browser) => $browser->click('@filters-apply'))
                ->assertQueryStringHas('date', '2025-01-14');
            $this->assertTestNames($browser, ['crash']);
        });
    }

    public function testShowsTheMatchingTestOutput(): void
    {
        // CTest replaces the escape character of ANSI color codes.
        $output = "Running...\n[NON-XML-CHAR-0x1B][31mSegmentation fault[NON-XML-CHAR-0x1B][0m in <main>\n";
        $this->createBuild()->tests()->create(['testname' => 'crash', 'status' => Test::FAILED, 'output' => $output]);

        $this->browse(function (Browser $browser): void {
            // The output is only available when filtering on it.
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15");
            $this->assertTestNames($browser, ['crash']);
            $browser->assertNotPresent('@matching-output-toggle')
                ->assertNotPresent('@test-row @matching-output');

            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-15"
                . '&filtercount=1&showfilters=1&field1=testoutput&compare1=95&value1=Segmentation%20fault');
            $this->assertTestNames($browser, ['crash']);
            $browser->assertMissing('@header-matching-output')
                ->assertMissing('@test-row @matching-output')
                ->click('@matching-output-toggle')
                ->waitFor('@test-row @matching-output')
                ->assertVisible('@header-matching-output');

            // Colors are shown, and the output isn't interpreted as HTML.
            self::assertSame("Running...\nSegmentation fault in <main>", $browser->text('@test-row @matching-output'));
            $colored = $browser->driver->findElement(WebDriverBy::xpath('//*[@data-test="matching-output"]//*[text()="Segmentation fault"]'));
            self::assertNotSame($browser->element('@test-row @matching-output')?->getCSSValue('color'), $colored->getCSSValue('color'));

            $browser->click('@matching-output-toggle')
                ->waitUntilMissing('@test-row @matching-output');
        });
    }

    public function testHeaderLinksToTestsWhichDidNotPass(): void
    {
        $build = $this->createBuild();
        $build->tests()->create(['testname' => 'failed', 'status' => Test::FAILED]);
        $build->tests()->create(['testname' => 'not-run', 'status' => Test::NOTRUN]);
        $build->tests()->create(['testname' => 'passed', 'status' => Test::PASSED]);
        $url = "/queryTests.php?project={$this->project->name}&date=2025-01-15&filtercount=1&showfilters=1&field1=status&compare1=62&value1=passed";

        $this->browse(function (Browser $browser) use ($url): void {
            // The link is for the day of the project's most recent build, not the day being viewed.
            $browser->visit("/queryTests.php?project={$this->project->name}&date=2025-01-10")
                ->waitForTextIn('@test-count', '0');
            self::assertStringEndsWith($url, (string) $browser->attribute('@header-tests-link', 'href'));

            $browser->visit($url)
                ->waitFor('@filters @filter');
            $this->assertTestNames($browser, ['failed', 'not-run']);
            self::assertSame('status', $browser->value('@filters @filter-field'));
            self::assertSame('62', $browser->value('@filters @filter-compare'));
            self::assertSame('passed', $browser->value('@filters @filter-value'));
        });
    }
}
