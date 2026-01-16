<?php

namespace Tests\Feature\Traits;

use App\Http\Submission\Traits\UpdatesSiteInformation;
use App\Models\Site;
use App\Models\SiteInformation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\CreatesSites;

class UpdatesSiteInformationTest extends TestCase
{
    use CreatesSites;
    use DatabaseTransactions;
    use UpdatesSiteInformation;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = $this->makeSite();
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
        $this->updateSiteInfoIfChanged($this->site, $site_information);

        $this->site->refresh();
        self::assertEquals(1234, $this->site->mostRecentInformation?->processorclockfrequency);
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
        $this->updateSiteInfoIfChanged($this->site, $site_information);

        $this->site->refresh();
        self::assertEquals(2345, $this->site->mostRecentInformation?->processorclockfrequency);
        self::assertCount(2, $this->site->information()->get());
    }

    public function testDoesNotUpdateWhenNoChange(): void
    {
        $this->site->information()->create([
            'processorclockfrequency' => 1234,
        ]);
        $this->site->refresh();
        self::assertCount(1, $this->site->information()->get());
        self::assertEquals(1234, $this->site->mostRecentInformation?->processorclockfrequency);

        $site_information = new SiteInformation([
            'processorclockfrequency' => 1234,
        ]);
        $this->updateSiteInfoIfChanged($this->site, $site_information);

        $this->site->refresh();
        self::assertEquals(1234, $this->site->mostRecentInformation?->processorclockfrequency);
        self::assertCount(1, $this->site->information()->get());
    }

    public function testDoesNotSaveWhenAllNull(): void
    {
        $this->site->information()->create([
            'processorclockfrequency' => 1234,
        ]);
        $this->site->refresh();
        self::assertCount(1, $this->site->information()->get());
        self::assertEquals(1234, $this->site->mostRecentInformation?->processorclockfrequency);

        $this->updateSiteInfoIfChanged($this->site, new SiteInformation());

        $this->site->refresh();
        self::assertEquals(1234, $this->site->mostRecentInformation?->processorclockfrequency);
        self::assertCount(1, $this->site->information()->get());
    }

    public function testCreatesInitialInformationWhenAllNull(): void
    {
        $this->updateSiteInfoIfChanged($this->site, new SiteInformation());

        $this->site->refresh();
        self::assertCount(1, $this->site->information()->get());
    }
}
