<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class SlowPageTest extends TestCase
{
    public function testSlowPageLogsWarning(): void
    {
        Log::shouldReceive('warning')
            ->with(Mockery::pattern('#Slow page\: /\?projectid=10 took \d*\.?\d+ seconds to load#'));
        Config::set('cdash.slow_page_time', 0);
        self::assertNotEmpty($this->get('/?projectid=10')->content());
    }

    public function testFastPageDoesntLogWarning(): void
    {
        Config::set('cdash.slow_page_time', 100);
        Log::shouldReceive('warning')->never();
        $this->get('/?projectid=10');
    }
}
