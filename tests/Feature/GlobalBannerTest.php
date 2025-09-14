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

    public function testMarkdownLinkGetsConvertedToHtml(): void
    {
        $bannerText = 'link [here](https://example.com)';

        config(['cdash.global_banner' => $bannerText]);

        $this->get('/login')->assertSeeHtml('link <a href="https://example.com">here</a>');
    }

    public function testHtmlTagsAreEncoded(): void
    {
        $bannerText = "text here <script>alert('xss')</script>";

        config(['cdash.global_banner' => $bannerText]);

        $this->get('/login')->assertSeeHtml("text here &lt;script&gt;alert('xss')&lt;/script&gt;");
    }
}
