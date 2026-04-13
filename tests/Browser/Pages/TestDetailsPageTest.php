<?php

namespace Tests\Browser\Pages;

use App\Http\Submission\Traits\UpdatesSiteInformation;
use App\Models\Build;
use App\Models\Label;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Models\Test;
use App\Models\TestOutput;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;

class TestDetailsPageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesSites;
    use UpdatesSiteInformation;

    private Project $project;
    private Build $build;
    private Site $site;
    private TestOutput $testOutput;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        $this->site = $this->makeSite();
        $this->updateSiteInfoIfChanged($this->site, new SiteInformation([]));

        $this->testOutput = TestOutput::create([
            'path' => Str::uuid()->toString(),
            'command' => Str::uuid()->toString(),
            'output' => Str::uuid()->toString(),
        ]);

        $this->build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);
    }

    public function tearDown(): void
    {
        $this->project->delete();
        $this->site->delete();
        $this->testOutput->delete();

        parent::tearDown();
    }

    public function testShowsBuildName(): void
    {
        /** @var Test $test */
        $test = $this->build->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'failed',
            'outputid' => $this->testOutput->id,
        ]);

        $this->browse(function (Browser $browser) use ($test): void {
            $browser->visit("/tests/{$test->id}")
                ->waitForText($this->build->name)
                ->assertSee($this->build->name)
            ;
        });
    }

    public function testBasicTestInfoIsVisible(): void
    {
        /** @var Test $test */
        $test = $this->build->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'failed',
            'outputid' => $this->testOutput->id,
            'details' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($test): void {
            $browser->visit("/tests/{$test->id}")
                ->waitForText($this->testOutput->output)
                ->assertSee($this->testOutput->output)
                ->waitForText($test->testname)
                ->assertSee($test->testname)
                ->waitForText('Failed')
                ->assertSee('Failed')
                ->waitForText($test->details)
                ->assertSee($test->details)
            ;
        });
    }

    public function testTestLabelsAreVisible(): void
    {
        /** @var Test $test */
        $test = $this->build->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'failed',
            'outputid' => $this->testOutput->id,
        ]);

        /** @var Label $label1 */
        $label1 = $test->labels()->create([
            'text' => Str::uuid()->toString(),
        ]);

        /** @var Label $label2 */
        $label2 = $test->labels()->create([
            'text' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($label2, $label1, $test): void {
            $browser->visit("/tests/{$test->id}")
                ->waitForText($label1->text)
                ->assertSee($label1->text)
                ->waitForText($label2->text)
                ->assertSee($label2->text)
            ;
        });
    }

    public function testTestHistoryLink(): void
    {
        /** @var Test $test */
        $test = $this->build->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'failed',
            'outputid' => $this->testOutput->id,
        ]);

        $this->build->starttime = Carbon::now()->subMinute();
        $this->build->save();

        $this->browse(function (Browser $browser) use ($test): void {
            $url = url('queryTests.php') . '?project=' . $this->project->name . '&filtercount=1&showfilters=1&field1=testname&compare1=61&value1=' . $test->testname . '&date=' . $this->build->starttime->toDateString();

            $browser->visit("/tests/{$test->id}")
                ->waitForLink($test->testname)
                ->assertPresent('a[href="' . $url . '"]')
            ;
        });
    }

    public function testCommandLineIsHiddenByDefault(): void
    {
        /** @var Test $test */
        $test = $this->build->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'failed',
            'outputid' => $this->testOutput->id,
        ]);

        $this->browse(function (Browser $browser) use ($test): void {
            $browser->visit("/tests/{$test->id}")
                ->waitForText('Show Command Line')
                ->assertDontSee($this->testOutput->command)
                ->click('#commandlinelink')
                ->waitForText($this->testOutput->command)
                ->assertSee($this->testOutput->command)
            ;
        });
    }

    public function testEnvironmentIsHiddenByDefault(): void
    {
        /** @var Test $test */
        $test = $this->build->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'failed',
            'outputid' => $this->testOutput->id,
        ]);

        $this->browse(function (Browser $browser) use ($test): void {
            $browser->visit("/tests/{$test->id}")
                ->waitForText('Show Command Line')
                ->assertDontSee('Show Environment')
            ;

            $environmentValue = Str::uuid()->toString();
            $test->testMeasurements()->create([
                'name' => 'Environment',
                'type' => 'text/string',
                'value' => $environmentValue,
            ]);

            $browser->visit("/tests/{$test->id}")
                ->waitForText('Show Environment')
                ->assertDontSee($environmentValue)
                ->click('#environmentlink')
                ->waitForText($environmentValue)
                ->assertSee($environmentValue)
            ;
        });
    }

    public function testTimeGraphCanBeToggled(): void
    {
        /** @var Test $test */
        $test = $this->build->tests()->create([
            'testname' => Str::uuid()->toString(),
            'status' => 'failed',
            'outputid' => $this->testOutput->id,
        ]);

        $this->browse(function (Browser $browser) use ($test): void {
            $browser->visit("/tests/{$test->id}")
                ->waitForText('Display graphs:')
                ->assertDontSee('View Graph Data as JSON')
                ->select('#GraphSelection', 'time')
                ->waitForText('View Graph Data as JSON')
                ->assertSee('View Graph Data as JSON')
            ;
        });
    }
}
