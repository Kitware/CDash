<?php

namespace Tests\Unit;

use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Tests\TestCase;

class CommonFunctionsTest extends TestCase
{
    /**
     * @return array<string,array{string,int}>
     */
    public static function intervals(): array
    {
        // Intervals are measured from the current time, so the length of two years depends on
        // whether a leap day falls within the next two years.  Data providers run before the
        // application sets the time zone, so use UTC explicitly.
        $utc = new DateTimeZone('UTC');
        $two_years = 2 * 365 * 24 * 60 * 60;
        foreach (new DatePeriod(new DateTime('now', $utc), new DateInterval('P1D'), new DateTime('2 years', $utc)) as $day) {
            if ($day->format('m-d') === '02-29') {
                $two_years += 24 * 60 * 60;
                break;
            }
        }

        return [
            'compact seconds' => ['43s', 43],
            'compact minutes and seconds' => ['24m 43s', 1483],
            'compact hours, minutes, and seconds' => ['17h 24m 43s', 62683],
            'verbose days' => ['8 days', 691200],
            'verbose days and compact time' => ['8 days 17h 24m 43s', 753883],
            'verbose years' => ['2 years', $two_years],
            'verbose years and days and compact time' => ['2 years 8 days 17h 24m 43s', $two_years + 753883],
        ];
    }

    #[DataProvider('intervals')]
    public function testGetSecondsFromInterval(string $interval, int $seconds): void
    {
        $this->assertSame($seconds, get_seconds_from_interval($interval));
    }

    public function testExtractTarThrowsForMissingFile(): void
    {
        Storage::fake();

        $this->expectException(FileNotFoundException::class);
        extract_tar('this_file_does_not_exist');
    }
}
