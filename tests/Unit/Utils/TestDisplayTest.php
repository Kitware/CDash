<?php

namespace Tests\Unit\Utils;

use App\Models\Test;
use App\Utils\TestDisplay;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TestDisplayTest extends TestCase
{
    #[DataProvider('detailsMatchesSkippedPatternCases')]
    public function testDetailsMatchesSkippedPattern(
        string $details,
        string $patternsText,
        bool $expected,
    ): void {
        self::assertSame(
            $expected,
            TestDisplay::detailsMatchesSkippedPattern($details, $patternsText),
        );
    }

    /**
     * @return list<array{string, string, bool}>
     */
    public static function detailsMatchesSkippedPatternCases(): array
    {
        return [
            ['Test skipped by user', '*skip*', true],
            ['Test SKIPPED by user', '*skip*', true],
            ['Test SkIpPeD by user', '*skip*', true],
            ['Disabled', '*skip*', false],
            ['', '*skip*', false],
            ['Skipped', 'Skipped', true],
            ['Skipped', 'Disabled', false],
            ['foo bar baz', "*bar*", true],
        ];
    }

    #[DataProvider('statusColorClassCases')]
    public function testStatusColorClass(
        string $status,
        ?string $details,
        string $patternsText,
        string $expected,
    ): void {
        self::assertSame(
            $expected,
            TestDisplay::statusColorClass($status, $details, $patternsText),
        );
    }

    /**
     * @return list<array{string, ?string, string, string}>
     */
    public static function statusColorClassCases(): array
    {
        return [
            [Test::PASSED, null, '*skip*', 'normal'],
            [Test::FAILED, null, '*skip*', 'error'],
            [Test::NOTRUN, 'Some reason', '*skip*', 'warning'],
            [Test::NOTRUN, 'Test skipped', '*skip*', 'normal'],
            [Test::NOTRUN, 'Test SKIPPED', '*skip*', 'normal'],
        ];
    }

    public function testDefaultPatternConstant(): void
    {
        self::assertSame('*skip*', TestDisplay::DEFAULT_NOTRUN_SKIPPED_DETAILS_REGEX);
    }

    public function testIsValidPatternsTextRejectsInvalidRegex(): void
    {
        self::assertFalse(TestDisplay::isValidPatternsText('('));
    }

    public function testIsValidPatternsTextAcceptsDefaultPattern(): void
    {
        self::assertTrue(TestDisplay::isValidPatternsText('*skip*'));
    }
}
