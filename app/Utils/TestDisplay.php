<?php

namespace App\Utils;

use App\Models\Test;

final class TestDisplay
{
    /**
     * CTest sets Details="Disabled" for tests marked DISABLED.
     */
    public const DISABLED_DETAILS = 'Disabled';

    public static function isAcceptableNotRun(?string $details): bool
    {
        return $details === self::DISABLED_DETAILS;
    }

    public static function statusColorClass(string $status, ?string $details): string
    {
        if ($status === Test::NOTRUN && self::isAcceptableNotRun($details)) {
            return 'normal';
        }

        return match ($status) {
            Test::PASSED => 'normal',
            Test::FAILED => 'error',
            Test::NOTRUN => 'warning',
            default => '',
        };
    }

    public static function statusTextColorClass(string $status, ?string $details): string
    {
        return match (self::statusColorClass($status, $details)) {
            'normal' => 'normal-text',
            'warning' => 'warning-text',
            'error' => 'error-text',
            default => '',
        };
    }

    public static function graphqlStatusColorClass(string $status, ?string $details): string
    {
        $dbStatus = match ($status) {
            'NOT_RUN' => Test::NOTRUN,
            'PASSED' => Test::PASSED,
            'FAILED' => Test::FAILED,
            default => strtolower($status),
        };

        return self::statusColorClass($dbStatus, $details);
    }
}
