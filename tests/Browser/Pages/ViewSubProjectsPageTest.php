<?php

namespace Tests\Browser\Pages;

use App\Models\Project;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;

class ViewSubProjectsPageTest extends BrowserTestCase
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
        // Deleting a project deletes its SubProjects.
        $this->project->delete();

        parent::tearDown();
    }

    public function testNavigatesBetweenSubProjects(): void
    {
        $this->project->subprojects()->create(['name' => 'ThreadPool', 'groupid' => -1]);
        $this->project->subprojects()->create(['name' => 'Teuchos', 'groupid' => -1]);

        $this->browse(function (Browser $browser): void {
            $browser->visit("/viewSubProjects.php?project={$this->project->name}")
                ->waitForLink('ThreadPool')
                ->clickLink('ThreadPool')
                ->waitForLocation('/index.php')
                ->assertQueryStringHas('subproject', 'ThreadPool')
                ->assertQueryStringHas('project', $this->project->name)

                // The SubProjects link is in the header's dashboard menu, which opens on hover.
                ->mouseover('#navigation > li:first-child')
                ->clickLink('SubProjects')
                ->waitForLocation('/viewSubProjects.php')
                ->assertQueryStringHas('project', $this->project->name)

                ->waitForLink('Teuchos')
                ->clickLink('Teuchos')
                ->waitForLocation('/index.php')
                ->assertQueryStringHas('subproject', 'Teuchos')
                ->assertQueryStringHas('project', $this->project->name);
        });
    }
}
