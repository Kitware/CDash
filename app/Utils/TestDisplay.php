<?php

namespace App\Utils;

use App\Models\Test;

final class TestDisplay
{
    public const DEFAULT_NOTRUN_SKIPPED_DETAILS_REGEX = '*skip*';

    /**
     * @return list<string>
     */
    public static function parsePatterns(?string $patternsText): array
    {
        if ($patternsText === null || trim($patternsText) === '') {
            return [];
        }

        $patterns = [];
        foreach (preg_split("/\r\n|\n|\r/", $patternsText) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $patterns[] = $line;
            }
        }

        return $patterns;
    }

    public static function patternToPcre(string $pattern): string
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return '';
        }

        if (str_starts_with($pattern, '/')) {
            return $pattern;
        }

        $parts = preg_split('/(\*|\?)/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return '';
        }

        $regex = '';
        foreach ($parts as $part) {
            if ($part === '*') {
                $regex .= '.*';
            } elseif ($part === '?') {
                $regex .= '.';
            } else {
                $regex .= preg_quote($part, '/');
            }
        }

        return '/(?i)' . $regex . '/';
    }

    public static function detailsMatchesSkippedPattern(?string $details, ?string $patternsText): bool
    {
        if ($details === null || $details === '') {
            return false;
        }

        foreach (self::parsePatterns($patternsText) as $pattern) {
            $pcre = self::patternToPcre($pattern);
            if ($pcre === '') {
                continue;
            }

            $result = @preg_match($pcre, $details);
            if ($result === 1) {
                return true;
            }
        }

        return false;
    }

    public static function isAcceptableNotRun(?string $details, ?string $patternsText): bool
    {
        return self::detailsMatchesSkippedPattern($details, $patternsText);
    }

    public static function statusColorClass(string $status, ?string $details, ?string $patternsText): string
    {
        if ($status === Test::NOTRUN && self::isAcceptableNotRun($details, $patternsText)) {
            return 'normal';
        }

        return match ($status) {
            Test::PASSED => 'normal',
            Test::FAILED => 'error',
            Test::NOTRUN => 'warning',
            default => '',
        };
    }

    public static function statusTextColorClass(string $status, ?string $details, ?string $patternsText): string
    {
        return match (self::statusColorClass($status, $details, $patternsText)) {
            'normal' => 'normal-text',
            'warning' => 'warning-text',
            'error' => 'error-text',
            default => '',
        };
    }

    public static function graphqlStatusColorClass(string $status, ?string $details, ?string $patternsText): string
    {
        $dbStatus = match ($status) {
            'NOT_RUN' => Test::NOTRUN,
            'PASSED' => Test::PASSED,
            'FAILED' => Test::FAILED,
            default => strtolower($status),
        };

        return self::statusColorClass($dbStatus, $details, $patternsText);
    }

    public static function isValidPatternsText(?string $patternsText): bool
    {
        foreach (self::parsePatterns($patternsText) as $pattern) {
            $pcre = self::patternToPcre($pattern);
            if ($pcre === '') {
                return false;
            }

            set_error_handler(static fn (): bool => true);
            $result = preg_match($pcre, '');
            restore_error_handler();

            if ($result === false) {
                return false;
            }
        }

        return true;
    }
}
