<?php

declare(strict_types=1);

namespace App\Enums;

enum TestDiffType: int
{
    case NotRun = 0;
    case Failed = 1;
    case Passed = 2;
    case FailedTimeStatus = 3;
}
