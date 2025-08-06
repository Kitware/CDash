<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class GlobalBannerTest extends TestCase
{
    public function testGlobalBannerAppearsWhenSet(): void
    {
        $bannerText = Str::uuid()->toString();

        $this->get('/login')->assertDontSee($bannerText);

        config(['cdash.global_banner' => $bannerText]);

        $this->get('/login')->assertSee($bannerText);
    }
}
