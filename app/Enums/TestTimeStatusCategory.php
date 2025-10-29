<?php

namespace App\Enums;

enum TestTimeStatusCategory: string
{
    case PASSED = 'PASSED';
    case FAILED = 'FAILED';
}
