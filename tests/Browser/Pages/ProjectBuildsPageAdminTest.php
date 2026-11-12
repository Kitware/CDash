<?php

namespace Tests\Browser\Pages;

use App\Enums\ProjectRole;
use App\Models\Build;
use App\Models\BuildGroupRule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The actions which project administrators can take on builds.
 */
class ProjectBuildsPageAdminTest extends ProjectBuildsPageTestCase
{
    private User $admin;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createProjectMember(ProjectRole::ADMINISTRATOR);
    }

    /**
     * A selector for the nth build in a build group.
     */
    private function row(int $n, string $group = 'Nightly'): string
    {
        return "@build-group-{$group} [data-test=\"build-row\"]:nth-child({$n})";
    }

    private function openAdminOptions(Browser $browser, string $row): Browser
    {
        return $browser->click("{$row} @build-admin-options-toggle")
            ->waitFor("{$row} @build-admin-options");
    }

    private function isExpected(Build $build, string $group = 'Nightly'): bool
    {
        return BuildGroupRule::where([
            'groupid' => $this->buildGroup($group)->id,
            'buildtype' => $build->type,
            'buildname' => $build->name,
            'siteid' => $build->siteid,
            'expected' => 1,
        ])->whereNull('endtime')->exists();
    }

    public function testHidesAdministrativeOptionsFromOtherUsers(): void
    {
        $this->createBuild();
        $user = $this->createProjectMember(ProjectRole::USER);

        $this->browse(function (Browser $browser) use ($user): void {
            $browser->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly @build-row')
                ->assertNotPresent('@build-admin-options-toggle')
                ->assertNotPresent('@bulk-select-toggle');

            $browser->loginAs($user)
                ->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly @build-row')
                ->assertNotPresent('@build-admin-options-toggle')
                ->assertNotPresent('@bulk-select-toggle');

            $browser->loginAs($this->admin)
                ->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly @build-row')
                ->assertPresent('@build-admin-options-toggle')
                ->assertPresent('@bulk-select-toggle');
        });
    }

    /**
     * @return array<string,array{bool}>
     */
    public static function parentBuild(): array
    {
        return [
            'build without children' => [false],
            'build with children' => [true],
        ];
    }

    #[DataProvider('parentBuild')]
    public function testMarksBuildsDone(bool $has_children): void
    {
        $build = $this->createBuild(['done' => false]);
        if ($has_children) {
            $this->createChildBuild($build, 'subproject-' . Str::uuid()->toString());
        }

        $this->browse(function (Browser $browser) use ($build): void {
            $button = "{$this->row(1)} @toggle-done-button";

            $browser->loginAs($this->admin)
                ->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly');
            $this->openAdminOptions($browser, $this->row(1))
                ->assertSeeIn($button, 'Mark as Done')
                ->click($button)
                ->waitForTextIn($button, 'Mark as Not Done')
                ->waitUsing(5, 100, fn (): bool => Build::findOrFail($build->id)->done);

            $browser->refresh()
                ->waitFor('@build-group-Nightly');
            $this->openAdminOptions($browser, $this->row(1))
                ->assertSeeIn($button, 'Mark as Not Done')
                ->click($button)
                ->waitForTextIn($button, 'Mark as Done')
                ->waitUsing(5, 100, fn (): bool => !Build::findOrFail($build->id)->done);
        });
    }

    public function testRemembersChangesToBuildsOnOtherPages(): void
    {
        for ($hour = 1; $hour <= 11; $hour++) {
            $this->createBuild(['starttime' => self::DATE . sprintf(' %02d:00:00', $hour), 'done' => false]);
        }

        $this->browse(function (Browser $browser): void {
            $button = "{$this->row(1)} @toggle-done-button";
            $pagination = '@build-group-Nightly @pagination';

            $browser->loginAs($this->admin)
                ->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly')
                ->select('@build-group-Nightly @items-per-page', '10')
                ->waitFor($pagination);
            $this->openAdminOptions($browser, $this->row(1))
                ->click($button)
                ->waitForTextIn($button, 'Mark as Not Done');

            $browser->within($pagination, fn (Browser $pagination) => $pagination->clickLink('2'))
                ->waitUntilMissing($button)
                ->within($pagination, fn (Browser $pagination) => $pagination->clickLink('First'));
            $this->openAdminOptions($browser, $this->row(1))
                ->assertSeeIn($button, 'Mark as Not Done');
        });
    }

    public function testMarksBuildsExpected(): void
    {
        $build = $this->createBuild(['name' => 'expected']);

        $this->browse(function (Browser $browser) use ($build): void {
            $button = "{$this->row(1)} @toggle-expected-button";

            $browser->loginAs($this->admin)
                ->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly');
            $this->openAdminOptions($browser, $this->row(1))
                ->assertSeeIn($button, 'Mark this build as expected')
                ->click($button)
                ->waitForTextIn($button, 'Mark this build as not expected')
                ->waitUsing(5, 100, fn (): bool => $this->isExpected($build));

            $browser->refresh()
                ->waitFor('@build-group-Nightly');
            $this->openAdminOptions($browser, $this->row(1))
                ->assertSeeIn($button, 'Mark this build as not expected')
                ->click($button)
                ->waitForTextIn($button, 'Mark this build as expected')
                ->waitUsing(5, 100, fn (): bool => !$this->isExpected($build))
                ->click($button)
                ->waitForTextIn($button, 'Mark this build as not expected')
                ->waitUsing(5, 100, fn (): bool => $this->isExpected($build));

            // The build is expected on the next day, even though it hasn't submitted.
            $browser->visit($this->indexUrl('', '2025-01-16'))
                ->waitFor('@build-group-Nightly');
            $this->assertBuildOrder($browser, ['expected']);
            self::assertSame('Expected build', $browser->text("{$this->row(1)} @start-time"));

            // It can be marked as not expected from there too.
            $this->openAdminOptions($browser, $this->row(1))
                ->assertSeeIn($button, 'Mark this build as not expected')
                ->click($button);
            $this->assertBuildOrder($browser, []);
            $browser->waitUsing(5, 100, fn (): bool => !$this->isExpected($build));
        });
    }

    public function testMovesBuildsToAnotherGroup(): void
    {
        $build = $this->createBuild(['name' => 'moving']);
        $nightly = (string) $this->buildGroup('Nightly')->id;
        $continuous = (string) $this->buildGroup('Continuous')->id;
        $experimental = (string) $this->buildGroup('Experimental')->id;

        $this->browse(function (Browser $browser) use ($build, $nightly, $continuous, $experimental): void {
            $select = "{$this->row(1)} @move-to-group-select";
            $button = "{$this->row(1)} @move-to-group-button";

            $browser->loginAs($this->admin)
                ->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly');
            $this->openAdminOptions($browser, $this->row(1));

            // The build's current group is selected, and the build can only be moved to a different group.
            self::assertSame($nightly, $browser->value($select));
            self::assertEqualsCanonicalizing(
                [$nightly, $continuous, $experimental],
                array_map(fn ($option) => $option->getAttribute('value'), $browser->elements("{$select} option")),
            );
            self::assertFalse($browser->element($button)?->isEnabled());
            $browser->select($select, $experimental);
            self::assertTrue($browser->element($button)?->isEnabled());
            $browser->select($select, $nightly);
            self::assertFalse($browser->element($button)?->isEnabled());

            $browser->select($select, $experimental)
                ->waitForReload(fn (Browser $browser) => $browser->click($button))
                ->waitFor('@build-group-Experimental')
                ->assertNotPresent('@build-group-Nightly');
            $this->assertBuildOrder($browser, ['moving'], 'Experimental');
            self::assertSame([(int) $experimental], Build::findOrFail($build->id)->buildGroups()->pluck('id')->all());

            $this->openAdminOptions($browser, $this->row(1, 'Experimental'));
            self::assertSame($experimental, $browser->value("{$this->row(1, 'Experimental')} @move-to-group-select"));
            $browser->select("{$this->row(1, 'Experimental')} @move-to-group-select", $nightly)
                ->waitForReload(fn (Browser $browser) => $browser->click("{$this->row(1, 'Experimental')} @move-to-group-button"))
                ->waitFor('@build-group-Nightly')
                ->assertNotPresent('@build-group-Experimental');
            $this->assertBuildOrder($browser, ['moving']);
        });
    }

    public function testRemovesBuilds(): void
    {
        $this->createBuild(['name' => 'kept', 'starttime' => self::DATE . ' 12:00:00']);
        $removed = $this->createBuild(['name' => 'removed', 'starttime' => self::DATE . ' 13:00:00']);

        $this->browse(function (Browser $browser) use ($removed): void {
            $browser->loginAs($this->admin)
                ->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly');
            $this->assertBuildOrder($browser, ['removed', 'kept']);

            // Nothing happens if the removal isn't confirmed.
            $this->openAdminOptions($browser, $this->row(1))
                ->click("{$this->row(1)} @remove-build-button")
                ->waitFor('@cancel-remove-build')
                ->click('@cancel-remove-build')
                ->waitUntilMissing('@confirm-remove-build');
            $this->assertBuildOrder($browser, ['removed', 'kept']);
            self::assertNotNull(Build::find($removed->id));

            $browser->click("{$this->row(1)} @remove-build-button")
                ->waitFor('@confirm-remove-build')
                ->click('@confirm-remove-build');
            $this->assertBuildOrder($browser, ['kept']);
            $browser->waitUsing(5, 100, fn (): bool => Build::whereKey($removed->id)->doesntExist());

            $browser->refresh()
                ->waitFor('@build-group-Nightly');
            $this->assertBuildOrder($browser, ['kept']);
        });
    }

    /**
     * Three builds, from newest to oldest.
     *
     * @return array<Build>
     */
    private function createBuildsForBulkActions(): array
    {
        return [
            $this->createBuild(['name' => 'first', 'starttime' => self::DATE . ' 13:00:00']),
            $this->createBuild(['name' => 'second', 'starttime' => self::DATE . ' 12:00:00']),
            $this->createBuild(['name' => 'third', 'starttime' => self::DATE . ' 11:00:00']),
        ];
    }

    private function selectBuilds(Browser $browser, int ...$rows): Browser
    {
        $browser->click('@build-group-Nightly @bulk-select-toggle')
            ->waitFor('@build-group-Nightly @build-selection-checkbox');
        foreach ($rows as $row) {
            $browser->check("{$this->row($row)} @build-selection-checkbox");
        }
        return $browser->waitFor('@build-group-Nightly @bulk-actions')
            ->assertSeeIn('@build-group-Nightly @bulk-selected-count', count($rows) . ' build(s) selected');
    }

    public function testMarksSelectedBuildsExpected(): void
    {
        [$first, $second, $third] = $this->createBuildsForBulkActions();

        $this->browse(function (Browser $browser) use ($first, $second, $third): void {
            $toggle = '@build-group-Nightly @bulk-select-toggle';

            $browser->loginAs($this->admin)
                ->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly')
                ->assertSeeIn($toggle, 'Bulk Select')
                ->assertNotPresent('@build-selection-checkbox');

            $this->selectBuilds($browser, 1, 2)
                ->assertSeeIn($toggle, 'Exit Selection')
                ->waitForReload(fn (Browser $browser) => $browser->click('@build-group-Nightly @bulk-mark-expected-button'))
                ->waitFor('@build-group-Nightly');
            self::assertTrue($this->isExpected($first));
            self::assertTrue($this->isExpected($second));
            self::assertFalse($this->isExpected($third));
            $this->openAdminOptions($browser, $this->row(1))
                ->assertSeeIn("{$this->row(1)} @toggle-expected-button", 'Mark this build as not expected');

            $this->selectBuilds($browser, 1, 2)
                ->waitForReload(fn (Browser $browser) => $browser->click('@build-group-Nightly @bulk-mark-not-expected-button'))
                ->waitFor('@build-group-Nightly');
            self::assertFalse($this->isExpected($first));
            self::assertFalse($this->isExpected($second));

            // The selection can be cleared without leaving selection mode.
            $this->selectBuilds($browser, 1)
                ->click('@build-group-Nightly @bulk-clear-selection-button')
                ->waitUntilMissing('@build-group-Nightly @bulk-actions')
                ->assertNotChecked("{$this->row(1)} @build-selection-checkbox")
                ->assertSeeIn($toggle, 'Exit Selection');

            $browser->click($toggle)
                ->waitUntilMissing('@build-selection-checkbox')
                ->assertSeeIn($toggle, 'Bulk Select');
        });
    }

    public function testSelectsAllBuilds(): void
    {
        $this->createBuildsForBulkActions();
        $this->buildGroup('Nightly')->rules()->create([
            'buildtype' => 'Nightly',
            'buildname' => 'missing',
            'siteid' => $this->createSite()->id,
            'expected' => 1,
            'starttime' => Carbon::create(1980),
        ]);

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->admin)
                ->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly')
                ->click('@build-group-Nightly @bulk-select-toggle')
                ->waitFor('@build-group-Nightly @bulk-select-all');

            // Builds which haven't submitted can't be selected.
            self::assertCount(3, $browser->elements('@build-group-Nightly @build-selection-checkbox'));

            $browser->check('@build-group-Nightly @bulk-select-all')
                ->waitFor('@build-group-Nightly @bulk-actions')
                ->assertSeeIn('@build-group-Nightly @bulk-selected-count', '3 build(s) selected');
            for ($row = 1; $row <= 3; $row++) {
                $browser->assertChecked("{$this->row($row)} @build-selection-checkbox");
            }

            $browser->uncheck('@build-group-Nightly @bulk-select-all')
                ->waitUntilMissing('@build-group-Nightly @bulk-actions');
            for ($row = 1; $row <= 3; $row++) {
                $browser->assertNotChecked("{$this->row($row)} @build-selection-checkbox");
            }
        });
    }

    public function testMovesSelectedBuilds(): void
    {
        $this->createBuildsForBulkActions();

        $this->browse(function (Browser $browser): void {
            $button = '@build-group-Nightly @bulk-move-button';

            $browser->loginAs($this->admin)
                ->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly');
            $this->selectBuilds($browser, 1, 3);
            self::assertFalse($browser->element($button)?->isEnabled());

            $browser->select('@build-group-Nightly @bulk-move-select', (string) $this->buildGroup('Experimental')->id)
                ->waitForReload(fn (Browser $browser) => $browser->click($button))
                ->waitFor('@build-group-Experimental');
            $this->assertBuildOrder($browser, ['second']);
            $this->assertBuildOrder($browser, ['first', 'third'], 'Experimental');
        });
    }

    public function testMovingSelectedBuildsKeepsWhetherTheyAreExpected(): void
    {
        [$first, , $third] = $this->createBuildsForBulkActions();
        $this->buildGroup('Nightly')->rules()->create([
            'buildtype' => $first->type,
            'buildname' => $first->name,
            'siteid' => $first->siteid,
            'expected' => 1,
            'starttime' => Carbon::create(1980),
        ]);

        $this->browse(function (Browser $browser) use ($first, $third): void {
            $browser->loginAs($this->admin)
                ->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly');
            $this->selectBuilds($browser, 1, 3)
                ->select('@build-group-Nightly @bulk-move-select', (string) $this->buildGroup('Experimental')->id)
                ->waitForReload(fn (Browser $browser) => $browser->click('@build-group-Nightly @bulk-move-button'))
                ->waitFor('@build-group-Experimental');

            self::assertTrue($this->isExpected($first, 'Experimental'));
            self::assertFalse($this->isExpected($third, 'Experimental'));
        });
    }

    public function testDeletesSelectedBuilds(): void
    {
        [$first, $second, $third] = $this->createBuildsForBulkActions();

        $this->browse(function (Browser $browser) use ($first, $second, $third): void {
            $browser->loginAs($this->admin)
                ->visit($this->indexUrl())
                ->waitFor('@build-group-Nightly');
            $this->selectBuilds($browser, 1, 2);

            // Nothing happens if the deletion isn't confirmed.
            $browser->click('@build-group-Nightly @bulk-delete-button')
                ->waitForDialog()
                ->assertDialogOpened('Are you sure you want to delete 2 build(s)? This action cannot be undone.')
                ->dismissDialog();
            $this->assertBuildOrder($browser, ['first', 'second', 'third']);

            $browser->waitForReload(function (Browser $browser): void {
                $browser->click('@build-group-Nightly @bulk-delete-button')
                    ->waitForDialog()
                    ->acceptDialog();
            })->waitFor('@build-group-Nightly');
            $this->assertBuildOrder($browser, ['third']);
            self::assertNull(Build::find($first->id));
            self::assertNull(Build::find($second->id));
            self::assertNotNull(Build::find($third->id));
        });
    }
}
