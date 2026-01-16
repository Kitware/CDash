<?php

namespace Tests\Browser\Pages;

use App\Http\Submission\Traits\UpdatesSiteInformation;
use App\Models\Build;
use App\Models\Note;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;

class BuildNotesPageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesSites;
    use UpdatesSiteInformation;

    private Project $project;

    private Build $build;

    private Site $site;

    /**
     * @var Collection<int|string, Note>
     */
    private Collection $notes;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        $this->site = $this->makeSite();
        $this->updateSiteInfoIfChanged($this->site, new SiteInformation([]));

        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);
        $this->build = $build;

        $this->notes = collect();
    }

    public function tearDown(): void
    {
        $this->project->delete();
        $this->site->delete();

        foreach ($this->notes as $note) {
            $note->delete();
        }
        $this->notes = collect();

        parent::tearDown();
    }

    private function addNote(): Note
    {
        $note = Note::create([
            'name' => Str::uuid()->toString(),
            'text' => Str::uuid()->toString(),
        ]);

        $this->build->notes()->attach($note);

        $this->notes[] = $note;
        return $note;
    }

    public function testShowsBuildName(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/notes")
                ->waitForText($this->build->name)
                ->assertSee($this->build->name)
            ;
        });
    }

    public function testShowsMessageIfNoNotes(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/notes")
                ->waitFor('@no-notes-message')
                ->assertMissing('@notes-content')
            ;
        });
    }

    public function testMenuPagination(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->addNote();
        }

        $this->notes = $this->notes->sortByDesc('id');

        self::assertCount(120, $this->notes);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/notes")
                ->waitFor('@notes-menu')
                // Wait for min and max IDs to ensure multiple pages of data have loaded properly...
                ->waitForTextIn('@notes-menu', $this->notes->first()->name ?? '')
                ->waitForTextIn('@notes-menu', $this->notes->last()->name ?? '')
                ->assertCount('@notes-menu-item', 120)
            ;
        });
    }

    public function testContentPagination(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->addNote();
        }

        $this->notes = $this->notes->sortByDesc('id');

        self::assertCount(120, $this->notes);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/notes")
                ->waitFor('@notes-content')
                // Wait for min and max IDs to ensure multiple pages of data have loaded properly...
                ->waitForTextIn('@notes-content', $this->notes->first()->name ?? '')
                ->waitForTextIn('@notes-content', $this->notes->last()->name ?? '')
                ->waitForTextIn('@notes-content', $this->notes->first()->text ?? '')
                ->waitForTextIn('@notes-content', $this->notes->last()->text ?? '')
                ->assertCount('@notes-content-item', 120)
            ;
        });
    }
}
