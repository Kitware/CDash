<?php

declare(strict_types=1);

namespace App\Enums;

enum BuildGroupType: string
{
    case DAILY = 'Daily';
    case LATEST = 'Latest';
}
