<?php

declare(strict_types=1);

namespace App\GraphQL\Scalars;

use Illuminate\Support\Carbon;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateTimeUtc as LighthouseDateTimeUtc;

/**
 * Converts input values to UTC, the timezone CDash stores timestamps in.  Laravel passes a
 * Carbon instance to the database as its local time without an offset, so an input like
 * 09:00-04:00 would otherwise be compared against stored timestamps as 09:00 UTC.
 */
final class DateTimeUtc extends LighthouseDateTimeUtc
{
    protected function parse(string $value): Carbon
    {
        return parent::parse($value)->utc();
    }
}
