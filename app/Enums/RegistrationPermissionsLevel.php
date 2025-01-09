<?php

namespace App\Enums;

enum RegistrationPermissionsLevel: int
{
    case PUBLIC = 0;
    case PROJECT_ADMIN = 1;
    case ADMIN = 2;
    case DISABLED = 3;
}
