<?php

namespace App\Enums;

enum SubmissionValidationType: int
{
    case SILENT = 0;
    case WARN = 1;
    case REJECT = 2;
}
