<?php

namespace Tests\Browser\Pages;

use App\Enums\ProjectRole;
use App\Models\Build;
use App\Models\BuildGroup;
use App\Models\BuildUpdate;
use App\Models\Label;
use App\Models\Project;
use App\Models\Site;
use App\Models\SubProject;
use App\Models\User;
use DateTimeInterface;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;

/**
 * Shared setup for the browser tests of the project builds page (index.php and viewBuildGroup.php).
 *
 * The page is going to be rewritten, so these tests only interact with it through data-test attributes,
 * URLs, cookies and the database.  Avoid selectors which depend on the page's markup or framework.
 */
abstract class ProjectBuildsPageTestCase extends BrowserTestCase
{
    use CreatesProjects;

    /**
     * The testing day which most builds are submitted on.  The project's nightly time is midnight UTC.
     */
    protected const DATE = '2025-01-15';

    protected Project $project;

    /**
     * Records which aren't deleted along with the project.
     *
     * @var array<Model>
     */
    private array $cleanup = [];

    private ?Site $defaultSite = null;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    public function tearDown(): void
    {
        try {
            $this->browse(function (Browser $browser): void {
                // The page saves sorting, paging and display settings in cookies, and Dusk reuses the
                // browser for each test in a class.  Log out first, since the users are deleted below.
                $browser->logout();
                $browser->driver->manage()->deleteAllCookies();
            });
        } finally {
            // Deleting the project deletes its builds, build groups, SubProjects and everything attached to them.
            $this->project->delete();
            foreach ($this->cleanup as $model) {
                $model->delete();
            }
            $this->cleanup = [];
            $this->defaultSite = null;
        }

        parent::tearDown();
    }

    /**
     * Delete records which aren't deleted along with the project once the test finishes.
     */
    protected function deleteAfterTest(Model $model): void
    {
        $this->cleanup[] = $model;
    }

    protected function createSite(string $name = 'site', bool $outOfOrder = false): Site
    {
        $site = Site::create([
            'name' => $name . '-' . Str::uuid()->toString(),
            'outoforder' => $outOfOrder,
        ]);
        $this->deleteAfterTest($site);
        return $site;
    }

    protected function createProjectMember(ProjectRole $role): User
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->deleteAfterTest($user);
        $this->project->users()->attach($user, ['role' => $role]);
        return $user;
    }

    /**
     * Labels must be unique, so a random suffix is added to the text.
     */
    protected function createLabel(string $text): Label
    {
        $label = Label::create(['text' => $text . '-' . Str::uuid()->toString()]);
        $this->deleteAfterTest($label);
        return $label;
    }

    /**
     * @param array<string,mixed> $attributes
     */
    protected function createUpdate(array $attributes = []): BuildUpdate
    {
        $update = BuildUpdate::factory()->create(array_merge([
            'starttime' => self::DATE . ' 11:00:00',
            'endtime' => self::DATE . ' 11:01:00',
            'revision' => '0',
            'warnings' => 0,
        ], $attributes));
        $this->deleteAfterTest($update);
        return $update;
    }

    protected function buildGroup(string $name): BuildGroup
    {
        /** @var BuildGroup $group */
        $group = $this->project->buildgroups()->where('name', $name)->firstOrFail();
        return $group;
    }

    /**
     * Create a build in a build group.  By default, the build is submitted at noon on DATE and has no results.
     *
     * @param array<string,mixed> $attributes
     */
    protected function createBuild(array $attributes = [], string $group = 'Nightly'): Build
    {
        if (!array_key_exists('siteid', $attributes)) {
            $this->defaultSite ??= $this->createSite();
            $attributes['siteid'] = $this->defaultSite->id;
        }

        $starttime = $attributes['starttime'] ?? self::DATE . ' 12:00:00';
        $starttime = $starttime instanceof DateTimeInterface
            ? Carbon::instance($starttime)
            : Carbon::parse(is_string($starttime) ? $starttime : '', 'UTC');

        /** @var Build $build */
        $build = $this->project->builds()->forceCreate(array_merge([
            'name' => 'build-' . Str::uuid()->toString(),
            'type' => $group,
            'uuid' => Str::uuid()->toString(),
            'starttime' => $starttime,
            'endtime' => $starttime->copy()->addMinutes(10),
            'submittime' => $starttime->copy()->addMinutes(10),
        ], $attributes));
        $build->buildGroups()->attach($this->buildGroup($group));

        return $build;
    }

    /**
     * Create a child build for a SubProject, labeled with the SubProject's name like CTest does.  Labels must
     * be unique, so callers should pass a unique SubProject name.
     *
     * @param array<string,mixed> $attributes
     */
    protected function createChildBuild(Build $parent, string $subproject_name, int $position = 0, array $attributes = []): Build
    {
        /** @var SubProject $subproject */
        $subproject = $this->project->subprojects()->firstOrCreate(
            ['name' => $subproject_name],
            ['groupid' => -1, 'position' => $position],
        );

        $child = $this->createBuild(array_merge([
            'name' => $parent->name,
            'siteid' => $parent->siteid,
            'starttime' => $parent->starttime,
            'parentid' => $parent->id,
            'subprojectid' => $subproject->id,
        ], $attributes));

        $label = Label::firstOrCreate(['text' => $subproject_name]);
        if ($label->wasRecentlyCreated) {
            $this->deleteAfterTest($label);
        }
        $child->labels()->attach($label);

        return $child;
    }

    /**
     * @return non-empty-string
     */
    protected function indexUrl(string $query = '', ?string $date = self::DATE): string
    {
        $url = '/index.php?project=' . rawurlencode($this->project->name);
        if ($date !== null) {
            $url .= '&date=' . $date;
        }
        return $url . $query;
    }

    /**
     * Wait for the elements matching a selector to have the expected text.
     *
     * @param array<string> $expected
     */
    protected function assertTexts(Browser $browser, string $selector, array $expected): void
    {
        $this->assertElements($browser, $selector, $expected, fn (RemoteWebElement $element): string => trim($element->getText()));
    }

    /**
     * Wait for the inputs matching a selector to have the expected values.
     *
     * @param array<string> $expected
     */
    protected function assertValues(Browser $browser, string $selector, array $expected): void
    {
        $this->assertElements($browser, $selector, $expected, fn (RemoteWebElement $element): string => (string) $element->getAttribute('value'));
    }

    /**
     * @param array<string> $expected
     * @param callable(RemoteWebElement): string $read
     */
    private function assertElements(Browser $browser, string $selector, array $expected, callable $read): void
    {
        $actual = null;
        try {
            // The page may replace the elements while they're being read, so keep trying.
            $browser->waitUsing(5, 100, function () use ($browser, $selector, $expected, $read, &$actual): bool {
                $actual = array_map($read, $browser->elements($selector));
                return $actual === $expected;
            });
        } catch (TimeoutException) {
            // Fall through to the assertion below, which reports the difference.
        }
        self::assertSame($expected, $actual, $selector);
    }

    /**
     * The rows matching a selector, keyed by the text of one of their cells.
     *
     * @return array<string,RemoteWebElement>
     */
    protected function rowsByCell(Browser $browser, string $rows, string $column): array
    {
        $result = [];
        foreach ($browser->elements($rows) as $row) {
            $result[trim($this->cell($row, $column)->getText())] = $row;
        }
        return $result;
    }

    protected function cell(RemoteWebElement $row, string $column): RemoteWebElement
    {
        return $this->findIn($row, "[data-test=\"{$column}\"]");
    }

    private function findIn(RemoteWebElement $context, string $selector): RemoteWebElement
    {
        $elements = $context->findElements(WebDriverBy::cssSelector($selector));
        self::assertNotSame([], $elements, "No element matches {$selector}.");
        return $elements[0];
    }

    /**
     * The absolute URL a link points to, regardless of whether its href attribute is relative.
     */
    protected function href(Browser|RemoteWebElement $context, string $selector = ''): string
    {
        if ($context instanceof Browser) {
            $element = $context->element($selector);
            self::assertNotNull($element, "No element matches {$selector}.");
        } else {
            $element = $selector === '' ? $context : $this->findIn($context, $selector);
        }
        $href = $element->getDomProperty('href');
        return is_string($href) ? $href : '';
    }

    /**
     * The names of the builds in a build group, in the order they are displayed.
     *
     * @param array<string> $expected
     */
    protected function assertBuildOrder(Browser $browser, array $expected, string $group = 'Nightly'): void
    {
        $this->assertTexts($browser, "@build-group-{$group} @build-row @build-name-text", $expected);
    }

    protected function shiftClick(Browser $browser, string $selector): void
    {
        (new WebDriverActions($browser->driver))
            ->keyDown(null, WebDriverKeys::SHIFT)
            ->click($browser->element($selector))
            ->keyUp(null, WebDriverKeys::SHIFT)
            ->perform();
    }

    /**
     * The direction a sortable column header shows that its table is sorted in.
     */
    protected function sortIndicator(Browser $browser, string $header): string
    {
        $indicator = $browser->element("{$header} [data-test^=\"sort-\"]");
        return $indicator === null ? 'none' : substr((string) $indicator->getAttribute('data-test'), strlen('sort-'));
    }

    /**
     * Wait for a sortable column header to show the direction its table is sorted in.
     *
     * @param 'ascending'|'descending'|'none' $direction
     */
    protected function assertSortIndicator(Browser $browser, string $header, string $direction): void
    {
        $actual = null;
        try {
            $browser->waitUsing(5, 100, function () use ($browser, $header, $direction, &$actual): bool {
                $actual = $this->sortIndicator($browser, $header);
                return $actual === $direction;
            });
        } catch (TimeoutException) {
            // Fall through to the assertion below, which reports the difference.
        }
        self::assertSame($direction, $actual, $header);
    }
}
