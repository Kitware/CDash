<?php

namespace Tests\Feature\Services;

use App\Models\Site;
use App\Models\SiteInformation;
use App\Services\SiteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SiteServiceTest extends TestCase
{
    use DatabaseTransactions;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::factory()->create();
    }

    protected function tearDown(): void
    {
        $this->site->delete();

        parent::tearDown();
    }

    public function testCreatesWhenNoInformation(): void
    {
        self::assertEmpty($this->site->information()->get());

        $site_information = new SiteInformation([
            'processorclockfrequency' => 1234,
        ]);
        SiteService::updateSiteInfoIfChanged($this->site, $site_information);

        $this->site->refresh();
        self::assertSame(1234, $this->site->mostRecentInformation?->processorclockfrequency);
    }

    public function testUpdatesWhenChangedInformation(): void
    {
        $this->site->information()->forceCreate([
            'timestamp' => Carbon::now()->subMinute(),
            'processorclockfrequency' => 1234,
        ]);
        $this->site->refresh();
        self::assertCount(1, $this->site->information()->get());

        $site_information = new SiteInformation([
            'processorclockfrequency' => 2345,
        ]);
        SiteService::updateSiteInfoIfChanged($this->site, $site_information);

        $this->site->refresh();
        self::assertSame(2345, $this->site->mostRecentInformation?->processorclockfrequency);
        self::assertCount(2, $this->site->information()->get());
    }

    public function testDoesNotUpdateWhenNoChange(): void
    {
        $this->site->information()->create([
            'processorclockfrequency' => 1234,
        ]);
        $this->site->refresh();
        self::assertCount(1, $this->site->information()->get());
        self::assertSame(1234, $this->site->mostRecentInformation?->processorclockfrequency);

        $site_information = new SiteInformation([
            'processorclockfrequency' => 1234,
        ]);
        SiteService::updateSiteInfoIfChanged($this->site, $site_information);

        $this->site->refresh();
        self::assertSame(1234, $this->site->mostRecentInformation?->processorclockfrequency);
        self::assertCount(1, $this->site->information()->get());
    }

    public function testPreservesDescriptionWhenNoChange(): void
    {
        $this->site->information()->forceCreate([
            'timestamp' => Carbon::now()->subMinute(),
            'description' => 'abc',
            'processorclockfrequency' => 1234,
        ]);
        $this->site->refresh();
        self::assertCount(1, $this->site->information()->get());

        $site_information = new SiteInformation([
            'processorclockfrequency' => 1234,
        ]);
        SiteService::updateSiteInfoIfChanged($this->site, $site_information);

        $this->site->refresh();
        self::assertSame('abc', $this->site->mostRecentInformation?->description);
        self::assertCount(1, $this->site->information()->get());
    }

    public function testPreservesDescriptionWhenChangedInformation(): void
    {
        $this->site->information()->forceCreate([
            'timestamp' => Carbon::now()->subMinute(),
            'description' => 'abc',
            'processorclockfrequency' => 1234,
        ]);
        $this->site->refresh();
        self::assertCount(1, $this->site->information()->get());

        $site_information = new SiteInformation([
            'processorclockfrequency' => 2345,
        ]);
        SiteService::updateSiteInfoIfChanged($this->site, $site_information);

        $this->site->refresh();
        self::assertSame(2345, $this->site->mostRecentInformation?->processorclockfrequency);
        self::assertSame('abc', $this->site->mostRecentInformation->description);
        self::assertCount(2, $this->site->information()->get());
    }

    public function testUpdatesWhenChangedDescription(): void
    {
        $this->site->information()->forceCreate([
            'timestamp' => Carbon::now()->subMinute(),
            'description' => 'abc',
            'processorclockfrequency' => 1234,
        ]);
        $this->site->refresh();
        self::assertCount(1, $this->site->information()->get());

        $site_information = new SiteInformation([
            'description' => 'def',
            'processorclockfrequency' => 1234,
        ]);
        SiteService::updateSiteInfoIfChanged($this->site, $site_information);

        $this->site->refresh();
        self::assertSame('def', $this->site->mostRecentInformation?->description);
        self::assertCount(2, $this->site->information()->get());
    }

    public function testDoesNotSaveWhenAllNull(): void
    {
        $this->site->information()->create([
            'processorclockfrequency' => 1234,
        ]);
        $this->site->refresh();
        self::assertCount(1, $this->site->information()->get());
        self::assertSame(1234, $this->site->mostRecentInformation?->processorclockfrequency);

        SiteService::updateSiteInfoIfChanged($this->site, new SiteInformation());

        $this->site->refresh();
        self::assertSame(1234, $this->site->mostRecentInformation?->processorclockfrequency);
        self::assertCount(1, $this->site->information()->get());
    }

    public function testCreatesInitialInformationWhenAllNull(): void
    {
        SiteService::updateSiteInfoIfChanged($this->site, new SiteInformation());

        $this->site->refresh();
        self::assertCount(1, $this->site->information()->get());
    }
}
