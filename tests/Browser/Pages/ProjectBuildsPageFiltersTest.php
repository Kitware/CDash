<?php

namespace Tests\Browser\Pages;

use Illuminate\Support\Carbon;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The legacy filters, and the links which carry the current filters and dates to other pages.
 */
class ProjectBuildsPageFiltersTest extends ProjectBuildsPageTestCase
{
    /**
     * A selector for the nth top-level filter.
     */
    private function filter(int $n): string
    {
        return "@filters [data-test=\"filter\"]:nth-of-type({$n})";
    }

    private function currentUrl(Browser $browser): string
    {
        return $browser->driver->getCurrentURL();
    }

    /**
     * Click a button which navigates to a new URL, and return that URL.
     */
    private function clickAndGetUrl(Browser $browser, string $selector): string
    {
        $browser->waitForReload(fn (Browser $browser) => $browser->click($selector));
        return $this->currentUrl($browser);
    }

    /**
     * Clear a date input and close the date picker it opens, so it doesn't cover anything.
     */
    private function clearDate(Browser $browser, string $selector): Browser
    {
        return $browser->keys($selector, ['{control}', 'a'], '{backspace}', '{escape}');
    }

    public function testShowsDefaultFilterWhenRequested(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl('&showfilters=1'))
                ->waitFor('@filters @filter');

            self::assertCount(1, $browser->elements('@filters @filter'));
            self::assertSame('site', $browser->value("{$this->filter(1)} @filter-field"));
            self::assertSame('63', $browser->value("{$this->filter(1)} @filter-compare"));
            self::assertSame('', $browser->value("{$this->filter(1)} @filter-value"));
            // A filter can't be removed if it is the only one.
            self::assertFalse($browser->element("{$this->filter(1)} @filter-remove")?->isEnabled());
        });
    }

    public function testFiltersBuilds(): void
    {
        $this->createBuild(['name' => 'alpha']);
        $this->createBuild(['name' => 'beta']);

        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl('&showfilters=1'))
                ->waitFor('@filters @filter')
                ->select("{$this->filter(1)} @filter-field", 'buildname')
                ->select("{$this->filter(1)} @filter-compare", '61')
                ->type("{$this->filter(1)} @filter-value", 'alpha');

            self::assertStringContainsString(
                $this->indexUrl('&filtercount=1&showfilters=1&field1=buildname&compare1=61&value1=alpha'),
                $this->clickAndGetUrl($browser, '@filters-apply'),
            );
            $browser->waitFor('@build-group-Nightly');
            $this->assertBuildOrder($browser, ['alpha']);

            // The filters are shown as they were applied.
            $browser->waitFor('@filters @filter');
            self::assertSame('buildname', $browser->value("{$this->filter(1)} @filter-field"));
            self::assertSame('61', $browser->value("{$this->filter(1)} @filter-compare"));
            self::assertSame('alpha', $browser->value("{$this->filter(1)} @filter-value"));
        });
    }

    public function testInsertsNewFiltersAfterTheCurrentOne(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl('&showfilters=1'))
                ->waitFor('@filters @filter')
                ->select("{$this->filter(1)} @filter-field", 'label')
                ->select("{$this->filter(1)} @filter-compare", '63')
                ->type("{$this->filter(1)} @filter-value", 'a')
                // New filters are copies of the filter they're added after.
                ->click("{$this->filter(1)} @filter-add")
                ->type("{$this->filter(2)} @filter-value", 'b')
                ->click("{$this->filter(1)} @filter-add");

            $this->assertValues($browser, '@filters @filter @filter-value', ['a', 'a', 'b']);
            self::assertStringContainsString(
                $this->indexUrl('&filtercount=3&showfilters=1&filtercombine=and&field1=label&compare1=63&value1=a&field2=label&compare2=63&value2=a&field3=label&compare3=63&value3=b'),
                $this->clickAndGetUrl($browser, '@filters-apply'),
            );
        });
    }

    public function testRemovesFilters(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl('&filtercount=2&showfilters=1&filtercombine=and&field1=site&compare1=63&value1=first&field2=buildname&compare2=63&value2=second'))
                ->waitFor('@filters @filter')
                ->click("{$this->filter(1)} @filter-remove");

            self::assertCount(1, $browser->elements('@filters @filter'));
            self::assertSame('buildname', $browser->value("{$this->filter(1)} @filter-field"));
            self::assertSame('second', $browser->value("{$this->filter(1)} @filter-value"));
        });
    }

    public function testResetsIncompatibleComparisons(): void
    {
        $this->browse(function (Browser $browser): void {
            $field = "{$this->filter(1)} @filter-field";
            $compare = "{$this->filter(1)} @filter-compare";

            $browser->visit($this->indexUrl('&showfilters=1'))
                ->waitFor('@filters @filter')
                ->select($compare, '61')
                // Another text field keeps the comparison.
                ->select($field, 'buildname');
            self::assertSame('61', $browser->value($compare));

            // A numeric field doesn't support the comparison, so it's cleared.
            $browser->select($field, 'builderrors');
            self::assertSame('0', $browser->value($compare));
            $this->assertTexts(
                $browser,
                "{$compare} option",
                ['-- choose comparison --', 'is', 'is not', 'is greater than', 'is less than'],
            );
        });
    }

    public function testCombinesFilters(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl('&showfilters=1'))
                ->waitFor('@filters @filter')
                ->assertNotPresent('@filter-combine')
                ->click("{$this->filter(1)} @filter-add")
                ->click("{$this->filter(2)} @filter-add")
                ->select('@filters @filter-combine', 'or');

            // The combination is only selectable on the first filter, and repeated between the others.
            self::assertCount(1, $browser->elements('@filters @filter-combine'));
            $this->assertTexts($browser, '@filters @filter-combine-label', ['or']);
            self::assertStringContainsString(
                '&filtercount=3&showfilters=1&filtercombine=or&field1=site',
                $this->clickAndGetUrl($browser, '@filters-apply'),
            );
            $browser->waitFor('@filters @filter');
            self::assertSame('or', $browser->value('@filters @filter-combine'));
        });
    }

    public function testCombinesFiltersInBlocks(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl('&showfilters=1'))
                ->waitFor('@filters @filter')
                ->click("{$this->filter(1)} @filter-add-block")
                ->waitFor("{$this->filter(2)} @subfilter")
                ->click("{$this->filter(2)} @subfilter @filter-add")
                ->select("{$this->filter(2)} [data-test=\"subfilter\"]:nth-of-type(2) @filter-field", 'buildname')
                ->type("{$this->filter(2)} [data-test=\"subfilter\"]:nth-of-type(2) @filter-value", 'x');

            // The filters in a block are combined the opposite way to the top-level filters.
            $this->assertTexts($browser, "{$this->filter(2)} @filter-block-combine", ['or']);
            self::assertStringContainsString(
                '&filtercount=2&showfilters=1&filtercombine=and&field1=site&compare1=63&value1=&field2=block&field2count=2&field2field1=site&field2compare1=63&field2value1=&field2field2=buildname&field2compare2=63&field2value2=x',
                $this->clickAndGetUrl($browser, '@filters-apply'),
            );
            $browser->waitFor("{$this->filter(2)} @subfilter");
            self::assertCount(2, $browser->elements("{$this->filter(2)} @subfilter"));
        });
    }

    public function testClearsFilters(): void
    {
        $this->createBuild(['name' => 'alpha']);
        $this->createBuild(['name' => 'beta', 'starttime' => self::DATE . ' 13:00:00']);

        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl('&filtercount=1&showfilters=1&field1=buildname&compare1=61&value1=alpha'))
                ->waitFor('@build-group-Nightly')
                ->waitFor('@filters @filter');
            $this->assertBuildOrder($browser, ['alpha']);

            self::assertStringContainsString(
                $this->indexUrl('&filtercount=0&showfilters=1'),
                $this->clickAndGetUrl($browser, '@filters-clear'),
            );
            $browser->waitFor('@build-group-Nightly');
            $this->assertBuildOrder($browser, ['beta', 'alpha']);
        });
    }

    public function testCreatesHyperlink(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl('&showfilters=1'))
                ->waitFor('@filters @filter')
                ->type("{$this->filter(1)} @filter-value", 'alpha')
                ->click('@filters-create-hyperlink')
                ->waitFor('@filters-hyperlink a');

            $expected = $this->indexUrl('&filtercount=1&showfilters=1&field1=site&compare1=63&value1=alpha');
            self::assertStringEndsWith($expected, $browser->text('@filters-hyperlink a'));
            self::assertStringEndsWith($expected, $this->href($browser, '@filters-hyperlink a'));
            // Creating a link doesn't apply the filters.
            self::assertStringEndsWith($this->indexUrl('&showfilters=1'), $this->currentUrl($browser));
        });
    }

    public function testKeepsLimit(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl('&showfilters=1&limit=5'))
                ->waitFor('@filters @filter');

            self::assertStringContainsString(
                '&filtercount=1&showfilters=1&limit=5&field1=site',
                $this->clickAndGetUrl($browser, '@filters-apply'),
            );
        });
    }

    public function testDateRangeIsEmptyByDefault(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl('&showfilters=1', null))
                ->waitFor('@filters @filter');
            self::assertSame('', $browser->value('@filter-begin'));
            self::assertSame('', $browser->value('@filter-end'));

            self::assertStringContainsString(
                $this->indexUrl('&filtercount=1&showfilters=1&field1=site&compare1=63&value1=', null),
                $this->clickAndGetUrl($browser, '@filters-apply'),
            );
        });
    }

    public function testDateRangeShowsDate(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl('&showfilters=1'))
                ->waitFor('@filters @filter');
            self::assertSame(self::DATE, $browser->value('@filter-begin'));
            self::assertSame(self::DATE, $browser->value('@filter-end'));
        });
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function singleDates(): array
    {
        return [
            'begin' => ['begin', '@filter-end'],
            'end' => ['end', '@filter-begin'],
        ];
    }

    #[DataProvider('singleDates')]
    public function testDateRangeWithOneDateUsesThatDate(string $parameter, string $other_input): void
    {
        $this->browse(function (Browser $browser) use ($parameter, $other_input): void {
            $browser->visit($this->indexUrl("&{$parameter}=" . self::DATE . '&showfilters=1', null))
                ->waitFor('@filters @filter');
            self::assertSame(self::DATE, $browser->value('@filter-begin'));
            self::assertSame(self::DATE, $browser->value('@filter-end'));

            $this->clearDate($browser, $other_input);
            self::assertStringContainsString(
                $this->indexUrl('&filtercount=1&showfilters=1&field1=site&compare1=63&value1='),
                $this->clickAndGetUrl($browser, '@filters-apply'),
            );
        });
    }

    public function testDateRangeSelectsRange(): void
    {
        $this->createBuild(['name' => 'before', 'starttime' => '2025-01-13 12:00:00']);
        $this->createBuild(['name' => 'first', 'starttime' => '2025-01-14 12:00:00']);
        $this->createBuild(['name' => 'last', 'starttime' => '2025-01-16 12:00:00']);
        $this->createBuild(['name' => 'after', 'starttime' => '2025-01-17 12:00:00']);

        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl('&begin=2025-01-14&end=2025-01-16&showfilters=1', null))
                ->waitFor('@build-group-Nightly')
                ->waitFor('@filters @filter');
            self::assertSame('2025-01-14', $browser->value('@filter-begin'));
            self::assertSame('2025-01-16', $browser->value('@filter-end'));
            $this->assertBuildOrder($browser, ['last', 'first']);

            self::assertStringContainsString(
                $this->indexUrl('&begin=2025-01-14&end=2025-01-16&filtercount=1&showfilters=1&field1=site&compare1=63&value1=', null),
                $this->clickAndGetUrl($browser, '@filters-apply'),
            );
        });
    }

    public function testClearingDateRangeRemovesDates(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl('&end=' . self::DATE . '&showfilters=1', null))
                ->waitFor('@filters @filter');
            $this->clearDate($browser, '@filter-begin');
            $this->clearDate($browser, '@filter-end');

            self::assertStringContainsString(
                $this->indexUrl('&filtercount=1&showfilters=1&field1=site&compare1=63&value1=', null),
                $this->clickAndGetUrl($browser, '@filters-apply'),
            );
        });
    }

    public function testCalendarSelectsDate(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl('&begin=yesterday&end=today&filtercount=0&showfilters=1', null))
                ->waitFor('@filters')
                ->assertMissing('@calendar')
                ->click('@calendar-toggle')
                ->waitFor('@calendar')
                ->click('@calendar-toggle')
                ->waitUntilMissing('@calendar')
                ->click('@calendar-toggle')
                ->waitFor('@calendar');

            // The date picker is a jQuery UI widget.  Picking a date replaces any date range in the URL.
            $today = Carbon::now('UTC')->toDateString();
            self::assertStringContainsString(
                $this->indexUrl('&filtercount=0&showfilters=1', $today),
                $this->clickAndGetUrl($browser, '@calendar .ui-datepicker-today a'),
            );
        });
    }

    public function testNavigationLinksKeepFilters(): void
    {
        $this->createBuild(['name' => 'monday', 'starttime' => '2025-01-13 12:00:00']);
        $this->createBuild(['name' => 'wednesday', 'starttime' => '2025-01-15 12:00:00']);
        $this->createBuild(['name' => 'friday', 'starttime' => '2025-01-17 12:00:00']);
        $filters = '&filtercount=1&showfilters=1&field1=buildname&compare1=63&value1=day';

        $this->browse(function (Browser $browser) use ($filters): void {
            $browser->visit($this->indexUrl($filters))
                ->waitFor('@build-group-Nightly');
            $this->assertBuildOrder($browser, ['wednesday']);

            // Days without builds are skipped.
            self::assertStringContainsString($this->indexUrl($filters, '2025-01-13'), $this->href($browser, '@header-nav-previous'));
            self::assertStringContainsString($this->indexUrl($filters, '2025-01-17'), $this->href($browser, '@header-nav-next'));
            self::assertStringContainsString($this->indexUrl($filters, null), $this->href($browser, '@header-nav-latest'));
            self::assertStringContainsString(
                "viewBuildGroup.php?project={$this->project->name}&buildgroup=Nightly&date=" . self::DATE,
                $this->href($browser, '@build-group-Nightly @view-timeline-link'),
            );
            self::assertStringContainsString($filters, $this->href($browser, '@build-group-Nightly @view-timeline-link'));

            $browser->waitForReload(fn (Browser $browser) => $browser->click('@header-nav-next'))
                ->waitFor('@build-group-Nightly');
            $this->assertBuildOrder($browser, ['friday']);
            self::assertStringContainsString($this->indexUrl($filters, '2025-01-15'), $this->href($browser, '@header-nav-previous'));
            $browser->assertNotPresent('[data-test="header-nav-next"][href]');
        });
    }

    public function testTimelineSelectsDates(): void
    {
        $this->createBuild(['name' => 'broken', 'builderrors' => 1, 'buildwarnings' => 0]);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/viewBuildGroup.php?project={$this->project->name}&buildgroup=Nightly&date=" . self::DATE)
                ->waitFor('@timeline-chart svg');

            self::assertStringContainsString(
                "viewBuildGroup.php?project={$this->project->name}&buildgroup=Nightly&date=" . self::DATE,
                $this->clickAndGetUrl($browser, '@timeline-update'),
            );
        });
    }
}
