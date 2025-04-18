<?php

namespace Tests\Browser\Pages;

use App\Models\Project;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;

class ProjectPageTest extends BrowserTestCase
{
    use CreatesProjects;

    private Project $project;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->project->delete();
    }

    public function testProjectPageDisplaysBanner(): void
    {
        $banner_text = Str::uuid()->toString();
        $this->project->banner = $banner_text;
        $this->project->save();

        $this->browse(function (Browser $browser) use ($banner_text) {
            $browser->visit("/index.php?project={$this->project->name}")
                ->whenAvailable('#index_top', function (Browser $browser) use ($banner_text) {
                    $browser->assertSeeIn('@banner', $banner_text);
                });

            $this->project->banner = null;
            $this->project->save();

            $browser->visit("/index.php?project={$this->project->name}")
                ->whenAvailable('#index_top', function (Browser $browser) use ($banner_text) {
                    $browser->assertDontSee($banner_text);
                });
        });
    }
}
