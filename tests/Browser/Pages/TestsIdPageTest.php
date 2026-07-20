<?php

namespace Tests\Browser\Pages;

use App\Http\Submission\Traits\UpdatesSiteInformation;
use App\Models\Build;
use App\Models\Image;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Models\Test;
use App\Models\TestOutput;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\DataProvider;
use Random\RandomException;
use Tests\BrowserTestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSites;

class TestsIdPageTest extends BrowserTestCase
{
    use CreatesProjects;
    use CreatesSites;
    use UpdatesSiteInformation;

    private Project $project;
    private Build $build;
    private Site $site;

    public function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
        $this->site = $this->makeSite();
        $this->updateSiteInfoIfChanged($this->site, new SiteInformation([]));

        $this->build = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'projectid' => $this->project->id,
            'name' => 'Test Build',
            'type' => 'Release',
            'uuid' => Str::uuid()->toString(),
            'starttime' => Carbon::now()->subMinutes(10),
        ]);
    }

    public function tearDown(): void
    {
        $this->project->delete();
        $this->site->delete();
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createTest(array $attributes = []): Test
    {
        $output = TestOutput::create([
            'path' => (string) Str::uuid(),
            'command' => (string) Str::uuid(),
            'output' => '',
        ]);

        $attributes = array_merge([
            'outputid' => $output->id,
            'timemean' => 0,
            'timestd' => 0,
        ], $attributes);

        return $this->build->tests()->create($attributes);
    }

    public function testPassingTest(): void
    {
        /** @var Test $test */
        $test = $this->createTest([
            'testname' => (string) Str::uuid(),
            'status' => 'passed',
        ]);

        $this->browse(function (Browser $browser) use ($test): void {
            $date = $this->build->starttime->toDateString();
            $url = url('queryTests.php') . '?project=' . rawurlencode($this->project->name) . "&date={$date}&filtercount=1&showfilters=1&field1=testname&compare1=61&value1=" . rawurlencode($test->testname);

            $browser->visit("/tests/{$test->id}")
                ->waitForText($test->testname)
                ->assertSee($this->build->name)
                ->assertSeeIn('@test-status', 'Passed')
                ->assertPresent('@test-name-link')
                // Browser might decode %20 to space in href attribute
                ->assertAttribute('@test-name-link', 'href', str_replace('%20', ' ', $url));
        });
    }

    public function testLabelsAreVisible(): void
    {
        /** @var Test $test */
        $test = $this->createTest([
            'testname' => (string) Str::uuid(),
            'status' => 'failed',
        ]);

        $label1 = $test->labels()->create(['text' => (string) Str::uuid()]);
        $label2 = $test->labels()->create(['text' => (string) Str::uuid()]);

        $this->browse(function (Browser $browser) use ($test, $label1, $label2): void {
            $browser->visit("/tests/{$test->id}")
                ->waitForText($label1->text)
                ->assertSee($label1->text)
                ->assertSee($label2->text);
        });
    }

    public function testFailingTestWithDetailsText(): void
    {
        /** @var Test $test */
        $test = $this->createTest([
            'testname' => (string) Str::uuid(),
            'status' => 'failed',
            'details' => (string) Str::uuid(),
        ]);

        $this->browse(function (Browser $browser) use ($test): void {
            $browser->visit("/tests/{$test->id}")
                ->waitForText($test->details)
                ->assertSeeIn('@test-status', 'Failed')
                ->assertSeeIn('@test-details', $test->details);
        });
    }

    public function testNullEnvironmentAndCommandLine(): void
    {
        /** @var Test $test */
        $test = $this->createTest([
            'testname' => (string) Str::uuid(),
            'status' => 'passed',
        ]);

        $this->browse(function (Browser $browser) use ($test): void {
            $browser->visit("/tests/{$test->id}")
                ->waitForText($test->testname)
                ->assertMissing('@environment-collapse')
                ->assertPresent('@command-line-collapse');
        });
    }

    public function testEnvironmentIsVisibleWhenPresent(): void
    {
        /** @var Test $test */
        $test = $this->createTest([
            'testname' => (string) Str::uuid(),
            'status' => 'passed',
        ]);

        $measurement = $test->testMeasurements()->create([
            'name' => 'Environment',
            'type' => 'text/string',
            'value' => (string) Str::uuid(),
        ]);

        $this->browse(function (Browser $browser) use ($test, $measurement): void {
            $browser->visit("/tests/{$test->id}")
                ->waitFor('@environment-collapse')
                ->click('@environment-collapse')
                ->waitForText($measurement->value)
                ->assertSee($measurement->value);
        });
    }

    public function testEnvironmentExcludedFromMeasurements(): void
    {
        /** @var Test $test */
        $test = $this->createTest([
            'testname' => (string) Str::uuid(),
            'status' => 'passed',
        ]);

        $test->testMeasurements()->create([
            'name' => 'Environment',
            'type' => 'text/string',
            'value' => (string) Str::uuid(),
        ]);
        $otherMeasurement = $test->testMeasurements()->create([
            'name' => (string) Str::uuid(),
            'type' => 'text/string',
            'value' => (string) Str::uuid(),
        ]);

        $this->browse(function (Browser $browser) use ($test, $otherMeasurement): void {
            $browser->visit("/tests/{$test->id}")
                ->waitFor('@measurements-collapse')
                ->click('@measurements-collapse')
                ->waitForText($otherMeasurement->value)
                ->assertSee($otherMeasurement->name)
                ->within('@measurements-collapse', function (Browser $browser): void {
                    $browser->assertDontSee('Environment');
                });
        });
    }

    public function testNumericMeasurementsInSummaryCard(): void
    {
        /** @var Test $test */
        $test = $this->createTest([
            'testname' => (string) Str::uuid(),
            'status' => 'passed',
        ]);

        $measurement = $test->testMeasurements()->create([
            'name' => (string) Str::uuid(),
            'type' => 'numeric/double',
            'value' => '99.99',
        ]);

        $this->browse(function (Browser $browser) use ($test, $measurement): void {
            $browser->visit("/tests/{$test->id}")
                ->waitForText($measurement->name)
                ->within('@numeric-measurement', function (Browser $browser) use ($measurement): void {
                    $browser->assertSee($measurement->name)
                        ->assertSee($measurement->value);
                });
        });
    }

    public static function measurementTypeProvider(): array
    {
        return [
            'numeric' => [
                'numeric/double',
                '1.23',
            ],
            'file' => [
                'file',
                (string) Str::uuid(),
            ],
            'link' => [
                'text/link',
                'http://' . Str::uuid(),
            ],
            'string' => [
                'text/string',
                (string) Str::uuid(),
            ],
            'preformatted' => [
                'text/preformatted',
                (string) Str::uuid(),
            ],
        ];
    }

    #[DataProvider('measurementTypeProvider')]
    public function testMeasurementTypes(string $type, string $value): void
    {
        /** @var Test $test */
        $test = $this->createTest([
            'testname' => (string) Str::uuid(),
            'status' => 'passed',
        ]);

        $measurement = $test->testMeasurements()->create([
            'name' => (string) Str::uuid(),
            'type' => $type,
            'value' => $value,
        ]);

        $this->browse(function (Browser $browser) use ($test, $measurement, $type): void {
            $browser->visit("/tests/{$test->id}")
                ->waitFor('@measurements-collapse')
                ->click('@measurements-collapse')
                ->waitForText($measurement->name);

            $browser->assertSee($measurement->name);

            if ($type === 'text/link') {
                $browser->assertAttribute('@measurement-link', 'href', $measurement->value);
            } elseif ($type === 'file') {
                $browser->assertPresent('@measurement-file-link');
            } else {
                $browser->assertSeeIn('@measurement-value', $measurement->value);
            }
        });
    }

    public function testTrendChartMeasurementSwitching(): void
    {
        /** @var Test $test */
        $test = $this->createTest([
            'testname' => (string) Str::uuid(),
            'status' => 'passed',
        ]);
        $test->time = 10.0;
        $test->save();

        // Create a previous build and test to ensure some trend data exists
        $previousBuild = $this->project->builds()->create([
            'siteid' => $this->site->id,
            'projectid' => $this->project->id,
            'name' => $this->build->name,
            'type' => $this->build->type,
            'uuid' => Str::uuid()->toString(),
            'starttime' => Carbon::now()->subMinutes(20),
        ]);

        $previousTest = $this->createTest([
            'testname' => $test->testname,
            'status' => 'passed',
            'buildid' => $previousBuild->id,
        ]);
        $previousTest->time = 5.0;
        $previousTest->save();

        // Add a numeric measurement to test switching
        $measurement = $test->testMeasurements()->create([
            'name' => (string) Str::uuid(),
            'type' => 'numeric/double',
            'value' => '100',
        ]);

        $this->browse(function (Browser $browser) use ($test, $measurement): void {
            $browser->visit("/tests/{$test->id}")
                ->waitFor('@trend-collapse')
                ->click('@trend-collapse')
                ->waitFor('@measurement-switcher')
                ->assertSelected('@measurement-switcher', 'time')
                ->select('@measurement-switcher', $measurement->name)
                ->waitFor("[data-test-selected-measurement=\"{$measurement->name}\"]")
                ->assertSelected('@measurement-switcher', $measurement->name);
        });
    }

    /**
     * @throws RandomException
     */
    public function testImagesSectionVisibility(): void
    {
        /** @var Test $test */
        $test = $this->createTest([
            'testname' => (string) Str::uuid(),
            'status' => 'passed',
        ]);

        $this->browse(function (Browser $browser) use ($test): void {
            $browser->visit("/tests/{$test->id}")
                ->waitForText($test->testname)
                ->assertMissing('@images-card');
        });

        // Add an image
        $image1 = Image::create([
            'img' => (string) Str::uuid(),
            'extension' => 'png',
            'checksum' => (string) random_int(1000, 9999),
        ]);
        $test->testImages()->create([
            'imgid' => $image1->id,
            'role' => 'TestImage',
        ]);

        $this->browse(function (Browser $browser) use ($test): void {
            $browser->visit("/tests/{$test->id}")
                ->waitForText($test->testname)
                ->assertVisible('@images-card')
                ->within('@images-card', function (Browser $browser): void {
                    $browser->assertMissing('@interactive-image');
                });
        });

        // Add a ValidImage for interactive comparison
        $image2 = Image::create([
            'img' => (string) Str::uuid(),
            'extension' => 'png',
            'checksum' => (string) random_int(1000, 9999),
        ]);
        $test->testImages()->create([
            'imgid' => $image2->id,
            'role' => 'ValidImage',
        ]);

        $this->browse(function (Browser $browser) use ($test): void {
            $browser->visit("/tests/{$test->id}")
                ->waitForText($test->testname)
                ->waitFor('@interactive-image')
                ->assertVisible('@interactive-image');
        });
    }

    public function testOutputCardAlwaysPresent(): void
    {
        /** @var Test $test */
        $test = $this->createTest([
            'testname' => (string) Str::uuid(),
            'status' => 'passed',
        ]);

        $this->browse(function (Browser $browser) use ($test): void {
            $browser->visit("/tests/{$test->id}")
                ->waitForText($test->testname)
                ->waitFor('@output-card')
                ->assertPresent('@output-card')
                ->assertSeeIn('@no-output-message', 'No output for this test.');
        });

        // Add output
        $testOutput = TestOutput::create([
            'path' => (string) Str::uuid(),
            'command' => (string) Str::uuid(),
            'output' => (string) Str::uuid(),
        ]);
        $test->outputid = $testOutput->id;
        $test->save();

        $this->browse(function (Browser $browser) use ($test, $testOutput): void {
            $browser->visit("/tests/{$test->id}")
                ->waitForText($testOutput->output)
                ->assertSee($testOutput->output)
                ->assertMissing('@no-output-message');
        });
    }
}
