<?php

namespace Tests\Browser\Pages;

use App\Enums\ProjectRole;
use App\Models\Build;
use App\Models\Comment;
use App\Models\DynamicAnalysisSummary;
use App\Models\Note;
use App\Models\SubProject;
use App\Models\UploadFile;
use Facebook\WebDriver\WebDriverBy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;

class ProjectBuildsPageTest extends ProjectBuildsPageTestCase
{
    public function testProjectPageDisplaysBanner(): void
    {
        $banner_text = Str::uuid()->toString();
        $this->project->banner = $banner_text;
        $this->project->save();

        $this->browse(function (Browser $browser) use ($banner_text): void {
            $browser->visit("/index.php?project={$this->project->name}")
                ->whenAvailable('#index_top', function (Browser $browser) use ($banner_text): void {
                    $browser->assertSeeIn('@banner', $banner_text);
                });

            $this->project->banner = null;
            $this->project->save();

            $browser->visit("/index.php?project={$this->project->name}")
                ->whenAvailable('#index_top', function (Browser $browser) use ($banner_text): void {
                    $browser->assertDontSee($banner_text);
                });
        });
    }

    public function testShowsBuildsInTheirBuildGroups(): void
    {
        $flagged_site = $this->createSite('flagged', true);
        $this->createBuild([
            'name' => 'nightly-older',
            'siteid' => $flagged_site->id,
            'starttime' => self::DATE . ' 11:00:00',
        ]);
        $this->createBuild([
            'name' => 'nightly-newer',
            'starttime' => self::DATE . ' 13:00:00',
        ]);
        $experimental = $this->createBuild(['name' => 'experimental'], 'Experimental');
        // A build from another day.
        $this->createBuild(['name' => 'yesterday', 'starttime' => '2025-01-14 12:00:00']);

        $this->browse(function (Browser $browser) use ($experimental): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Experimental');

            // Groups without builds are hidden, and the others are shown in the project's order.
            $this->assertTexts($browser, '@build-group-name', ['Nightly', 'Experimental']);
            self::assertSame('2 builds', $browser->text('@build-group-Nightly @build-group-count'));
            self::assertSame('1 build', $browser->text('@build-group-Experimental @build-group-count'));

            // Without any errors, the newest builds are shown first.
            $this->assertBuildOrder($browser, ['nightly-newer', 'nightly-older']);
            $this->assertBuildOrder($browser, ['experimental'], 'Experimental');

            self::assertCount(1, $browser->elements('@build-group-Nightly @site-out-of-order'));
            self::assertCount(0, $browser->elements('@build-group-Experimental @site-out-of-order'));

            self::assertStringEndsWith(
                "/builds/{$experimental->id}",
                $this->href($browser, '@build-group-Experimental @build-summary-link'),
            );
            self::assertStringContainsString(
                "/sites/{$experimental->siteid}?project={$this->project->id}",
                $this->href($browser, '@build-group-Experimental @site a'),
            );
        });
    }

    public function testShowsBuildResults(): void
    {
        $build = $this->createBuild([
            'updateid' => $this->createUpdate(['nfiles' => 8])->id,
            'configureerrors' => 1,
            'configurewarnings' => 2,
            'builderrors' => 3,
            'buildwarnings' => 4,
            'testnotrun' => 5,
            'testfailed' => 6,
            'testpassed' => 7,
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly');

            self::assertSame('Files', $browser->text('@build-group-Nightly @header-update-files'));

            $browser->within('@build-group-Nightly @build-row', function (Browser $row) use ($build): void {
                $expected = [
                    'update-files' => ['8', "/builds/{$build->id}/update", 'normal'],
                    'configure-errors' => ['1', "/builds/{$build->id}/configure", 'error'],
                    'configure-warnings' => ['2', "/builds/{$build->id}/configure", 'warning'],
                    'build-errors' => ['3', "/builds/{$build->id}/build", 'error'],
                    'build-warnings' => ['4', "/builds/{$build->id}/build", 'warning'],
                    'tests-not-run' => ['5', "/builds/{$build->id}/tests?filters=", 'warning'],
                    'tests-failed' => ['6', "/builds/{$build->id}/tests?filters=", 'error'],
                    'tests-passed' => ['7', "/builds/{$build->id}/tests?filters=", 'normal'],
                ];
                foreach ($expected as $column => [$text, $link, $status]) {
                    self::assertSame($text, $row->text("@{$column}"), $column);
                    self::assertStringContainsString($link, $this->href($row, "@{$column} a"), $column);
                    self::assertContains($status, explode(' ', (string) $row->attribute("@{$column}", 'class')), $column);
                }

                $test_filters = [
                    'tests-not-run' => 'NOT_RUN',
                    'tests-failed' => 'FAILED',
                    'tests-passed' => 'PASSED',
                ];
                foreach ($test_filters as $column => $status) {
                    self::assertStringContainsString(
                        '{"eq":{"status":"' . $status . '"}}',
                        urldecode($this->href($row, "@{$column} a")),
                    );
                }
            });
        });
    }

    public function testShowsRevisionsForUpdatesWithRevisions(): void
    {
        $this->createBuild([
            'updateid' => $this->createUpdate(['revision' => 'abcdef0123456789'])->id,
        ]);

        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly');

            self::assertSame('Revision', $browser->text('@build-group-Nightly @header-update-files'));
            self::assertSame('abcdef', $browser->text('@build-group-Nightly @build-row @update-files'));
        });
    }

    public function testHidesColumnsWithoutResults(): void
    {
        $this->createBuild();

        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly')
                ->assertPresent('@build-group-Nightly @header-start-time');

            $columns = [
                'update-files',
                'configure-errors',
                'configure-warnings',
                'build-errors',
                'build-warnings',
                'tests-not-run',
                'tests-failed',
                'tests-passed',
            ];
            foreach ($columns as $column) {
                $browser->assertNotPresent("@build-group-Nightly @header-{$column}")
                    ->assertNotPresent("@build-group-Nightly @build-row @{$column}");
            }
        });
    }

    public function testShowsChangesSincePreviousBuild(): void
    {
        $build = $this->createBuild([
            'configureerrors' => 0,
            'configurewarnings' => 5,
            'builderrors' => 3,
            'buildwarnings' => 2,
            'testnotrun' => 0,
            'testfailed' => 4,
            'testpassed' => 1,
        ]);
        DB::table('builderrordiff')->insert([
            ['buildid' => $build->id, 'type' => 0, 'difference_positive' => 2, 'difference_negative' => 1],
            ['buildid' => $build->id, 'type' => 1, 'difference_positive' => 0, 'difference_negative' => 3],
        ]);
        DB::table('configureerrordiff')->insert(['buildid' => $build->id, 'type' => 1, 'difference' => -2]);
        DB::table('testdiff')->insert(['buildid' => $build->id, 'type' => 1, 'difference_positive' => 1, 'difference_negative' => 0]);

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly');

            $browser->within('@build-group-Nightly @build-row', function (Browser $row) use ($build): void {
                self::assertSame(['3', '+2', '-1'], preg_split('/\s+/', $row->text('@build-errors')));
                self::assertSame(['2', '-3'], preg_split('/\s+/', $row->text('@build-warnings')));
                self::assertSame(['5', '-2'], preg_split('/\s+/', $row->text('@configure-warnings')));
                self::assertSame(['4', '+1'], preg_split('/\s+/', $row->text('@tests-failed')));

                $row->assertPresent("@build-errors a[href$=\"builds/{$build->id}/build?onlydeltap\"]")
                    ->assertPresent("@build-errors a[href$=\"builds/{$build->id}/build?onlydeltan\"]");
                self::assertStringContainsString(
                    "/builds/{$build->id}/tests?onlydelta&",
                    $this->href($row, '@tests-failed a:last-of-type'),
                );
            });
        });
    }

    public function testShowsLabels(): void
    {
        $one_label = $this->createLabel('label');
        $build = $this->createBuild(['name' => 'one-label', 'starttime' => self::DATE . ' 12:00:00']);
        $build->labels()->attach($one_label);
        $build = $this->createBuild(['name' => 'two-labels', 'starttime' => self::DATE . ' 11:00:00']);
        $build->labels()->attach([$this->createLabel('first')->id, $this->createLabel('second')->id]);
        $this->createBuild(['name' => 'no-labels', 'starttime' => self::DATE . ' 10:00:00']);

        $this->browse(function (Browser $browser) use ($one_label): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly');

            $this->assertBuildOrder($browser, ['one-label', 'two-labels', 'no-labels']);
            $this->assertTexts($browser, '@build-group-Nightly @build-row @labels', [$one_label->text, '(2 labels)', '(none)']);

            $this->project->displaylabels = false;
            $this->project->save();

            $browser->refresh()
                ->waitFor('@build-group-Nightly @build-row')
                ->assertNotPresent('@build-group-Nightly @header-labels')
                ->assertNotPresent('@build-group-Nightly @build-row @labels');
        });
    }

    public function testShowsExpectedBuildsWhichHaveNotSubmitted(): void
    {
        $site = $this->createSite();
        $this->buildGroup('Nightly')->rules()->create([
            'buildtype' => 'Nightly',
            'buildname' => 'missing',
            'siteid' => $site->id,
            'expected' => 1,
            'starttime' => Carbon::create(1980),
        ]);
        $present_site = $this->createSite();
        $this->createBuild(['name' => 'present', 'siteid' => $present_site->id]);

        $this->browse(function (Browser $browser) use ($site, $present_site): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly');

            self::assertSame('1 of 2 builds', $browser->text('@build-group-Nightly @build-group-count'));
            // Expected builds which haven't submitted are always shown last.
            $this->assertBuildOrder($browser, ['present', 'missing']);
            $this->assertTexts($browser, '@build-group-Nightly @build-row @site', [
                $present_site->name,
                $site->name,
            ]);
            $rows = $browser->elements('@build-group-Nightly @build-row');
            self::assertSame('Expected build', trim($this->cell($rows[1], 'start-time')->getText()));
            self::assertCount(1, $rows[0]->findElements(WebDriverBy::cssSelector('[data-test="build-summary-link"]')));
            self::assertCount(0, $rows[1]->findElements(WebDriverBy::cssSelector('[data-test="build-summary-link"]')));
        });
    }

    public function testShowsCoverage(): void
    {
        $coverage = [
            'high' => [90, 10, 5, -5],
            'medium' => [60, 40, 0, 0],
            'low' => [30, 70, 0, 0],
        ];
        /** @var array<string,Build> $builds */
        $builds = [];
        foreach ($coverage as $name => [$tested, $untested, $tested_diff, $untested_diff]) {
            $builds[$name] = $this->createBuild(['name' => $name]);
            DB::table('coveragesummary')->insert([
                'buildid' => $builds[$name]->id,
                'loctested' => $tested,
                'locuntested' => $untested,
                'loctesteddiff' => $tested_diff,
                'locuntesteddiff' => $untested_diff,
            ]);
        }

        $this->browse(function (Browser $browser) use ($builds): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@coverage');

            $rows = $this->rowsByCell($browser, '@coverage @coverage-row', 'build-name');
            self::assertEqualsCanonicalizing(['high', 'medium', 'low'], array_keys($rows));

            // The project's coverage threshold is 70%.
            $expected = [
                'high' => [['90%', '+5%'], 'normal', ['90', '+5'], ['10', '-5']],
                'medium' => [['60%'], 'warning', ['60'], ['40']],
                'low' => [['30%'], 'error', ['30'], ['70']],
            ];
            foreach ($expected as $name => [$percentage, $status, $tested, $untested]) {
                $percentage_cell = $this->cell($rows[$name], 'percentage');
                self::assertSame($percentage, preg_split('/\s+/', trim($percentage_cell->getText())), $name);
                self::assertContains($status, explode(' ', (string) $percentage_cell->getAttribute('class')), $name);
                self::assertStringEndsWith(
                    "/builds/{$builds[$name]->id}/coverage",
                    $this->href($percentage_cell, 'a'),
                );
                self::assertSame($tested, preg_split('/\s+/', trim($this->cell($rows[$name], 'loc-tested')->getText())), $name);
                self::assertSame($untested, preg_split('/\s+/', trim($this->cell($rows[$name], 'loc-untested')->getText())), $name);
                self::assertSame($builds[$name]->site?->name, trim($this->cell($rows[$name], 'site')->getText()));
            }
        });
    }

    public function testShowsDynamicAnalysis(): void
    {
        $defects = ['defective' => 3, 'clean' => 0];
        $builds = [];
        foreach ($defects as $name => $count) {
            $builds[$name] = $this->createBuild(['name' => $name]);
            DynamicAnalysisSummary::create([
                'buildid' => $builds[$name]->id,
                'checker' => 'Valgrind',
                'numdefects' => $count,
            ]);
        }

        $this->browse(function (Browser $browser) use ($builds): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@dynamic-analysis');

            $rows = $this->rowsByCell($browser, '@dynamic-analysis @dynamic-analysis-row', 'build-name');
            self::assertEqualsCanonicalizing(['defective', 'clean'], array_keys($rows));

            foreach (['defective' => ['3', 'error'], 'clean' => ['0', 'normal']] as $name => [$count, $status]) {
                self::assertSame('Valgrind', trim($this->cell($rows[$name], 'checker')->getText()));
                $count_cell = $this->cell($rows[$name], 'defect-count');
                self::assertSame($count, trim($count_cell->getText()));
                self::assertContains($status, explode(' ', (string) $count_cell->getAttribute('class')));
                self::assertStringEndsWith(
                    "/builds/{$builds[$name]->id}/dynamic_analysis",
                    $this->href($count_cell, 'a'),
                );
            }
        });
    }

    public function testShowsNotesFilesAndComments(): void
    {
        $build = $this->createBuild();

        $note = Note::create(['name' => 'note.txt', 'text' => 'A note']);
        $this->deleteAfterTest($note);
        $build->notes()->attach($note, ['time' => Carbon::now()]);

        $file = UploadFile::create(['filename' => 'file.txt', 'filesize' => 1, 'sha1sum' => sha1('file'), 'isurl' => false]);
        $this->deleteAfterTest($file);
        $build->uploadedFiles()->attach($file);

        Comment::create([
            'buildid' => $build->id,
            'userid' => $this->createProjectMember(ProjectRole::ADMINISTRATOR)->id,
            'text' => 'A comment',
            'status' => 0,
            'timestamp' => Carbon::now(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly');

            $browser->within('@build-group-Nightly @build-row', function (Browser $row) use ($build): void {
                self::assertStringEndsWith("/builds/{$build->id}/notes", $this->href($row, '@build-notes-link'));
                self::assertStringEndsWith("/builds/{$build->id}/files", $this->href($row, '@build-files-link'));
                self::assertStringEndsWith("/builds/{$build->id}/comments", $this->href($row, '@build-comments-link'));
            });
        });
    }

    public function testShowsTestingDataUrl(): void
    {
        $this->project->testingdataurl = 'https://example.com/testing-data';
        $this->project->save();
        $this->createBuild();

        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@testing-data-url')
                ->assertSeeIn('@testing-data-url', 'https://example.com/testing-data')
                ->assertAttribute('@testing-data-url', 'href', 'https://example.com/testing-data');
        });
    }

    public function testRejectsDatesInTheFuture(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit($this->indexUrl('', '2999-01-01'))
                ->waitFor('@error-message')
                ->assertSeeIn('@error-message', 'CDash cannot predict the future (yet)');
        });
    }

    public function testParentBuildsLinkToTheirChildren(): void
    {
        $site = $this->createSite();
        $parent = $this->createBuild(['name' => 'parent', 'siteid' => $site->id]);
        $first = 'first-' . Str::uuid()->toString();
        $second = 'second-' . Str::uuid()->toString();
        $this->createChildBuild($parent, $first, 2, ['builderrors' => 1, 'starttime' => self::DATE . ' 12:01:00']);
        $this->createChildBuild($parent, $second, 1, ['builderrors' => 0, 'starttime' => self::DATE . ' 12:02:00']);

        $this->browse(function (Browser $browser) use ($site, $parent, $first, $second): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly @child-builds-link');

            // Children aren't shown alongside their parent.
            $this->assertBuildOrder($browser, ['parent']);
            self::assertStringContainsString(
                "index.php?project={$this->project->name}&parentid={$parent->id}",
                $this->href($browser, '@build-group-Nightly @child-builds-link'),
            );

            $browser->click('@build-group-Nightly @child-builds-link')
                ->waitFor('@parent-build-info')
                ->assertSeeIn('@parent-site', $site->name)
                ->assertSeeIn('@parent-build-name', 'parent')
                ->assertSeeIn('@parent-num-subprojects', '2')
                ->waitFor('@build-group-Nightly');

            // Children with problems are listed first, then the rest from oldest to newest.
            $this->assertTexts($browser, '@build-group-Nightly @build-row @subproject', [$first, $second]);
            $browser->assertNotPresent('@build-group-Nightly @build-row @site')
                ->assertNotPresent('@build-group-Nightly @build-row @build-name')
                ->assertPresent('@build-group-Nightly @header-start-time')
                ->assertNotPresent('@build-group-Nightly @header-order');
        });
    }

    public function testShowsSubProjectOrderForBuildsWhichBuiltAllSubProjectsAtOnce(): void
    {
        $parent = $this->createBuild(['name' => 'parent']);
        $first = 'first-' . Str::uuid()->toString();
        $second = 'second-' . Str::uuid()->toString();
        $this->createChildBuild($parent, $first, 2, ['builderrors' => 1]);
        $this->createChildBuild($parent, $second, 1, ['builderrors' => 0]);

        $this->browse(function (Browser $browser) use ($parent, $first, $second): void {
            $browser->visit($this->indexUrl("&parentid={$parent->id}", null))
                ->waitFor('@build-group-Nightly');

            $this->assertTexts($browser, '@build-group-Nightly @build-row @subproject', [$first, $second]);
            $this->assertTexts($browser, '@build-group-Nightly @build-row @order', ['2', '1']);
            $browser->assertPresent('@build-group-Nightly @header-order')
                ->assertNotPresent('@build-group-Nightly @header-start-time');
        });
    }

    public function testShowsSubProjectDependencies(): void
    {
        $name = 'subproject-' . Str::uuid()->toString();
        $subproject = $this->project->subprojects()->create(['name' => $name, 'groupid' => -1]);
        foreach (['dependency-one', 'dependency-two'] as $dependency_name) {
            /** @var SubProject $dependency */
            $dependency = $this->project->subprojects()->create(['name' => $dependency_name, 'groupid' => -1]);
            DB::table('subproject2subproject')->insert([
                'subprojectid' => $subproject->id,
                'dependsonid' => $dependency->id,
            ]);
        }
        $parent = $this->createBuild(['name' => 'parent']);
        $this->createChildBuild($parent, $name, 1, ['name' => 'subproject-build']);

        $this->browse(function (Browser $browser) use ($name): void {
            $browser->visit($this->indexUrl("&subproject={$name}"))
                ->waitFor('@subproject-table');

            $rows = $this->rowsByCell($browser, '@subproject-table @subproject-row', 'subproject');
            self::assertEqualsCanonicalizing(['dependency-one', 'dependency-two'], array_keys($rows));
            self::assertStringContainsString(
                'index.php?subproject=dependency-one&project=' . $this->project->name . '&date=' . self::DATE,
                $this->href($this->cell($rows['dependency-one'], 'subproject'), 'a'),
            );

            // Only the subproject's builds are shown.
            $this->assertBuildOrder($browser, ['subproject-build']);
        });
    }

    public function testViewBuildGroupShowsOneGroupWithTimeline(): void
    {
        $this->createBuild(['name' => 'nightly', 'builderrors' => 1, 'buildwarnings' => 0]);
        $this->createBuild(['name' => 'experimental', 'builderrors' => 0, 'buildwarnings' => 0], 'Experimental');

        $this->browse(function (Browser $browser): void {
            $browser->visit('/viewBuildGroup.php?project=' . $this->project->name . '&buildgroup=Experimental&date=' . self::DATE)
                ->waitFor('@build-group-Experimental')
                ->assertNotPresent('@build-group-Nightly')
                ->waitFor('@timeline @timeline-chart svg');

            $this->assertBuildOrder($browser, ['experimental'], 'Experimental');
        });
    }
}
