<?php

namespace Tests\Browser\Pages;

use App\Http\Submission\Traits\UpdatesSiteInformation;
use App\Models\Build;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;
use Tests\Traits\CreatesUsers;

class BuildSummaryPageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesSites;
    use CreatesUsers;
    use UpdatesSiteInformation;

    private Project $project;
    private Site $site;
    private User $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        $this->user = $this->makeNormalUser();

        $this->site = $this->makeSite();
        $this->updateSiteInfoIfChanged($this->site, new SiteInformation([]));
    }

    public function tearDown(): void
    {
        $this->project->delete();
        $this->site->delete();

        parent::tearDown();
    }

    public function testShowsBuildName(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit("/builds/{$build->id}")
                ->waitForText($build->name)
                ->assertSee($build->name)
            ;
        });
    }

    public function testShowsComments(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Comment $comment */
        $comment = $build->comments()->save(Comment::factory()->make([
            'userid' => $this->user->id,
        ]));

        $this->browse(function (Browser $browser) use ($comment, $build): void {
            $browser->visit("/builds/{$build->id}")
                ->waitForText($comment->text)
                ->assertSee($this->user->firstname)
                ->assertSee($this->user->lastname)
            ;
        });
    }
}
