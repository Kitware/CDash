<?php

namespace Tests\Browser\Pages;

use App\Enums\BuildCommandType;
use App\Enums\TargetType;
use App\Http\Submission\Traits\UpdatesSiteInformation;
use App\Models\Build;
use App\Models\BuildUpdate;
use App\Models\CoverageFile;
use App\Models\DynamicAnalysis;
use App\Models\Note;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Models\UploadFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;

class BuildSidebarComponentTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesSites;
    use UpdatesSiteInformation;

    private Project $project;
    private Site $site;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        $this->site = $this->makeSite();
        $this->updateSiteInfoIfChanged($this->site, new SiteInformation([]));
    }

    public function tearDown(): void
    {
        $this->project->delete();
        $this->site->delete();

        parent::tearDown();
    }

    private function assertDisabled(Browser $browser, string $url, string $selector): void
    {
        $browser->visit($url)
            ->waitFor('@sidebar-loaded')
            ->assertAttributeMissing($selector, 'href');
    }

    private function assertNotDisabled(Browser $browser, string $url, string $selector, string $expectedLink): void
    {
        $browser->visit($url)
            ->waitFor('@sidebar-loaded')
            ->assertAttributeContains($selector, 'href', $expectedLink);
    }

    public function testSummaryItem(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-summary', "/builds/{$build->id}");
        });
    }

    public function testUpdateItem(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $this->assertDisabled($browser, "/builds/{$build->id}", '@sidebar-update');

            /** @var BuildUpdate $update */
            $update = BuildUpdate::create([
                'command' => Str::uuid()->toString(),
                'type' => 'GIT',
                'status' => Str::uuid()->toString(),
                'revision' => Str::uuid()->toString(),
                'priorrevision' => Str::uuid()->toString(),
                'path' => Str::uuid()->toString(),
            ]);
            $build->updateStep()->associate($update)->save();

            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-update', "/builds/{$build->id}/update");
        });
    }

    public function testConfigureItem(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $this->assertDisabled($browser, "/builds/{$build->id}", '@sidebar-configure');

            $build->configurewarnings = 10;
            $build->save();
            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-configure', "/builds/{$build->id}/configure");
            $browser->visit("/builds/{$build->id}")
                ->waitFor('@sidebar-loaded')
                ->assertSeeIn('@sidebar-configure', '10');

            $build->configurewarnings = -1;
            $build->configureerrors = 5;
            $build->save();
            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-configure', "/builds/{$build->id}/configure");
            $browser->visit("/builds/{$build->id}")
                ->waitFor('@sidebar-loaded')
                ->assertSeeIn('@sidebar-configure', '5');

            $build->configurewarnings = 10;
            $build->save();
            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-configure', "/builds/{$build->id}/configure");
            $browser->visit("/builds/{$build->id}")
                ->waitFor('@sidebar-loaded')
                ->assertSeeIn('@sidebar-configure', '10')
                ->assertSeeIn('@sidebar-configure', '5');
        });
    }

    public function testBuildItem(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $this->assertDisabled($browser, "/builds/{$build->id}", '@sidebar-build');

            $build->buildwarnings = 10;
            $build->save();
            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-build', "/builds/{$build->id}/build");
            $browser->visit("/builds/{$build->id}")
                ->waitFor('@sidebar-loaded')
                ->assertSeeIn('@sidebar-build', '10');

            $build->buildwarnings = -1;
            $build->builderrors = 5;
            $build->save();
            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-build', "/builds/{$build->id}/build");
            $browser->visit("/builds/{$build->id}")
                ->waitFor('@sidebar-loaded')
                ->assertSeeIn('@sidebar-build', '5');

            $build->buildwarnings = 10;
            $build->save();
            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-build', "/builds/{$build->id}/build");
            $browser->visit("/builds/{$build->id}")
                ->waitFor('@sidebar-loaded')
                ->assertSeeIn('@sidebar-build', '10')
                ->assertSeeIn('@sidebar-build', '5');
        });
    }

    public function testTestsItem(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $this->assertDisabled($browser, "/builds/{$build->id}", '@sidebar-tests');

            $build->testpassed = 10;
            $build->save();
            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-tests', "/builds/{$build->id}/tests");
            $browser->visit("/builds/{$build->id}")
                ->waitFor('@sidebar-loaded')
                ->assertDontSeeIn('@sidebar-tests', '10');

            $build->testpassed = 0;
            $build->testnotrun = 7;
            $build->save();
            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-tests', "/builds/{$build->id}/tests");
            $browser->visit("/builds/{$build->id}")
                ->waitFor('@sidebar-loaded')
                ->assertDontSeeIn('@sidebar-tests', '10');

            $build->testnotrun = 0;
            $build->testfailed = 5;
            $build->save();
            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-tests', "/builds/{$build->id}/tests");
            $browser->visit("/builds/{$build->id}")
                ->waitFor('@sidebar-loaded')
                ->assertSeeIn('@sidebar-tests', '5');

            $build->testpassed = 10;
            $build->testnotrun = 7;
            $build->testfailed = 5;
            $build->save();
            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-tests', "/builds/{$build->id}/tests");
            $browser->visit("/builds/{$build->id}")
                ->waitFor('@sidebar-loaded')
                ->assertSeeIn('@sidebar-tests', '5');
        });
    }

    public function testCoverageItem(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $this->assertDisabled($browser, "/builds/{$build->id}", '@sidebar-coverage');

            $coverageFile = CoverageFile::firstOrCreate([
                'fullpath' => Str::uuid()->toString(),
                'file' => Str::uuid()->toString(),
                'crc32' => 0,
            ]);
            $build->coverageResults()->create([
                'fileid' => $coverageFile->id,
                'locuntested' => 10,
                'loctested' => 10,
                'branchesuntested' => 0,
                'branchestested' => 0,
            ]);

            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-coverage', "/builds/{$build->id}/coverage");
        });
    }

    public function testDynamicAnalysisItem(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $this->assertDisabled($browser, "/builds/{$build->id}", '@sidebar-dynamic-analysis');

            $build->dynamicAnalyses()->save(DynamicAnalysis::factory()->make());

            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-dynamic-analysis', "/builds/{$build->id}/dynamic_analysis");
        });
    }

    public function testFilesItem(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $this->assertDisabled($browser, "/builds/{$build->id}", '@sidebar-files');

            $build->uploadedFiles()->attach(
                UploadFile::create([
                    'filename' => Str::uuid()->toString(),
                    'sha1sum' => Str::uuid()->toString(),
                    'filesize' => 12345,
                    'isurl' => false,
                ])
            );

            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-files', "/builds/{$build->id}/files");

            $build->uploadedFiles()->delete();
            $this->assertDisabled($browser, "/builds/{$build->id}", '@sidebar-files');

            $build->uploadedFiles()->attach(
                UploadFile::create([
                    'filename' => fake()->url(),
                    'sha1sum' => Str::uuid()->toString(),
                    'isurl' => true,
                ])
            );

            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-files', "/builds/{$build->id}/files");
        });
    }

    public function testNotesItem(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $this->assertDisabled($browser, "/builds/{$build->id}", '@sidebar-notes');

            $build->notes()->attach(
                Note::create([
                    'name' => Str::uuid()->toString(),
                    'text' => Str::uuid()->toString(),
                ])
            );

            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-notes', "/builds/{$build->id}/notes");
        });
    }

    public function testInstrumentationItem(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $this->assertDisabled($browser, "/builds/{$build->id}", '@sidebar-instrumentation');

            $build->commands()->create([
                'type' => BuildCommandType::CUSTOM,
                'starttime' => Carbon::now(),
                'duration' => 12345,
                'command' => Str::random(10),
                'result' => Str::random(10),
                'source' => Str::random(10),
                'language' => Str::random(10),
                'config' => Str::random(10),
                'workingdirectory' => Str::uuid()->toString(),
            ]);

            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-instrumentation', "/builds/{$build->id}/commands");
        });
    }

    public function testTargetsItem(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->browse(function (Browser $browser) use ($build): void {
            $this->assertDisabled($browser, "/builds/{$build->id}", '@sidebar-targets');

            $build->targets()->create([
                'name' => Str::uuid()->toString(),
                'type' => TargetType::UNKNOWN,
            ]);

            $this->assertNotDisabled($browser, "/builds/{$build->id}", '@sidebar-targets', "/builds/{$build->id}/targets");
        });
    }
}
