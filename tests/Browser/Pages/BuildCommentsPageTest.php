<?php

namespace Tests\Browser\Pages;

use App\Models\Build;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Models\User;
use App\Services\SiteService;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;
use Tests\Traits\CreatesUsers;

class BuildCommentsPageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesSites;
    use CreatesUsers;

    private Project $project;
    private Site $site;
    private User $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        $this->user = $this->makeNormalUser();

        $this->site = $this->makeSite();
        SiteService::updateSiteInfoIfChanged($this->site, new SiteInformation([]));
    }

    public function tearDown(): void
    {
        $this->project->delete();
        $this->site->delete();
        $this->user->delete();

        parent::tearDown();
    }

    public function testLoggedOutUserCanSeeCommentsButNotAdd(): void
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
            $browser->visit("/builds/{$build->id}/comments")
                ->waitForText($comment->text)
                ->assertSee($this->user->firstname)
                ->assertSee($this->user->lastname)
                ->assertMissing('@comment-text')
                ->assertMissing('@add-comment')
                ->assertSee('log in to add a comment')
            ;
        });
    }

    public function testAddComment(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $commentText = 'This is a test comment ' . Str::random(10);

        $this->browse(function (Browser $browser) use ($build, $commentText): void {
            $browser->loginAs($this->user)
                ->visit("/builds/{$build->id}/comments")
                ->waitFor('@comment-text')
                ->assertPresent('@comment-text')
                ->assertPresent('@add-comment')
                ->type('@comment-text', $commentText)
                ->click('@add-comment')
                ->waitForText($commentText)
                ->assertSee($this->user->firstname)
                ->assertSee($this->user->lastname)
            ;
        });
    }

    public function testNoComments(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $browser->visit("/builds/{$build->id}/comments")
                ->waitFor('@no-comments-message')
                ->assertSee('No comments yet.')
                ->assertMissing('@comments-list');
        });
    }
}
