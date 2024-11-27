<?php

namespace Tests;

use Exception;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Collection;
use Laravel\Dusk\Browser;
use Laravel\Dusk\Dusk;
use Laravel\Dusk\TestCase as BaseTestCase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;

abstract class BrowserTestCase extends BaseTestCase
{
    use CreatesApplication;
    use MakesGraphQLRequests;
    use DatabaseTruncation;

    private string $original_env_contents = '';

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        Dusk::selectorHtmlAttribute('data-test');

        $env_contents = file_get_contents(base_path('.env'));
        if ($env_contents === false) {
            throw new Exception('Unable to read .env file.');
        }
        $this->original_env_contents = $env_contents;

        file_put_contents(base_path('.env'), str_replace('localhost:8080', 'cdash:8080', $this->original_env_contents));

        Browser::$baseUrl = 'http://cdash:8080';
    }

    public function tearDown(): void
    {
        parent::tearDown();

        file_put_contents(base_path('.env'), $this->original_env_contents);
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            'http://selenium:4444/wd/hub',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}
