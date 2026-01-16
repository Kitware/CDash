<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class SlowPageTest extends TestCase
{
    use DatabaseTransactions;

    public function testSlowPageLogsWarning(): void
    {
        Log::shouldReceive('warning')
            ->with(Mockery::pattern('#Slow page\: /login took \d*\.?\d+ seconds to load#'));
        Config::set('cdash.slow_page_time', 0);
        self::assertNotEmpty($this->get('/login')->content());
    }

    public function testFastPageDoesntLogWarning(): void
    {
        Config::set('cdash.slow_page_time', 100);
        Log::shouldReceive('warning')->never();
        $this->get('/login');
        self::assertNotEmpty($this->get('/login')->content());
    }
}
