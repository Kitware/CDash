<?php

namespace Tests\Browser\Pages;

use App\Http\Submission\Traits\UpdatesSiteInformation;
use App\Models\Build;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Models\UploadFile;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;

class BuildFilesPageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesSites;
    use UpdatesSiteInformation;

    private Project $project;

    private Build $build;

    private Site $site;

    /**
     * @var array<UploadFile>
     */
    private array $files = [];

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
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->project->delete();
        $this->site->delete();

        foreach ($this->files as $file) {
            $file->delete();
        }
        $this->files = [];
    }

    private function addUploadedFile(bool $url = false): UploadFile
    {
        if ($url) {
            $file = UploadFile::create([
                'filename' => fake()->url(),
                'sha1sum' => Str::uuid()->toString(),
                'isurl' => true,
            ]);
        } else {
            $file = UploadFile::create([
                'filename' => Str::uuid()->toString(),
                'sha1sum' => Str::uuid()->toString(),
                'filesize' => 12345,
                'isurl' => false,
            ]);
        }

        $this->build->uploadedFiles()->attach($file);

        $this->files[] = $file;
        return $file;
    }

    public function testShowsMessageIfNoUrlsOrFiles(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit("/build/{$this->build->id}/files")
                ->waitFor('@no-urls-or-files-message')
                ->assertMissing('@urls-table')
                ->assertMissing('@files-table')
            ;
        });
    }

    public function testShowsOnlyUrlsIfOnlyUrlsUploaded(): void
    {
        $file = $this->addUploadedFile(true);

        $this->browse(function (Browser $browser) use ($file) {
            $browser->visit("/build/{$this->build->id}/files")
                ->waitFor('@urls-table')
                ->assertMissing('@no-urls-or-files-message')
                ->assertMissing('@files-table')
                ->assertSeeIn('@urls-table', $file->filename)
            ;
        });
    }

    public function testShowsOnlyFilesIfOnlyFilesUploaded(): void
    {
        $file = $this->addUploadedFile();

        $this->browse(function (Browser $browser) use ($file) {
            $browser->visit("/build/{$this->build->id}/files")
                ->waitFor('@files-table')
                ->assertMissing('@no-urls-or-files-message')
                ->assertMissing('@urls-table')
                ->assertSeeIn('@files-table', $file->filename)
                ->assertSeeIn('@files-table', '12.06 KiB')
                ->assertSeeIn('@files-table', $file->sha1sum)
            ;
        });
    }

    public function testUrlTablePagination(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->addUploadedFile(true);
        }

        $urls = [];
        foreach ($this->files as $file) {
            $urls[] = $file->filename;
        }
        self::assertCount(120, $urls);

        $this->browse(function (Browser $browser) use ($urls) {
            $browser->visit("/build/{$this->build->id}/files")
                ->waitFor('@urls-table')
                ->waitForTextIn('@urls-table', max($urls)) // Wait for max and min to ensure multiple pages have loaded properly...
                ->waitForTextIn('@urls-table', min($urls))
                ->assertCount('@urls-table-row', 120)
            ;
        });
    }

    public function testFileTablePagination(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->addUploadedFile();
        }

        $files = [];
        foreach ($this->files as $file) {
            $files[] = $file->filename;
        }
        self::assertCount(120, $files);

        $this->browse(function (Browser $browser) use ($files) {
            $browser->visit("/build/{$this->build->id}/files")
                ->waitFor('@files-table')
                ->waitForTextIn('@files-table', max($files)) // Wait for max and min to ensure multiple pages have loaded properly...
                ->waitForTextIn('@files-table', min($files))
                ->assertCount('@files-table-row', 120)
            ;
        });
    }
}
