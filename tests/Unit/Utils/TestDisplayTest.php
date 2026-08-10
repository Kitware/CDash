<?php

namespace Tests\Unit\Utils;

use App\Models\Test;
use App\Utils\TestDisplay;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TestDisplayTest extends TestCase
{
    #[DataProvider('isAcceptableNotRunCases')]
    public function testIsAcceptableNotRun(?string $details, bool $expected): void
    {
        self::assertSame($expected, TestDisplay::isAcceptableNotRun($details));
    }

    /**
     * @return list<array{?string, bool}>
     */
    public static function isAcceptableNotRunCases(): array
    {
        return [
            ['Disabled', true],
            ['disabled', false],
            ['DISABLED', false],
            ['Test Disabled', false],
            ['Disabled test', false],
            ['Skipped', false],
            ['', false],
            [null, false],
        ];
    }

    #[DataProvider('statusColorClassCases')]
    public function testStatusColorClass(
        string $status,
        ?string $details,
        string $expected,
    ): void {
        self::assertSame(
            $expected,
            TestDisplay::statusColorClass($status, $details),
        );
    }

    /**
     * @return list<array{string, ?string, string}>
     */
    public static function statusColorClassCases(): array
    {
        return [
            [Test::PASSED, null, 'normal'],
            [Test::FAILED, null, 'error'],
            [Test::NOTRUN, 'Some reason', 'warning'],
            [Test::NOTRUN, 'Disabled', 'normal'],
            [Test::NOTRUN, 'skipped', 'warning'],
        ];
    }
}
