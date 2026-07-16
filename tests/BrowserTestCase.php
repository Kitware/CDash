<?php

namespace Tests;

use Exception;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
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
        parent::setUp();

        Dusk::selectorHtmlAttribute('data-test');

        Browser::$baseUrl = 'http://website:8080';
        Browser::$waitSeconds = 20;
    }

    public function tearDown(): void
    {
        $this->browse(fn (Browser $browser) => $browser->logout());

        parent::tearDown();
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

    /**
     * Capture screenshots and console logs for failed tests.
     *
     * @param Collection $browsers
     */
    protected function captureFailuresFor($browsers): void
    {
        $browsers->each(function (Browser $browser, int $key): void {
            $name = str_replace('\\', '_', static::class) . '_' . $this->name();
            $screenshotName = 'failure-' . $name . '-' . $key;
            $browser->screenshot($screenshotName);
            $browser->storeConsoleLog($screenshotName);

            $screenshotPath = base_path("tests/Browser/screenshots/{$screenshotName}.png");
            if (file_exists($screenshotPath)) {
                fwrite(STDOUT, "\n<CTestMeasurementFile name=\"TestImage\" type=\"image/png\">$screenshotPath</CTestMeasurementFile>\n");
            }

            $consoleLogPath = base_path("tests/Browser/console/{$screenshotName}.log");
            if (file_exists($consoleLogPath)) {
                fwrite(STDOUT, "\n<CTestMeasurementFile name=\"ConsoleLog\" type=\"text/plain\">$consoleLogPath</CTestMeasurementFile>\n");
            }
        });
    }
}
