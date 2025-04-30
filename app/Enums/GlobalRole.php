<?php

declare(strict_types=1);

namespace App\Enums;

enum GlobalRole: string
{
    case USER = 'USER';
    case ADMINISTRATOR = 'ADMINISTRATOR';
}
