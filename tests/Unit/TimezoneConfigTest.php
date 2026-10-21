<?php

namespace Tests\Unit;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class TimezoneConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['APP_TIMEZONE']);

        parent::tearDown();
    }

    public function testAppTimezoneOnlyControlsSchedule(): void
    {
        $_SERVER['APP_TIMEZONE'] = 'America/New_York';
        $this->refreshApplication();

        self::assertSame('UTC', config('app.timezone'));
        self::assertSame('UTC', date_default_timezone_get());

        $events = app(Schedule::class)->events();
        self::assertNotEmpty($events);
        foreach ($events as $event) {
            self::assertSame('America/New_York', $event->timezone);
        }
    }
}
