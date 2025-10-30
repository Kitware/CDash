<?php

namespace Tests;

use Exception;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Laravel\Dusk\Browser;
use Laravel\Dusk\Dusk;
use Laravel\Dusk\TestCase as BaseTestCase;

abstract class BrowserTestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        // The APP_URL must be set prior to config initialization so we can correctly generate test URLs.
        $env_contents = file_get_contents('/cdash/.env');
        if ($env_contents === false) {
            throw new Exception('Unable to read .env file.');
        }
        $env_after_substitution = str_replace('APP_URL=http://localhost:8080', 'APP_URL=http://website:8080', $env_contents);
        file_put_contents('/cdash/.env', $env_after_substitution);

        parent::setUp();

        Dusk::selectorHtmlAttribute('data-test');

        Browser::$baseUrl = 'http://website:8080';
        Browser::$waitSeconds = 10;
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $env_contents = file_get_contents(base_path('.env'));
        if ($env_contents === false) {
            throw new Exception('Unable to read .env file.');
        }

        $env_after_substitution = str_replace('APP_URL=http://website:8080', 'APP_URL=http://localhost:8080', $env_contents);
        file_put_contents(base_path('.env'), $env_after_substitution);
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions())->addArguments(collect([
            '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
        ])->unless($this->hasHeadlessDisabled(), fn (Collection $items) => $items->merge([
            '--disable-gpu',
            '--headless=new',
        ]))->all());

        return RemoteWebDriver::create(
            'http://selenium:4444/wd/hub',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}
