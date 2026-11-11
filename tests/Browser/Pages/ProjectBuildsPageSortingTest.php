<?php

namespace Tests\Browser\Pages;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\DataProvider;

class ProjectBuildsPageSortingTest extends ProjectBuildsPageTestCase
{
    /**
     * Three builds with a different order for every column, and no ties.
     */
    private function createSortableBuilds(): void
    {
        $builds = [
            'build-c' => ['b', 1, 2, 0, 1, 2, 0, 1, 2, '12:00:00'],
            'build-a' => ['c', 3, 0, 2, 2, 1, 1, 0, 1, '11:00:00'],
            'build-b' => ['a', 2, 1, 1, 0, 0, 2, 2, 0, '13:00:00'],
        ];
        foreach ($builds as $name => [$site, $files, $configure_errors, $configure_warnings, $build_errors, $build_warnings, $not_run, $failed, $passed, $time]) {
            $this->createBuild([
                'name' => $name,
                'siteid' => $this->createSite($site)->id,
                'updateid' => $this->createUpdate(['nfiles' => $files])->id,
                'configureerrors' => $configure_errors,
                'configurewarnings' => $configure_warnings,
                'builderrors' => $build_errors,
                'buildwarnings' => $build_warnings,
                'testnotrun' => $not_run,
                'testfailed' => $failed,
                'testpassed' => $passed,
                'starttime' => self::DATE . ' ' . $time,
            ]);
        }
    }

    public function testShowsBuildsWithProblemsFirst(): void
    {
        $this->createSortableBuilds();

        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly');

            // Configure errors take priority over the other problems.
            $this->assertBuildOrder($browser, ['build-c', 'build-b', 'build-a']);
            $this->assertSortIndicator($browser, '@build-group-Nightly @header-configure-errors', 'descending');
            $this->assertSortIndicator($browser, '@build-group-Nightly @header-site', 'none');
        });
    }

    /**
     * @return array<string,array{string,array<string>}>
     */
    public static function columns(): array
    {
        return [
            'site' => ['site', ['build-a', 'build-c', 'build-b']],
            'build name' => ['build-name', ['build-c', 'build-b', 'build-a']],
            'updated files' => ['update-files', ['build-a', 'build-b', 'build-c']],
            'configure errors' => ['configure-errors', ['build-c', 'build-b', 'build-a']],
            'configure warnings' => ['configure-warnings', ['build-a', 'build-b', 'build-c']],
            'build errors' => ['build-errors', ['build-a', 'build-c', 'build-b']],
            'build warnings' => ['build-warnings', ['build-c', 'build-a', 'build-b']],
            'tests not run' => ['tests-not-run', ['build-b', 'build-a', 'build-c']],
            'tests failed' => ['tests-failed', ['build-b', 'build-c', 'build-a']],
            'tests passed' => ['tests-passed', ['build-c', 'build-a', 'build-b']],
            'start time' => ['start-time', ['build-b', 'build-c', 'build-a']],
        ];
    }

    /**
     * @param array<string> $descending
     */
    #[DataProvider('columns')]
    public function testSortsByColumn(string $column, array $descending): void
    {
        $this->createSortableBuilds();
        $ascending = array_reverse($descending);
        $header = "@build-group-Nightly @header-{$column}";

        // Problems are sorted in descending order by default, so sort by another column first.
        $other_header = '@build-group-Nightly @header-' . ($column === 'site' ? 'build-name' : 'site');

        $this->browse(function (Browser $browser) use ($header, $other_header, $descending, $ascending): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly')
                ->click($other_header);
            $this->assertSortIndicator($browser, $header, 'none');

            // The first click sorts in descending order, and the second reverses it.
            $browser->click($header);
            $this->assertSortIndicator($browser, $header, 'descending');
            $this->assertBuildOrder($browser, $descending);

            $browser->click($header);
            $this->assertSortIndicator($browser, $header, 'ascending');
            $this->assertBuildOrder($browser, $ascending);

            // The order is remembered.
            $browser->refresh()
                ->waitFor('@build-group-Nightly');
            $this->assertSortIndicator($browser, $header, 'ascending');
            $this->assertBuildOrder($browser, $ascending);
        });
    }

    public function testSortsByMultipleColumns(): void
    {
        $this->createBuild(['name' => 'x', 'testfailed' => 1, 'testpassed' => 0, 'testnotrun' => 0, 'builderrors' => 0, 'buildwarnings' => 0]);
        $this->createBuild(['name' => 'y', 'testfailed' => 1, 'testpassed' => 0, 'testnotrun' => 0, 'builderrors' => 0, 'buildwarnings' => 3]);
        $this->createBuild(['name' => 'z', 'testfailed' => 0, 'testpassed' => 0, 'testnotrun' => 0, 'builderrors' => 0, 'buildwarnings' => 2]);

        $this->browse(function (Browser $browser): void {
            $failed = '@build-group-Nightly @header-tests-failed';
            $warnings = '@build-group-Nightly @header-build-warnings';

            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly')
                ->click('@build-group-Nightly @header-site')
                ->click($failed);
            $this->assertSortIndicator($browser, $failed, 'descending');
            $this->assertSortIndicator($browser, '@build-group-Nightly @header-site', 'none');

            // Holding shift adds a column to sort by, and reverses it on subsequent clicks.
            $this->shiftClick($browser, $warnings);
            $this->assertSortIndicator($browser, $failed, 'descending');
            $this->assertSortIndicator($browser, $warnings, 'descending');
            $this->assertBuildOrder($browser, ['y', 'x', 'z']);

            $this->shiftClick($browser, $warnings);
            $this->assertSortIndicator($browser, $failed, 'descending');
            $this->assertSortIndicator($browser, $warnings, 'ascending');
            $this->assertBuildOrder($browser, ['x', 'y', 'z']);

            $browser->refresh()
                ->waitFor('@build-group-Nightly');
            $this->assertSortIndicator($browser, $failed, 'descending');
            $this->assertSortIndicator($browser, $warnings, 'ascending');
            $this->assertBuildOrder($browser, ['x', 'y', 'z']);

            // Clicking without shift replaces the sort order.
            $browser->click('@build-group-Nightly @header-build-name');
            $this->assertSortIndicator($browser, $failed, 'none');
            $this->assertSortIndicator($browser, $warnings, 'none');
            $this->assertBuildOrder($browser, ['z', 'y', 'x']);
        });
    }

    public function testSortsByQueryParameter(): void
    {
        $this->createSortableBuilds();

        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl('&sort=buildname'))
                ->waitFor('@build-group-Nightly');

            $this->assertSortIndicator($browser, '@build-group-Nightly @header-build-name', 'ascending');
            $this->assertBuildOrder($browser, ['build-a', 'build-b', 'build-c']);
        });
    }

    public function testSortsContinuousBuildsByStartTime(): void
    {
        $this->createBuild(['name' => 'older', 'builderrors' => 5, 'starttime' => self::DATE . ' 11:00:00'], 'Continuous');
        $this->createBuild(['name' => 'newer', 'builderrors' => 0, 'starttime' => self::DATE . ' 13:00:00'], 'Continuous');

        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Continuous');

            $this->assertBuildOrder($browser, ['newer', 'older'], 'Continuous');
        });
    }

    public function testShowsBuildsWithoutResultsLast(): void
    {
        $this->createBuild(['name' => 'one-file', 'updateid' => $this->createUpdate(['nfiles' => 1])->id]);
        $this->createBuild(['name' => 'five-files', 'updateid' => $this->createUpdate(['nfiles' => 5])->id]);
        $this->createBuild(['name' => 'no-update']);
        $this->buildGroup('Nightly')->rules()->create([
            'buildtype' => 'Nightly',
            'buildname' => 'expected',
            'siteid' => $this->createSite()->id,
            'expected' => 1,
            'starttime' => Carbon::create(1980),
        ]);

        $this->browse(function (Browser $browser): void {
            $header = '@build-group-Nightly @header-update-files';
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly')
                ->click($header);
            $this->assertBuildOrder($browser, ['five-files', 'one-file', 'no-update', 'expected']);

            $browser->click($header);
            $this->assertBuildOrder($browser, ['one-file', 'five-files', 'no-update', 'expected']);
        });
    }

    public function testPaginatesBuilds(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->createBuild([
                'name' => sprintf('build-%02d', $i),
                'starttime' => Carbon::parse(self::DATE . ' 00:00:00', 'UTC')->addMinutes($i),
            ]);
        }
        $newest_first = array_map(fn (int $i): string => sprintf('build-%02d', $i), range(12, 1));

        $this->browse(function (Browser $browser) use ($newest_first): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly');

            // 50 builds are shown per page by default.
            $this->assertBuildOrder($browser, $newest_first);
            $browser->assertNotPresent('@build-group-Nightly @pagination');

            $browser->select('@build-group-Nightly @items-per-page', '10')
                ->waitFor('@build-group-Nightly @pagination');
            $this->assertBuildOrder($browser, array_slice($newest_first, 0, 10));

            $browser->within('@build-group-Nightly @pagination', fn (Browser $pagination) => $pagination->clickLink('2'));
            $this->assertBuildOrder($browser, array_slice($newest_first, 10));

            $browser->within('@build-group-Nightly @pagination', fn (Browser $pagination) => $pagination->clickLink('First'));
            $this->assertBuildOrder($browser, array_slice($newest_first, 0, 10));

            // The number of builds per page is remembered.
            self::assertSame('10', $browser->plainCookie('num_builds_per_page'));
            $browser->refresh()
                ->waitFor('@build-group-Nightly @pagination');
            $this->assertBuildOrder($browser, array_slice($newest_first, 0, 10));

            $browser->select('@build-group-Nightly @items-per-page', '-1');
            $this->assertBuildOrder($browser, $newest_first);
            $browser->assertNotPresent('@build-group-Nightly @pagination');
        });
    }

    public function testHidesPageSizeForSmallBuildGroups(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->createBuild();
        }

        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly')
                ->assertNotPresent('@build-group-Nightly @items-per-page')
                ->assertNotPresent('@build-group-Nightly @pagination');
        });
    }

    public function testSortsCoverage(): void
    {
        foreach (['high' => 90, 'low' => 30, 'medium' => 60] as $name => $tested) {
            $build = $this->createBuild(['name' => $name]);
            DB::table('coveragesummary')->insert([
                'buildid' => $build->id,
                'loctested' => $tested,
                'locuntested' => 100 - $tested,
            ]);
        }

        $this->browse(function (Browser $browser): void {
            $header = '@coverage @header-percentage';
            $rows = '@coverage @coverage-row @build-name';
            $browser->visit($this->indexUrl())
                ->waitFor('@coverage')
                ->click($header);
            $this->assertSortIndicator($browser, $header, 'descending');
            $this->assertTexts($browser, $rows, ['high', 'medium', 'low']);

            $browser->click($header);
            $this->assertSortIndicator($browser, $header, 'ascending');
            $this->assertTexts($browser, $rows, ['low', 'medium', 'high']);

            $browser->refresh()
                ->waitFor('@coverage');
            $this->assertSortIndicator($browser, $header, 'ascending');
            $this->assertTexts($browser, $rows, ['low', 'medium', 'high']);
        });
    }

    public function testSortsDynamicAnalysis(): void
    {
        foreach (['some' => 3, 'none' => 0, 'many' => 9] as $name => $defects) {
            $build = $this->createBuild(['name' => $name]);
            DB::table('dynamicanalysissummary')->insert([
                'buildid' => $build->id,
                'checker' => 'Valgrind',
                'numdefects' => $defects,
            ]);
        }

        $this->browse(function (Browser $browser): void {
            $header = '@dynamic-analysis @header-defect-count';
            $rows = '@dynamic-analysis @dynamic-analysis-row @build-name';
            $browser->visit($this->indexUrl())
                ->waitFor('@dynamic-analysis')
                ->click($header);
            $this->assertSortIndicator($browser, $header, 'descending');
            $this->assertTexts($browser, $rows, ['many', 'some', 'none']);

            $browser->click($header);
            $this->assertSortIndicator($browser, $header, 'ascending');
            $this->assertTexts($browser, $rows, ['none', 'some', 'many']);

            $browser->refresh()
                ->waitFor('@dynamic-analysis');
            $this->assertSortIndicator($browser, $header, 'ascending');
            $this->assertTexts($browser, $rows, ['none', 'some', 'many']);
        });
    }

    public function testSortsSubProjectDependencies(): void
    {
        $subproject = $this->project->subprojects()->create(['name' => 'subproject', 'groupid' => -1]);
        foreach (['dependency-b', 'dependency-c', 'dependency-a'] as $name) {
            DB::table('subproject2subproject')->insert([
                'subprojectid' => $subproject->id,
                'dependsonid' => $this->project->subprojects()->create(['name' => $name, 'groupid' => -1])->id,
            ]);
        }

        $this->browse(function (Browser $browser): void {
            $header = '@subproject-table @header-subproject';
            $rows = '@subproject-table @subproject-row @subproject';
            $browser->visit($this->indexUrl('&subproject=subproject'))
                ->waitFor('@subproject-table')
                ->click($header);
            $this->assertSortIndicator($browser, $header, 'descending');
            $this->assertTexts($browser, $rows, ['dependency-c', 'dependency-b', 'dependency-a']);

            $browser->click($header);
            $this->assertSortIndicator($browser, $header, 'ascending');
            $this->assertTexts($browser, $rows, ['dependency-a', 'dependency-b', 'dependency-c']);
        });
    }
}
