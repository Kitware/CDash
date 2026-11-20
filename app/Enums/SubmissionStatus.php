<?php

declare(strict_types=1);

namespace App\Enums;

enum SubmissionStatus: string
{
    case QUEUED = 'QUEUED';
    case PROCESSING = 'PROCESSING';
    case DONE = 'DONE';
    case FAILED = 'FAILED';
}
