<?php

namespace Tests\Browser\Pages;

use App\Http\Submission\Traits\UpdatesSiteInformation;
use App\Models\Build;
use App\Models\BuildUpdate;
use App\Models\BuildUpdateFile;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;

class BuildUpdatePageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesSites;
    use UpdatesSiteInformation;

    private Project $project;

    private Build $build;

    private Site $site;

    private BuildUpdate $update;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        $this->site = $this->makeSite();
        $this->updateSiteInfoIfChanged($this->site, new SiteInformation([]));

        $this->update = BuildUpdate::factory()->create();

        /** @var Build $build */
        $build = $this->project->builds()->forceCreate([
            'siteid' => $this->site->id,
            'updateid' => $this->update->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);
        $this->build = $build;
    }

    public function tearDown(): void
    {
        $this->project->delete();
        $this->site->delete();
        $this->update->delete();

        parent::tearDown();
    }

    public function testShowsBuildName(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/update")
                ->waitForText($this->build->name)
                ->assertSee($this->build->name)
            ;
        });
    }

    public function testShowsRevision(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/update")
                ->waitForText($this->update->revision)
                ->assertSee($this->update->revision)
            ;
        });
    }

    public function testOnlyShowsPriorRevisionIfSet(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/update")
                ->waitForText($this->update->revision)
                ->assertSee($this->update->priorrevision)
            ;

            $this->update->priorrevision = '';
            $this->update->save();

            $browser->visit("/builds/{$this->build->id}/update")
                ->waitForText($this->update->revision)
                ->assertDontSee($this->update->priorrevision)
            ;
        });
    }

    public function testLinkifiesRevisionAndPriorRevisionIfProjectRepoConfigured(): void
    {
        $this->browse(function (Browser $browser): void {
            $revisionUrl = 'https://example.com/org/repo/commit/' . $this->update->revision;
            $revisionCompareUrl = 'https://example.com/org/repo/compare/' . $this->update->revision . '...' . $this->update->priorrevision;
            $priorRevisionUrl = 'https://example.com/org/repo/commit/' . $this->update->priorrevision;

            $browser->visit("/builds/{$this->build->id}/update")
                ->waitForText($this->update->revision)
                ->assertNotPresent('a[href="' . $revisionUrl . '"]')
                ->assertNotPresent('a[href="' . $revisionCompareUrl . '"]')
                ->assertNotPresent('a[href="' . $priorRevisionUrl . '"]')
            ;

            $this->project->cvsviewertype = 'github';
            $this->project->cvsurl = 'https://example.com/org/repo';
            $this->project->save();

            $browser->visit("/builds/{$this->build->id}/update")
                ->waitForLink($this->update->revision)
                ->assertNotPresent('a[href="' . $revisionUrl . '"]')
                ->assertPresent('a[href="' . $revisionCompareUrl . '"]')
                ->assertPresent('a[href="' . $priorRevisionUrl . '"]')
            ;

            $this->update->priorrevision = '';
            $this->update->save();

            $browser->visit("/builds/{$this->build->id}/update")
                ->waitForLink($this->update->revision)
                ->assertPresent('a[href="' . $revisionUrl . '"]')
                ->assertNotPresent('a[href="' . $revisionCompareUrl . '"]')
                ->assertNotPresent('a[href="' . $priorRevisionUrl . '"]')
            ;
        });
    }

    public function testIndicatesConflictingFiles(): void
    {
        $this->update->updateFiles()->save(
            BuildUpdateFile::factory()->make([
                'status' => 'CONFLICTING',
            ])
        );

        $this->browse(function (Browser $browser): void {
            $browser->visit("/builds/{$this->build->id}/update")
                ->waitForText('CONFLICTING')
                ->assertSee('CONFLICTING')
            ;
        });
    }

    public function testGroupsUpdateFilesByCommit(): void
    {
        $this->browse(function (Browser $browser): void {
            /** @var BuildUpdateFile $updateFile1 */
            $updateFile1 = $this->update->updateFiles()->save(BuildUpdateFile::factory()->make());
            /** @var BuildUpdateFile $updateFile2 */
            $updateFile2 = $this->update->updateFiles()->save(
                BuildUpdateFile::factory()->make([
                    'revision' => $updateFile1->revision,
                    'priorrevision' => $updateFile1->priorrevision,
                    'log' => $updateFile1->log,
                    'author' => $updateFile1->author,
                    'email' => $updateFile1->email,
                    'committer' => $updateFile1->committer,
                    'committeremail' => $updateFile1->committeremail,
                ])
            );
            /** @var BuildUpdateFile $updateFile3 */
            $updateFile3 = $this->update->updateFiles()->save(BuildUpdateFile::factory()->make());

            $browser->visit("/builds/{$this->build->id}/update")
                ->waitForText($updateFile1->revision)
                ->waitForText($updateFile3->revision)
                ->assertCount('@commit-card', 2)
                ->assertSee($updateFile1->revision)
                ->assertSee($updateFile1->author)
                ->assertSee($updateFile1->committer)
                ->assertSee($updateFile1->filename)
                ->assertSee($updateFile1->log)
                ->assertSee($updateFile2->revision)
                ->assertSee($updateFile2->author)
                ->assertSee($updateFile2->committer)
                ->assertSee($updateFile2->filename)
                ->assertSee($updateFile2->log)
                ->assertSee($updateFile3->revision)
                ->assertSee($updateFile3->author)
                ->assertSee($updateFile3->committer)
                ->assertSee($updateFile3->filename)
                ->assertSee($updateFile3->log)
            ;
        });
    }
}
